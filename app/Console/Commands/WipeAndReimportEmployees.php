<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class WipeAndReimportEmployees extends Command
{
    protected $signature = 'employees:wipe-reimport
        {file=public/Master Sheet - HR-2026- DEVOLEPER.xlsx : Path to xlsx}
        {--sheet=Employee_Master1 : Sheet name}
        {--header-row=7 : 1-based header row}
        {--keep-id=1 : Comma-separated Odoo employee IDs to keep (default: Administrator)}
        {--confirm : Required; without this, runs as dry-run}';

    protected $description = 'DESTRUCTIVE: Hard-delete all hr.employee from Odoo (and dependents) then recreate from Master Sheet';

    private const COLUMNS = [
        1 => 'emp_code', 2 => 'name', 3 => 'job_title', 4 => 'parent_name',
        5 => 'parent_role', 6 => 'date_of_joining', 7 => 'contract_end_date',
        8 => 'contract_status', 9 => 'service_years', 10 => 'birthday',
        11 => 'age', 12 => 'family_status', 13 => 'cchi_card_type',
        14 => 'total_salary', 15 => 'basic_salary', 16 => 'nationality_code',
        17 => 'nationality', 18 => 'region', 19 => 'passport_id',
        20 => 'iqama_id', 21 => 'status_label', 22 => 'contract_type',
        23 => 'ot_allowance', 24 => 'loan_balance', 25 => 'allowance_house',
        26 => 'allowance_rent', 27 => 'allowance_transport',
        28 => 'allowance_car', 29 => 'allowance_special',
        30 => 'allowance_project', 31 => 'allowance_food',
        32 => 'allowance_other', 33 => 'alt_ticket',
        34 => 'bonus_eligibility_months', 35 => 'bonus_pm', 36 => 'gosi_pm',
        37 => 'indemnity_pm', 38 => 'leave_accrual_pm',
        39 => 'med_insurance_pm', 40 => 'pa_insurance_pm',
    ];

    private const DATE_FIELDS = ['date_of_joining', 'contract_end_date', 'birthday'];
    private const NUMERIC_FIELDS = [
        'total_salary', 'basic_salary', 'allowance_house', 'allowance_rent',
        'allowance_transport', 'allowance_car', 'allowance_special',
        'allowance_project', 'allowance_food', 'allowance_other', 'ot_allowance',
        'loan_balance', 'alt_ticket', 'bonus_eligibility_months', 'bonus_pm',
        'gosi_pm', 'indemnity_pm', 'leave_accrual_pm', 'med_insurance_pm',
        'pa_insurance_pm',
    ];

    private array $countryCache = [];

    public function handle(OdooService $odoo, SyncService $sync): int
    {
        $file = $this->argument('file');
        if (!str_starts_with($file, '/')) {
            $file = base_path($file);
        }
        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $keepIds = array_filter(array_map('intval', explode(',', (string) $this->option('keep-id'))));
        $confirm = (bool) $this->option('confirm');

        $sheetName = $this->option('sheet');
        $headerRow = (int) $this->option('header-row');

        $this->info("Reading: {$file}");
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly([$sheetName]);
        $book = $reader->load($file);
        $sheet = $book->getSheetByName($sheetName);
        $rows = $this->extractRows($sheet, $headerRow);
        $this->info("Sheet rows: " . count($rows));

        if (!$confirm) {
            $this->warn("DRY RUN — pass --confirm to actually wipe & reimport.");
            $this->showWipeTargets($odoo, $keepIds);
            $this->showCreatePreview($rows);
            return self::SUCCESS;
        }

        $this->wipe($odoo, $keepIds);
        $createdMap = $this->createEmployees($odoo, $rows);
        $this->linkParents($odoo, $rows, $createdMap);

        $this->newLine();
        $this->info("Re-syncing local DB from Odoo...");
        $sync->syncDepartments();
        $sync->syncEmployees();
        $sync->syncContracts();
        $this->info("Done.");

        return self::SUCCESS;
    }

    private function showWipeTargets(OdooService $odoo, array $keepIds): void
    {
        $keep = $keepIds ?: [];
        $domain = [['id', 'not in', $keep], '|', ['active', '=', true], ['active', '=', false]];
        $count = $odoo->searchCount('hr.employee', $domain);
        $this->line("Would delete {$count} hr.employee (keep: " . implode(',', $keep) . ").");
        $this->line("Plus dependents: contracts, payslips, attendance, leaves.");
    }

    private function showCreatePreview(array $rows): void
    {
        $this->line("Would create " . count($rows) . " hr.employee rows. First 3:");
        foreach (array_slice($rows, 0, 3) as $r) {
            $this->line("  - {$r['emp_code']} | {$r['name']} | job: {$r['job_title']}");
        }
    }

    private function wipe(OdooService $odoo, array $keepIds): void
    {
        $this->newLine();
        $this->warn("WIPING Odoo data (keep employee IDs: " . implode(',', $keepIds) . ")");

        $empDomain = [['id', 'not in', $keepIds], '|', ['active', '=', true], ['active', '=', false]];
        $empIds = $odoo->search('hr.employee', $empDomain);
        $this->line("Found " . count($empIds) . " employees to remove.");

        if (empty($empIds)) {
            return;
        }

        $this->deleteDependents($odoo, 'hr.payslip.line', [['slip_id.employee_id', 'in', $empIds]]);

        $payslipIds = $odoo->search('hr.payslip', [['employee_id', 'in', $empIds]]);
        if ($payslipIds) {
            $this->line("Cancelling " . count($payslipIds) . " payslips before delete...");
            try {
                $odoo->write('hr.payslip', $payslipIds, ['state' => 'cancel']);
            } catch (Throwable $e) {
                $this->warn("Payslip cancel failed (continuing): " . $e->getMessage());
            }
            $this->deleteIds($odoo, 'hr.payslip', $payslipIds);
        }

        $this->deleteDependents($odoo, 'hr.attendance', [['employee_id', 'in', $empIds]]);
        $this->deleteDependents($odoo, 'hr.leave', [['employee_id', 'in', $empIds]]);
        $this->deleteDependents($odoo, 'hr.leave.allocation', [['employee_id', 'in', $empIds]]);

        $contractIds = $odoo->search('hr.contract', [['employee_id', 'in', $empIds]]);
        if ($contractIds) {
            $this->line("Cancelling " . count($contractIds) . " contracts before delete...");
            try {
                $odoo->write('hr.contract', $contractIds, ['state' => 'cancel']);
            } catch (Throwable $e) {
                $this->warn("Contract cancel failed (continuing): " . $e->getMessage());
            }
            $this->deleteIds($odoo, 'hr.contract', $contractIds);
        }

        $this->line("Deleting " . count($empIds) . " employees...");
        try {
            $odoo->unlink('hr.employee', $empIds);
            $this->info("  ✓ unlinked.");
        } catch (Throwable $e) {
            $this->warn("Bulk unlink failed: " . $e->getMessage());
            $this->line("Trying one-by-one...");
            $failed = [];
            foreach ($empIds as $id) {
                try {
                    $odoo->unlink('hr.employee', [$id]);
                } catch (Throwable $e2) {
                    $failed[] = $id . ': ' . $e2->getMessage();
                }
            }
            $this->warn("Per-employee failures: " . count($failed));
            foreach (array_slice($failed, 0, 5) as $f) {
                $this->line('  - ' . $f);
            }
        }
    }

    private function deleteDependents(OdooService $odoo, string $model, array $domain): void
    {
        try {
            $ids = $odoo->search($model, $domain);
        } catch (Throwable $e) {
            $this->warn("Skip {$model} (search failed): " . $e->getMessage());
            return;
        }
        $this->deleteIds($odoo, $model, $ids);
    }

    private function deleteIds(OdooService $odoo, string $model, array $ids): void
    {
        if (!$ids) {
            return;
        }
        $this->line("Deleting " . count($ids) . " {$model}...");
        try {
            $odoo->unlink($model, $ids);
            $this->info("  ✓");
        } catch (Throwable $e) {
            $this->warn("  ✗ " . $e->getMessage());
        }
    }

    private function createEmployees(OdooService $odoo, array $rows): array
    {
        $this->newLine();
        $this->info("Creating " . count($rows) . " employees in Odoo...");
        $map = []; // emp_code => odoo_id
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();
        foreach ($rows as $row) {
            $payload = $this->buildCreatePayload($row, $odoo);
            try {
                $id = $odoo->create('hr.employee', $payload);
                $map[$row['emp_code']] = $id;
            } catch (Throwable $e) {
                $this->newLine();
                $this->warn("Create failed for {$row['emp_code']} ({$row['name']}): " . $e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info("Created " . count($map) . " of " . count($rows) . ".");
        return $map;
    }

    private function buildCreatePayload(array $row, OdooService $odoo): array
    {
        $p = ['name' => $row['name']];
        if (!empty($row['job_title'])) {
            $p['job_title'] = $row['job_title'];
        }
        if (!empty($row['birthday'])) {
            $p['birthday'] = $row['birthday'];
        }
        if (!empty($row['iqama_id'])) {
            $p['identification_id'] = $row['iqama_id'];
        }
        if (!empty($row['passport_id'])) {
            $p['passport_id'] = $row['passport_id'];
        }
        if (!empty($row['nationality_code'])) {
            $cid = $this->lookupCountryId($row['nationality_code'], $row['nationality'] ?? '', $odoo);
            if ($cid) {
                $p['country_id'] = $cid;
            }
        }
        if (!empty($row['family_status'])) {
            $m = $this->mapFamilyStatusToOdoo($row['family_status']);
            if ($m) {
                $p['marital'] = $m;
            }
        }
        $statusLow = mb_strtolower(trim($row['status_label'] ?? ''));
        if (in_array($statusLow, ['withdrawn', 'inactive', 'terminated', 'resigned'], true)) {
            $p['active'] = false;
        }
        return $p;
    }

    private function linkParents(OdooService $odoo, array $rows, array $createdMap): void
    {
        $this->newLine();
        $this->info("Linking manager hierarchy (parent_id)...");
        $byNameKey = [];
        foreach ($rows as $r) {
            $byNameKey[mb_strtolower(trim($r['name']))] = $createdMap[$r['emp_code']] ?? null;
        }
        $linked = 0;
        $missing = [];
        foreach ($rows as $r) {
            $childId = $createdMap[$r['emp_code']] ?? null;
            $parentName = trim((string) ($r['parent_name'] ?? ''));
            if (!$childId || $parentName === '') {
                continue;
            }
            $key = mb_strtolower($parentName);
            $parentId = $byNameKey[$key] ?? null;
            if (!$parentId) {
                $missing[] = "{$r['emp_code']} {$r['name']} → '{$parentName}'";
                continue;
            }
            try {
                $odoo->write('hr.employee', [$childId], ['parent_id' => $parentId]);
                $linked++;
            } catch (Throwable $e) {
                $missing[] = "{$r['emp_code']} (write failed): " . $e->getMessage();
            }
        }
        $this->info("Linked {$linked} parent relationships.");
        if ($missing) {
            $this->warn("Unmatched/failed parent links (" . count($missing) . "):");
            foreach ($missing as $m) {
                $this->line('  - ' . $m);
            }
        }
    }

    private function extractRows($sheet, int $headerRow): array
    {
        $rows = [];
        $maxRow = $sheet->getHighestDataRow();
        for ($r = $headerRow + 1; $r <= $maxRow; $r++) {
            $empCode = trim((string) $this->cellValue($sheet, 1, $r));
            $name    = trim((string) $this->cellValue($sheet, 2, $r));
            if ($empCode === '' || $name === '' || !preg_match('/^ID-\d+/i', $empCode)) {
                continue;
            }
            $row = [];
            foreach (self::COLUMNS as $col => $field) {
                $row[$field] = $this->coerce($field, $this->cellValue($sheet, $col, $r));
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function cellValue($sheet, int $col, int $row)
    {
        $cell = $sheet->getCell([$col, $row]);
        $raw = $cell->getValue();
        if (is_string($raw) && str_starts_with($raw, '=')) {
            try {
                return $cell->getCalculatedValue();
            } catch (Throwable $e) {
                return $cell->getOldCalculatedValue();
            }
        }
        return $raw;
    }

    private function coerce(string $field, $value)
    {
        if ($value === null || $value === '') {
            return in_array($field, self::NUMERIC_FIELDS, true) || in_array($field, self::DATE_FIELDS, true) ? null : '';
        }
        if (in_array($field, self::DATE_FIELDS, true)) {
            try {
                if (is_numeric($value)) {
                    return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
                }
                return Carbon::parse((string) $value)->toDateString();
            } catch (Throwable $e) {
                return null;
            }
        }
        if (in_array($field, self::NUMERIC_FIELDS, true)) {
            return is_numeric($value) ? round((float) $value, 2) : null;
        }
        return trim((string) $value);
    }

    private function lookupCountryId(string $code, string $name, OdooService $odoo): ?int
    {
        $cacheKey = mb_strtolower($code . '|' . $name);
        if (array_key_exists($cacheKey, $this->countryCache)) {
            return $this->countryCache[$cacheKey];
        }
        $code = strtoupper(trim($code));
        $aliases = [
            'SD' => 'SD', 'KSA' => 'SA', 'SA' => 'SA', 'PK' => 'PK', 'BD' => 'BD',
            'IN' => 'IN', 'JOR' => 'JO', 'JO' => 'JO', 'EG' => 'EG', 'YE' => 'YE',
            'SY' => 'SY', 'LB' => 'LB', 'PH' => 'PH',
        ];
        $iso = $aliases[$code] ?? (strlen($code) === 2 ? $code : null);

        $id = null;
        try {
            if ($iso) {
                $ids = $odoo->search('res.country', [['code', '=', $iso]], ['limit' => 1]);
                $id = $ids[0] ?? null;
            }
            if (!$id && $name !== '') {
                $ids = $odoo->search('res.country', [['name', 'ilike', $name]], ['limit' => 1]);
                $id = $ids[0] ?? null;
            }
        } catch (Throwable $e) {
            $id = null;
        }
        $this->countryCache[$cacheKey] = $id;
        return $id;
    }

    private function mapFamilyStatusToOdoo(string $code): ?string
    {
        return match (mb_strtoupper(trim($code))) {
            'S' => 'single',
            'M' => 'married',
            'W' => 'widower',
            'D' => 'divorced',
            'C' => 'cohabitant',
            default => null,
        };
    }
}
