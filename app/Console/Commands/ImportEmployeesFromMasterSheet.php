<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\OdooService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;

class ImportEmployeesFromMasterSheet extends Command
{
    protected $signature = 'employees:import-master
        {file=public/Master Sheet - HR-2026- DEVOLEPER.xlsx : Path to xlsx (relative to project root or absolute)}
        {--sheet=Employee_Master1 : Sheet name}
        {--header-row=7 : 1-based header row}
        {--dry-run : Parse only, do not write to DB or Odoo}
        {--no-odoo : Update local DB but skip Odoo writes}';

    protected $description = 'Import/update employees from the Master Sheet xlsx — local DB + push safe fields to Odoo';

    private const COLUMNS = [
        1 => 'emp_code',
        2 => 'name',
        3 => 'job_title',
        4 => 'parent_name',
        5 => 'parent_role',
        6 => 'date_of_joining',
        7 => 'contract_end_date',
        8 => 'contract_status',
        9 => 'service_years',
        10 => 'birthday',
        11 => 'age',
        12 => 'family_status',
        13 => 'cchi_card_type',
        14 => 'total_salary',
        15 => 'basic_salary',
        16 => 'nationality_code',
        17 => 'nationality',
        18 => 'region',
        19 => 'passport_id',
        20 => 'iqama_id',
        21 => 'status_label',
        22 => 'contract_type',
        23 => 'ot_allowance',
        24 => 'loan_balance',
        25 => 'allowance_house',
        26 => 'allowance_rent',
        27 => 'allowance_transport',
        28 => 'allowance_car',
        29 => 'allowance_special',
        30 => 'allowance_project',
        31 => 'allowance_food',
        32 => 'allowance_other',
        33 => 'alt_ticket',
        34 => 'bonus_eligibility_months',
        35 => 'bonus_pm',
        36 => 'gosi_pm',
        37 => 'indemnity_pm',
        38 => 'leave_accrual_pm',
        39 => 'med_insurance_pm',
        40 => 'pa_insurance_pm',
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

    public function handle(OdooService $odoo): int
    {
        $file = $this->argument('file');
        if (!str_starts_with($file, '/')) {
            $file = base_path($file);
        }
        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $sheetName = $this->option('sheet');
        $headerRow = (int) $this->option('header-row');
        $dryRun    = (bool) $this->option('dry-run');
        $noOdoo    = (bool) $this->option('no-odoo');

        $this->info("Reading: {$file}");
        $this->line("Sheet: {$sheetName} | header row: {$headerRow}" . ($dryRun ? ' | DRY RUN' : ''));

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly([$sheetName]);
        $book = $reader->load($file);
        $sheet = $book->getSheetByName($sheetName);
        if (!$sheet) {
            $this->error("Sheet not found: {$sheetName}");
            return self::FAILURE;
        }

        $rows = $this->extractRows($sheet, $headerRow);
        $this->info("Parsed " . count($rows) . " employee rows.");

        $matched = 0;
        $unmatched = [];
        $localUpdated = 0;
        $odooUpdated = 0;
        $odooErrors = [];

        $existing = Employee::all()->keyBy(fn ($e) => mb_strtolower(trim($e->name)));

        foreach ($rows as $row) {
            $key = mb_strtolower(trim($row['name']));
            $emp = $existing->get($key);
            if (!$emp) {
                $unmatched[] = $row['emp_code'] . ' | ' . $row['name'];
                continue;
            }
            $matched++;

            $localPayload = [
                'emp_code'               => $row['emp_code'] ?: null,
                'job_title'              => $row['job_title'] ?: $emp->job_title,
                'date_of_joining'        => $row['date_of_joining'],
                'contract_end_date'      => $row['contract_end_date'],
                'contract_status'        => $row['contract_status'] ?: null,
                'birthday'               => $row['birthday'],
                'family_status'          => $row['family_status'] ?: null,
                'cchi_card_type'         => $row['cchi_card_type'] ?: null,
                'nationality_code'       => $row['nationality_code'] ?: null,
                'nationality'            => $row['nationality'] ?: null,
                'region'                 => $row['region'] ?: null,
                'passport_id'            => $row['passport_id'] ?: null,
                'iqama_id'               => $row['iqama_id'] ?: null,
                'status_label'           => $row['status_label'] ?: null,
                'contract_type'          => $row['contract_type'] ?: null,
                'total_salary'           => $row['total_salary'],
                'basic_salary'           => $row['basic_salary'],
                'allowance_house'        => $row['allowance_house'],
                'allowance_rent'         => $row['allowance_rent'],
                'allowance_transport'    => $row['allowance_transport'],
                'allowance_car'          => $row['allowance_car'],
                'allowance_special'      => $row['allowance_special'],
                'allowance_project'      => $row['allowance_project'],
                'allowance_food'         => $row['allowance_food'],
                'allowance_other'        => $row['allowance_other'],
                'ot_allowance'           => $row['ot_allowance'],
                'loan_balance'           => $row['loan_balance'],
                'alt_ticket'             => $row['alt_ticket'],
                'bonus_eligibility_months' => $row['bonus_eligibility_months'],
                'bonus_pm'               => $row['bonus_pm'],
                'gosi_pm'                => $row['gosi_pm'],
                'indemnity_pm'           => $row['indemnity_pm'],
                'leave_accrual_pm'       => $row['leave_accrual_pm'],
                'med_insurance_pm'       => $row['med_insurance_pm'],
                'pa_insurance_pm'        => $row['pa_insurance_pm'],
                'active'                 => $this->resolveActive($row['status_label'], $emp->active),
                'master_imported_at'     => now(),
            ];

            if (!$dryRun) {
                $emp->fill($localPayload)->save();
                $localUpdated++;
            }

            if ($dryRun || $noOdoo) {
                continue;
            }

            try {
                $odooPayload = $this->buildOdooPayload($row, $odoo);
                if (!empty($odooPayload)) {
                    $odoo->write('hr.employee', [$emp->odoo_id], $odooPayload);
                    $odooUpdated++;
                }
            } catch (Throwable $e) {
                $odooErrors[] = "{$emp->odoo_id} ({$emp->name}): " . $e->getMessage();
            }
        }

        $this->newLine();
        $this->info("Local matched: {$matched} / " . count($rows));
        if (!$dryRun) {
            $this->info("Local updated: {$localUpdated}");
        }
        if (!$noOdoo && !$dryRun) {
            $this->info("Odoo updated:  {$odooUpdated}");
        }

        if ($unmatched) {
            $this->newLine();
            $this->warn("Unmatched (" . count($unmatched) . "):");
            foreach ($unmatched as $u) {
                $this->line('  - ' . $u);
            }
        }

        if ($odooErrors) {
            $this->newLine();
            $this->warn("Odoo write errors (" . count($odooErrors) . "):");
            foreach ($odooErrors as $err) {
                $this->line('  - ' . $err);
            }
        }

        return self::SUCCESS;
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
                $raw = $this->cellValue($sheet, $col, $r);
                $row[$field] = $this->coerce($field, $raw);
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
            return in_array($field, self::NUMERIC_FIELDS, true) || in_array($field, self::DATE_FIELDS, true)
                ? null
                : '';
        }
        if (in_array($field, self::DATE_FIELDS, true)) {
            try {
                if (is_numeric($value)) {
                    $dt = ExcelDate::excelToDateTimeObject((float) $value);
                    return Carbon::instance($dt)->toDateString();
                }
                return Carbon::parse((string) $value)->toDateString();
            } catch (Throwable $e) {
                return null;
            }
        }
        if (in_array($field, self::NUMERIC_FIELDS, true)) {
            $num = is_numeric($value) ? (float) $value : null;
            return $num === null ? null : round($num, 2);
        }
        return trim((string) $value);
    }

    private function resolveActive(string $status, bool $current): bool
    {
        $s = mb_strtolower(trim($status));
        return match (true) {
            in_array($s, ['active', 'نشط', 'مستمر'], true)       => true,
            in_array($s, ['withdrawn', 'inactive', 'terminated',
                          'resigned', 'إستقالة', 'منسحب'], true) => false,
            default                                                => $current,
        };
    }

    private function buildOdooPayload(array $row, OdooService $odoo): array
    {
        $payload = [];
        if (!empty($row['job_title'])) {
            $payload['job_title'] = $row['job_title'];
        }
        if (!empty($row['birthday'])) {
            $payload['birthday'] = $row['birthday'];
        }
        if (!empty($row['iqama_id'])) {
            $payload['identification_id'] = $row['iqama_id'];
        }
        if (!empty($row['passport_id'])) {
            $payload['passport_id'] = $row['passport_id'];
        }
        if (!empty($row['nationality_code'])) {
            $countryId = $this->lookupCountryId($row['nationality_code'], $row['nationality'] ?? '', $odoo);
            if ($countryId) {
                $payload['country_id'] = $countryId;
            }
        }
        if (!empty($row['family_status'])) {
            $marital = $this->mapFamilyStatusToOdoo($row['family_status']);
            if ($marital) {
                $payload['marital'] = $marital;
            }
        }
        return $payload;
    }

    private array $countryCache = [];

    private function lookupCountryId(string $code, string $name, OdooService $odoo): ?int
    {
        $cacheKey = mb_strtolower($code . '|' . $name);
        if (array_key_exists($cacheKey, $this->countryCache)) {
            return $this->countryCache[$cacheKey];
        }
        $code = strtoupper(trim($code));
        $aliases = [
            'SD'    => 'SD',
            'KSA'   => 'SA',
            'SA'    => 'SA',
            'PK'    => 'PK',
            'BD'    => 'BD',
            'IN'    => 'IN',
            'JOR'   => 'JO',
            'JO'    => 'JO',
            'EG'    => 'EG',
            'YE'    => 'YE',
            'SY'    => 'SY',
            'LB'    => 'LB',
            'PH'    => 'PH',
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
            'S'         => 'single',
            'M'         => 'married',
            'W'         => 'widower',
            'D'         => 'divorced',
            'C'         => 'cohabitant',
            default     => null,
        };
    }
}
