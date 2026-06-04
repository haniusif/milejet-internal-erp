<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\SyncLog;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Throwable;

/**
 * مزامنة البيانات من Odoo إلى قاعدة بيانات Laravel المحلية
 */
class SyncService
{
    public function __construct(protected OdooService $odoo) {}

    public function syncAll(): array
    {
        return [
            'work_locations' => $this->syncWorkLocations(),
            'departments' => $this->syncDepartments(),
            'employees'   => $this->syncEmployees(),
            'leave_types' => $this->syncLeaveTypes(),
            'leaves'      => $this->syncLeaves(),
            'attendances' => $this->syncAttendances(),
            'contracts'   => $this->syncContracts(),
            'payslips'    => $this->syncPayslips(),
        ];
    }

    public function syncContracts(): SyncLog
    {
        return $this->runSync('hr.contract', function () {
            $rows = $this->odoo->searchRead(
                'hr.contract', [],
                ['id', 'name', 'employee_id', 'wage', 'date_start', 'date_end', 'state', 'struct_id'],
                0, 0, 'id asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                Contract::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'             => $row['name'],
                        'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                        'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                        'wage'             => $row['wage'] ?? 0,
                        'date_start'       => $this->parseOdooDate($row['date_start']),
                        'date_end'         => $this->parseOdooDate($row['date_end']),
                        'state'            => $row['state'] ?? 'draft',
                        'odoo_struct_id'   => OdooService::many2oneId($row['struct_id']),
                        'struct_name'      => OdooService::many2oneName($row['struct_id']),
                        'synced_at'        => now(),
                    ]
                );
                $count++;
            }
            return $count;
        });
    }

    public function syncPayslips(): SyncLog
    {
        return $this->runSync('hr.payslip', function () {
            $rows = $this->odoo->searchRead(
                'hr.payslip', [],
                ['id', 'number', 'employee_id', 'contract_id', 'date_from', 'date_to', 'state', 'line_ids'],
                500, 0, 'id desc'
            );

            $allLineIds = [];
            foreach ($rows as $r) $allLineIds = array_merge($allLineIds, $r['line_ids'] ?? []);
            $linesByPayslip = [];
            if ($allLineIds) {
                $lines = $this->odoo->read('hr.payslip.line', array_values(array_unique($allLineIds)),
                    ['id', 'slip_id', 'code', 'name', 'category_id', 'total', 'sequence']);
                foreach ($lines as $line) {
                    $slipId = OdooService::many2oneId($line['slip_id']);
                    if ($slipId) $linesByPayslip[$slipId][] = $line;
                }
            }

            $count = 0;
            foreach ($rows as $row) {
                $this->writePayslip($row, $linesByPayslip[$row['id']] ?? []);
                $count++;
            }
            return $count;
        });
    }

    public function refreshPayslip(int $odooId): ?Payslip
    {
        try {
            $rows = $this->odoo->searchRead(
                'hr.payslip', [['id', '=', $odooId]],
                ['id', 'number', 'employee_id', 'contract_id', 'date_from', 'date_to', 'state', 'line_ids'],
                1
            );
            if (empty($rows)) return null;
            $row = $rows[0];

            $lines = $row['line_ids']
                ? $this->odoo->read('hr.payslip.line', $row['line_ids'],
                    ['id', 'slip_id', 'code', 'name', 'category_id', 'total', 'sequence'])
                : [];

            return $this->writePayslip($row, $lines);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Shared payslip writer: stores the payslip row + replaces its lines + rolls up category totals.
     */
    protected function writePayslip(array $row, array $lines): Payslip
    {
        $totals = ['BASIC' => 0, 'ALW' => 0, 'GROSS' => 0, 'DED' => 0, 'NET' => 0];
        foreach ($lines as $line) {
            $code = $this->categoryCode(OdooService::many2oneName($line['category_id']));
            if ($code) $totals[$code] += (float) $line['total'];
        }

        $payslip = Payslip::updateOrCreate(
            ['odoo_id' => $row['id']],
            [
                'number'           => $row['number'] ?: null,
                'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                'odoo_contract_id' => OdooService::many2oneId($row['contract_id']),
                'date_from'        => $this->parseOdooDate($row['date_from']),
                'date_to'          => $this->parseOdooDate($row['date_to']),
                'state'            => $row['state'] ?? 'draft',
                'basic_total'      => $totals['BASIC'],
                'allowance_total'  => $totals['ALW'],
                'gross_total'      => $totals['GROSS'],
                'deduction_total'  => $totals['DED'],
                'net_total'        => $totals['NET'],
                'synced_at'        => now(),
            ]
        );

        PayslipLine::where('odoo_payslip_id', $row['id'])->delete();
        foreach ($lines as $line) {
            $catName = OdooService::many2oneName($line['category_id']);
            PayslipLine::create([
                'odoo_id'         => $line['id'],
                'odoo_payslip_id' => $row['id'],
                'code'            => $line['code'],
                'name'            => $line['name'],
                'category_code'   => $this->categoryCode($catName),
                'category_name'   => $catName,
                'total'           => $line['total'] ?? 0,
                'sequence'        => $line['sequence'] ?? 0,
            ]);
        }

        return $payslip;
    }

    protected function categoryCode(?string $name): ?string
    {
        return match (strtolower($name ?? '')) {
            'basic'     => 'BASIC',
            'allowance' => 'ALW',
            'gross'     => 'GROSS',
            'deduction' => 'DED',
            'net'       => 'NET',
            default     => null,
        };
    }

    public function refreshContract(int $odooId): ?Contract
    {
        try {
            $rows = $this->odoo->read('hr.contract', [$odooId],
                ['id', 'name', 'employee_id', 'wage', 'date_start', 'date_end', 'state', 'struct_id']);
            if (empty($rows)) return null;
            $row = $rows[0];

            return Contract::updateOrCreate(
                ['odoo_id' => $row['id']],
                [
                    'name'             => $row['name'],
                    'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                    'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                    'wage'             => $row['wage'] ?? 0,
                    'date_start'       => $this->parseOdooDate($row['date_start']),
                    'date_end'         => $this->parseOdooDate($row['date_end']),
                    'state'            => $row['state'] ?? 'draft',
                    'odoo_struct_id'   => OdooService::many2oneId($row['struct_id']),
                    'struct_name'      => OdooService::many2oneName($row['struct_id']),
                    'synced_at'        => now(),
                ]
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function syncWorkLocations(): SyncLog
    {
        return $this->runSync('hr.work.location', function () {
            // active_test=false so archived locations sync too (kept locally
            // with active=false instead of silently disappearing).
            $rows = $this->odoo->searchRead(
                'hr.work.location', [['active', 'in', [true, false]]],
                ['id', 'name', 'location_type', 'address_id', 'active'],
                0, 0, 'id asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                $this->writeWorkLocation($row);
                $count++;
            }
            return $count;
        });
    }

    public function refreshWorkLocation(int $odooId): ?WorkLocation
    {
        try {
            $rows = $this->odoo->read('hr.work.location', [$odooId],
                ['id', 'name', 'location_type', 'address_id', 'active']);
            if (empty($rows)) return null;

            return $this->writeWorkLocation($rows[0]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Writes only the Odoo-sourced columns. latitude/longitude/geofence_radius
     * are managed in Laravel and must survive every sync.
     */
    protected function writeWorkLocation(array $row): WorkLocation
    {
        return WorkLocation::updateOrCreate(
            ['odoo_id' => $row['id']],
            [
                'name'          => $row['name'],
                'location_type' => is_string($row['location_type'] ?? null) ? $row['location_type'] : null,
                'address_name'  => OdooService::many2oneName($row['address_id']),
                'active'        => (bool) ($row['active'] ?? true),
                'synced_at'     => now(),
            ]
        );
    }

    public function syncDepartments(): SyncLog
    {
        return $this->runSync('hr.department', function () {
            $rows = $this->odoo->searchRead(
                'hr.department', [],
                ['id', 'name', 'parent_id', 'manager_id', 'total_employee'],
                0, 0, 'id asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                Department::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'             => $row['name'],
                        'odoo_parent_id'   => OdooService::many2oneId($row['parent_id']),
                        'parent_name'      => OdooService::many2oneName($row['parent_id']),
                        'odoo_manager_id'  => OdooService::many2oneId($row['manager_id']),
                        'manager_name'     => OdooService::many2oneName($row['manager_id']),
                        'total_employee'   => $row['total_employee'] ?? 0,
                        'synced_at'        => now(),
                    ]
                );
                $count++;
            }
            return $count;
        });
    }

    public function syncEmployees(): SyncLog
    {
        return $this->runSync('hr.employee', function () {
            $rows = $this->odoo->searchRead(
                'hr.employee', [],
                ['id', 'name', 'job_title', 'work_email', 'work_phone',
                 'mobile_phone', 'department_id', 'parent_id', 'work_location_id',
                 'active', 'image_128'],
                0, 0, 'id asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                Employee::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'               => $row['name'],
                        'job_title'          => $row['job_title'] ?: null,
                        'work_email'         => $row['work_email'] ?: null,
                        'work_phone'         => $row['work_phone'] ?: null,
                        'mobile_phone'       => $row['mobile_phone'] ?? null,
                        'odoo_department_id' => OdooService::many2oneId($row['department_id']),
                        'department_name'    => OdooService::many2oneName($row['department_id']),
                        'odoo_parent_id'     => OdooService::many2oneId($row['parent_id']),
                        'parent_name'        => OdooService::many2oneName($row['parent_id']),
                        'odoo_work_location_id' => OdooService::many2oneId($row['work_location_id']),
                        'work_location_name'    => OdooService::many2oneName($row['work_location_id']),
                        'active'             => (bool) ($row['active'] ?? true),
                        'image_small'        => is_string($row['image_128'] ?? null) ? $row['image_128'] : null,
                        'synced_at'          => now(),
                    ]
                );
                $count++;
            }
            return $count;
        });
    }

    public function syncLeaveTypes(): SyncLog
    {
        return $this->runSync('hr.leave.type', function () {
            $rows = $this->odoo->searchRead(
                'hr.leave.type', [], ['id', 'name'], 0, 0, 'id asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                LeaveType::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    ['name' => $row['name'], 'synced_at' => now()]
                );
                $count++;
            }
            return $count;
        });
    }

    public function syncLeaves(): SyncLog
    {
        return $this->runSync('hr.leave', function () {
            $rows = $this->odoo->searchRead(
                'hr.leave', [],
                ['id', 'employee_id', 'holiday_status_id', 'date_from',
                 'date_to', 'number_of_days', 'state', 'name'],
                500, 0, 'id desc' // آخر 500 طلب
            );

            $count = 0;
            foreach ($rows as $row) {
                Leave::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'odoo_employee_id'   => OdooService::many2oneId($row['employee_id']) ?? 0,
                        'employee_name'      => OdooService::many2oneName($row['employee_id']) ?? '—',
                        'odoo_leave_type_id' => OdooService::many2oneId($row['holiday_status_id']),
                        'leave_type_name'    => OdooService::many2oneName($row['holiday_status_id']),
                        'date_from'          => $this->parseOdooDate($row['date_from']),
                        'date_to'            => $this->parseOdooDate($row['date_to']),
                        'number_of_days'     => $row['number_of_days'] ?? 0,
                        'state'              => $row['state'] ?? 'draft',
                        'description'        => $row['name'] ?: null,
                        'synced_at'          => now(),
                    ]
                );
                $count++;
            }
            return $count;
        });
    }

    public function syncAttendances(): SyncLog
    {
        return $this->runSync('hr.attendance', function () {
            // آخر 1000 سجل حضور
            $rows = $this->odoo->searchRead(
                'hr.attendance', [],
                ['id', 'employee_id', 'check_in', 'check_out', 'worked_hours'],
                1000, 0, 'id desc'
            );

            $count = 0;
            foreach ($rows as $row) {
                Attendance::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                        'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                        'check_in'         => $this->parseOdooDate($row['check_in']),
                        'check_out'        => $this->parseOdooDate($row['check_out']),
                        'worked_hours'     => $row['worked_hours'] ?? 0,
                        'synced_at'        => now(),
                    ]
                );
                $count++;
            }
            return $count;
        });
    }

    /**
     * Wrapper لكل عملية sync: يسجل في sync_logs ويتعامل مع الأخطاء
     */
    protected function runSync(string $model, callable $callback): SyncLog
    {
        $log = SyncLog::create([
            'model'      => $model,
            'started_at' => now(),
            'status'     => 'running',
        ]);

        try {
            $count = $callback();
            $log->update([
                'records_synced' => $count,
                'status'         => 'success',
                'completed_at'   => now(),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }

        return $log->fresh();
    }

    protected function parseOdooDate(mixed $value): ?Carbon
    {
        if (!$value || $value === false) return null;
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * تحديث سجل واحد محلياً بعد تعديله في Odoo (بدون sync كامل)
     */
    public function refreshEmployee(int $odooId): ?Employee
    {
        try {
            $rows = $this->odoo->read('hr.employee', [$odooId],
                ['id', 'name', 'job_title', 'work_email', 'work_phone',
                 'mobile_phone', 'department_id', 'parent_id', 'work_location_id',
                 'active', 'image_128']);
            if (empty($rows)) return null;
            $row = $rows[0];

            return Employee::updateOrCreate(
                ['odoo_id' => $row['id']],
                [
                    'name'               => $row['name'],
                    'job_title'          => $row['job_title'] ?: null,
                    'work_email'         => $row['work_email'] ?: null,
                    'work_phone'         => $row['work_phone'] ?: null,
                    'mobile_phone'       => $row['mobile_phone'] ?? null,
                    'odoo_department_id' => OdooService::many2oneId($row['department_id']),
                    'department_name'    => OdooService::many2oneName($row['department_id']),
                    'odoo_parent_id'     => OdooService::many2oneId($row['parent_id']),
                    'parent_name'        => OdooService::many2oneName($row['parent_id']),
                    'odoo_work_location_id' => OdooService::many2oneId($row['work_location_id']),
                    'work_location_name'    => OdooService::many2oneName($row['work_location_id']),
                    'active'             => (bool) ($row['active'] ?? true),
                    'image_small'        => is_string($row['image_128'] ?? null) ? $row['image_128'] : null,
                    'synced_at'          => now(),
                ]
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function refreshDepartment(int $odooId): ?Department
    {
        try {
            $rows = $this->odoo->read('hr.department', [$odooId],
                ['id', 'name', 'parent_id', 'manager_id', 'total_employee']);
            if (empty($rows)) return null;
            $row = $rows[0];

            return Department::updateOrCreate(
                ['odoo_id' => $row['id']],
                [
                    'name'             => $row['name'],
                    'odoo_parent_id'   => OdooService::many2oneId($row['parent_id']),
                    'parent_name'      => OdooService::many2oneName($row['parent_id']),
                    'odoo_manager_id'  => OdooService::many2oneId($row['manager_id']),
                    'manager_name'     => OdooService::many2oneName($row['manager_id']),
                    'total_employee'   => $row['total_employee'] ?? 0,
                    'synced_at'        => now(),
                ]
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function refreshLeave(int $odooId): ?Leave
    {
        try {
            $rows = $this->odoo->read('hr.leave', [$odooId],
                ['id', 'employee_id', 'holiday_status_id', 'date_from',
                 'date_to', 'number_of_days', 'state', 'name']);
            if (empty($rows)) return null;
            $row = $rows[0];

            return Leave::updateOrCreate(
                ['odoo_id' => $row['id']],
                [
                    'odoo_employee_id'   => OdooService::many2oneId($row['employee_id']) ?? 0,
                    'employee_name'      => OdooService::many2oneName($row['employee_id']) ?? '—',
                    'odoo_leave_type_id' => OdooService::many2oneId($row['holiday_status_id']),
                    'leave_type_name'    => OdooService::many2oneName($row['holiday_status_id']),
                    'date_from'          => $this->parseOdooDate($row['date_from']),
                    'date_to'            => $this->parseOdooDate($row['date_to']),
                    'number_of_days'     => $row['number_of_days'] ?? 0,
                    'state'              => $row['state'] ?? 'draft',
                    'description'        => $row['name'] ?: null,
                    'synced_at'          => now(),
                ]
            );
        } catch (Throwable) {
            return null;
        }
    }
}
