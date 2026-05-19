<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Console\Command;
use Throwable;

class CreateContractsFromMaster extends Command
{
    protected $signature = 'employees:create-contracts
        {--struct-id=1 : Odoo hr.payroll.structure id (default SA-STD)}
        {--dry-run : Show what would be created without writing}';

    protected $description = 'Create hr.contract in Odoo for each master-imported employee using local salary/DOJ/contract_end data';

    public function handle(OdooService $odoo, SyncService $sync): int
    {
        $structId = (int) $this->option('struct-id');
        $dryRun = (bool) $this->option('dry-run');

        $employees = Employee::whereNotNull('master_imported_at')
            ->whereNotNull('total_salary')
            ->orderBy('emp_code')
            ->get();

        $this->info("Employees with master data: " . $employees->count());

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($employees as $emp) {
            if (empty($emp->date_of_joining)) {
                $skipped++;
                $this->warn("Skip {$emp->emp_code} {$emp->name}: no date_of_joining");
                continue;
            }
            if ((float) $emp->total_salary <= 0) {
                $skipped++;
                $this->warn("Skip {$emp->emp_code} {$emp->name}: total_salary <= 0");
                continue;
            }

            $state = $this->mapState($emp->contract_status, $emp->contract_end_date?->toDateString());

            $payload = [
                'name'         => 'Contract - ' . $emp->name,
                'employee_id'  => $emp->odoo_id,
                'wage'         => round((float) $emp->total_salary, 2),
                'date_start'   => $emp->date_of_joining->toDateString(),
                'state'        => $state,
                'struct_id'    => $structId,
            ];
            if ($emp->contract_end_date) {
                $payload['date_end'] = $emp->contract_end_date->toDateString();
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '  %s | %s | wage=%.2f | %s → %s | state=%s',
                    $emp->emp_code,
                    $emp->name,
                    $payload['wage'],
                    $payload['date_start'],
                    $payload['date_end'] ?? '—',
                    $state,
                ));
                $created++;
                continue;
            }

            try {
                $id = $odoo->create('hr.contract', $payload);
                $created++;
                $this->line("  ✓ {$emp->emp_code} {$emp->name} → contract id={$id}");
            } catch (Throwable $e) {
                $errors[] = "{$emp->emp_code} {$emp->name}: " . $e->getMessage();
                $this->warn("  ✗ {$emp->emp_code} {$emp->name}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info(($dryRun ? "Would create: " : "Created: ") . $created);
        $this->info("Skipped: {$skipped}");
        if ($errors) {
            $this->warn("Errors (" . count($errors) . "):");
            foreach ($errors as $e) {
                $this->line('  - ' . $e);
            }
        }

        if (!$dryRun && $created > 0) {
            $this->newLine();
            $this->info("Re-syncing local contracts cache...");
            $sync->syncContracts();
            $this->info("Done.");
        }

        return self::SUCCESS;
    }

    private function mapState(?string $sheetStatus, ?string $dateEnd): string
    {
        $s = mb_strtolower(trim((string) $sheetStatus));
        if (in_array($s, ['expired'], true)) {
            return 'close';
        }
        if (in_array($s, ['active', 'expiring soon'], true)) {
            return 'open';
        }
        if ($dateEnd && strtotime($dateEnd) < time()) {
            return 'close';
        }
        return 'open';
    }
}
