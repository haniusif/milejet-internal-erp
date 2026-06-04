<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncOdooCommand extends Command
{
    protected $signature = 'odoo:sync {model? : أي موديل (work_locations|departments|employees|leaves|attendances|leave_types|contracts|payslips|all)}';
    protected $description = 'مزامنة البيانات من Odoo إلى قاعدة بيانات Laravel المحلية';

    public function handle(SyncService $sync): int
    {
        $model = $this->argument('model') ?? 'all';

        $this->info("🔄 بدء المزامنة: {$model}");
        $this->newLine();

        $methods = match ($model) {
            'work_locations' => ['syncWorkLocations'],
            'departments' => ['syncDepartments'],
            'employees'   => ['syncEmployees'],
            'leave_types' => ['syncLeaveTypes'],
            'leaves'      => ['syncLeaves'],
            'attendances' => ['syncAttendances'],
            'contracts'   => ['syncContracts'],
            'payslips'    => ['syncPayslips'],
            'all'         => ['syncWorkLocations', 'syncDepartments', 'syncEmployees', 'syncLeaveTypes', 'syncLeaves', 'syncAttendances', 'syncContracts', 'syncPayslips'],
            default       => null,
        };

        if (!$methods) {
            $this->error("موديل غير معروف: {$model}");
            return Command::FAILURE;
        }

        foreach ($methods as $method) {
            $name = str_replace('sync', '', $method);
            $this->line("• مزامنة {$name}...");

            $log = $sync->{$method}();

            if ($log->status === 'success') {
                $this->info("  ✓ {$log->records_synced} سجل");
            } else {
                $this->error("  ✗ فشلت: {$log->error_message}");
            }
        }

        $this->newLine();
        $this->info('✅ تمت المزامنة');
        return Command::SUCCESS;
    }
}
