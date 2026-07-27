<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CourierDaily;
use App\Models\Country;
use App\Models\CrmCustomer;
use App\Models\CrmLead;
use App\Models\CrmLostReason;
use App\Models\CrmStage;
use App\Models\CrmTag;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FinanceInvoice;
use App\Models\FleetServiceLog;
use App\Models\FleetAccident;
use App\Models\FleetFuelLog;
use App\Models\FleetInspection;
use App\Models\FleetInspectionItem;
use App\Models\FleetInspectionLine;
use App\Models\FleetInspectionTemplate;
use App\Models\FleetServiceType;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleCategory;
use App\Models\FleetVehicleModel;
use App\Models\FleetVehicleUsage;
use App\Models\FleetVehicleState;
use App\Models\JobPosition;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\LoanLine;
use App\Models\EmployeeDocument;
use App\Models\HrRequest;
use App\Models\Payslip;
use App\Models\PayslipLine;
use App\Models\PayslipPayment;
use App\Models\SalaryAdjustment;
use App\Models\ServiceEnd;
use App\Models\SickLeave;
use App\Models\Warning;
use App\Models\RecruitmentStage;
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
            'countries'   => $this->syncCountries(),
            'companies'   => $this->syncCompanies(),
            'work_locations' => $this->syncWorkLocations(),
            'departments' => $this->syncDepartments(),
            'employees'   => $this->syncEmployees(),
            'leave_types' => $this->syncLeaveTypes(),
            'leaves'      => $this->syncLeaves(),
            'attendances' => $this->syncAttendances(),
            'contracts'   => $this->syncContracts(),
            'loans'       => $this->syncLoans(),
            'salary_adjustments' => $this->syncSalaryAdjustments(),
            'employee_documents' => $this->syncEmployeeDocuments(),
            'hr_requests' => $this->syncHrRequests(),
            'warnings'    => $this->syncWarnings(),
            'sick_leaves' => $this->syncSickLeaves(),
            'service_ends' => $this->syncServiceEnds(),
            'payslips'    => $this->syncPayslips(),
            'payslip_payments' => $this->syncPayslipPayments(),
            'recruitment' => $this->syncRecruitment(),
            'crm'         => $this->syncCrm(),
            'fleet'       => $this->syncFleet(),
            'finance'     => $this->syncFinance(),
        ];
    }

    /** Customer invoices + vendor bills (account.move). Last 1000 of each. */
    public function syncFinance(): SyncLog
    {
        return $this->runSync('finance', function () {
            $count = 0;

            $rows = $this->odoo->searchRead(
                'account.move',
                [['move_type', 'in', ['out_invoice', 'in_invoice', 'out_refund', 'in_refund']]],
                ['id', 'move_type', 'name', 'ref', 'partner_id', 'invoice_date', 'invoice_date_due',
                 'amount_total', 'amount_residual', 'currency_id', 'state', 'payment_state', 'journal_id'],
                1000, 0, 'id desc'
            );
            foreach ($rows as $row) {
                FinanceInvoice::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'move_type'        => $row['move_type'],
                        'name'             => $row['name'] ?: '/',
                        'ref'              => $row['ref'] ?: null,
                        'odoo_partner_id'  => OdooService::many2oneId($row['partner_id']),
                        'partner_name'     => OdooService::many2oneName($row['partner_id']),
                        'invoice_date'     => $this->parseOdooDate($row['invoice_date']),
                        'invoice_date_due' => $this->parseOdooDate($row['invoice_date_due']),
                        'amount_total'     => $row['amount_total'] ?: 0,
                        'amount_residual'  => $row['amount_residual'] ?: 0,
                        'currency'         => OdooService::many2oneName($row['currency_id']),
                        'state'            => $row['state'] ?: null,
                        'payment_state'    => $row['payment_state'] ?: null,
                        'journal_name'     => OdooService::many2oneName($row['journal_id']),
                        'synced_at'        => now(),
                    ]
                );
                $count++;
            }

            return $count;
        });
    }

    /** Fleet states + models + service types + vehicles + service logs. */
    public function syncFleet(): SyncLog
    {
        return $this->runSync('fleet', function () {
            $count = 0;

            $rows = $this->odoo->searchRead('fleet.vehicle.state', [], ['id', 'name', 'sequence'], 0, 0, 'sequence asc');
            foreach ($rows as $row) {
                FleetVehicleState::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    ['name' => $row['name'], 'sequence' => $row['sequence'] ?? 0, 'synced_at' => now()]
                );
                $count++;
            }

            $rows = $this->odoo->searchRead('fleet.vehicle.model', [], ['id', 'name', 'brand_id', 'vehicle_type'], 0, 0, 'name asc');
            foreach ($rows as $row) {
                FleetVehicleModel::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'         => $row['name'],
                        'brand_name'   => OdooService::many2oneName($row['brand_id']),
                        'vehicle_type' => $row['vehicle_type'] ?: null,
                        'synced_at'    => now(),
                    ]
                );
                $count++;
            }

            $rows = $this->odoo->searchRead('fleet.service.type', [], ['id', 'name'], 0, 0, 'name asc');
            foreach ($rows as $row) {
                FleetServiceType::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    ['name' => $row['name'], 'synced_at' => now()]
                );
                $count++;
            }

            // Vehicle categories (fleet_vehicle_category OCA addon).
            $rows = $this->odoo->searchRead('fleet.vehicle.category', [], ['id', 'name'], 0, 0, 'name asc');
            foreach ($rows as $row) {
                FleetVehicleCategory::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    ['name' => $row['name'], 'synced_at' => now()]
                );
                $count++;
            }

            // Inspection templates + items (fleet_vehicle_inspection[_template]).
            $rows = $this->odoo->searchRead('fleet.vehicle.inspection.template', [], ['id', 'name'], 0, 0, 'name asc');
            foreach ($rows as $row) {
                FleetInspectionTemplate::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    ['name' => $row['name'] ?: '—', 'synced_at' => now()]
                );
                $count++;
            }
            $rows = $this->odoo->searchRead('fleet.vehicle.inspection.item', [], ['id', 'name', 'instruction'], 0, 0, 'name asc');
            foreach ($rows as $row) {
                FleetInspectionItem::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    ['name' => $row['name'] ?: '—', 'instruction' => $row['instruction'] ?: null, 'synced_at' => now()]
                );
                $count++;
            }

            $rows = $this->odoo->searchRead(
                'fleet.vehicle', [['active', 'in', [true, false]]],
                ['id', 'name', 'model_id', 'license_plate', 'vin_sn', 'driver_id', 'state_id',
                 'odometer', 'odometer_unit', 'fuel_type', 'fuel_capacity', 'vehicle_category_id',
                 'model_year', 'color', 'seats', 'doors', 'acquisition_date', 'car_value', 'active', 'in_use',
                 'inspection_expiry', 'fuel_card_no'],
                0, 0, 'id asc'
            );
            foreach ($rows as $row) {
                FleetVehicle::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'                   => $row['name'] ?: '—',
                        'odoo_model_id'          => OdooService::many2oneId($row['model_id']),
                        'model_name'             => OdooService::many2oneName($row['model_id']),
                        'license_plate'          => $row['license_plate'] ?: null,
                        'vin_sn'                 => $row['vin_sn'] ?: null,
                        'odoo_driver_partner_id' => OdooService::many2oneId($row['driver_id']),
                        'driver_name'            => OdooService::many2oneName($row['driver_id']),
                        'odoo_state_id'          => OdooService::many2oneId($row['state_id']),
                        'state_name'             => OdooService::many2oneName($row['state_id']),
                        'odometer'               => $row['odometer'] ?? 0,
                        'odometer_unit'          => $row['odometer_unit'] ?: null,
                        'fuel_type'              => $row['fuel_type'] ?: null,
                        'fuel_capacity'          => $row['fuel_capacity'] ?: null,
                        'odoo_category_id'       => OdooService::many2oneId($row['vehicle_category_id']),
                        'category_name'          => OdooService::many2oneName($row['vehicle_category_id']),
                        'model_year'             => $row['model_year'] ?: null,
                        'color'                  => $row['color'] ?: null,
                        'seats'                  => $row['seats'] ?: null,
                        'doors'                  => $row['doors'] ?: null,
                        'acquisition_date'       => $this->parseOdooDate($row['acquisition_date']),
                        'car_value'              => $row['car_value'] ?: null,
                        'active'                 => (bool) ($row['active'] ?? true),
                        'in_use'                 => (bool) ($row['in_use'] ?? false),
                        'inspection_expiry'      => $this->parseOdooDate($row['inspection_expiry']),
                        'fuel_card_no'           => $row['fuel_card_no'] ?: null,
                        'synced_at'              => now(),
                    ]
                );
                $count++;
            }

            // Fuel logs (fleet_vehicle_log_fuel) + accidents (mj_fleet_ops).
            $count += $this->syncFleetOps();

            // Sub-service-type names by odoo id (fleet_vehicle_service_services).
            $typeNames = FleetServiceType::pluck('name', 'odoo_id');

            $rows = $this->odoo->searchRead(
                'fleet.vehicle.log.services', [],
                ['id', 'vehicle_id', 'description', 'service_type_id', 'date', 'amount', 'vendor_id', 'state', 'service_ids'],
                1000, 0, 'id desc'
            );
            foreach ($rows as $row) {
                $included = collect($row['service_ids'] ?? [])
                    ->map(fn ($id) => $typeNames[$id] ?? null)->filter()->implode(', ');
                FleetServiceLog::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'odoo_vehicle_id'   => OdooService::many2oneId($row['vehicle_id']) ?? 0,
                        'vehicle_name'      => OdooService::many2oneName($row['vehicle_id']),
                        'description'       => $row['description'] ?: null,
                        'service_type_name' => OdooService::many2oneName($row['service_type_id']),
                        'included_services' => $included ?: null,
                        'date'              => $this->parseOdooDate($row['date']),
                        'amount'            => $row['amount'] ?: null,
                        'vendor_name'       => OdooService::many2oneName($row['vendor_id']),
                        'state'             => $row['state'] ?: null,
                        'synced_at'         => now(),
                    ]
                );
                $count++;
            }

            // Vehicle inspections + their checklist lines (fleet_vehicle_inspection).
            $rows = $this->odoo->searchRead(
                'fleet.vehicle.inspection', [],
                ['id', 'name', 'vehicle_id', 'state', 'direction', 'date_inspected',
                 'odometer', 'odometer_unit', 'inspected_by', 'result', 'note', 'inspection_line_ids'],
                1000, 0, 'id desc'
            );
            $seenInspections = [];
            $allLineIds = [];
            foreach ($rows as $row) {
                $seenInspections[] = $row['id'];
                $allLineIds = array_merge($allLineIds, $row['inspection_line_ids'] ?? []);
                FleetInspection::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'odoo_vehicle_id'   => OdooService::many2oneId($row['vehicle_id']) ?? 0,
                        'vehicle_name'      => OdooService::many2oneName($row['vehicle_id']),
                        'name'              => $row['name'] ?: null,
                        'state'             => $row['state'] ?: 'draft',
                        'direction'         => $row['direction'] ?: null,
                        'date_inspected'    => $this->parseOdooDate($row['date_inspected']),
                        'odometer'          => $row['odometer'] ?: null,
                        'odometer_unit'     => $row['odometer_unit'] ?: null,
                        'inspected_by_name' => OdooService::many2oneName($row['inspected_by']),
                        'result'            => $row['result'] ?: null,
                        'note'              => is_string($row['note']) ? strip_tags($row['note']) : null,
                        'synced_at'         => now(),
                    ]
                );
                $count++;
            }
            FleetInspection::whereNotIn('odoo_id', $seenInspections)->delete();

            // Inspection checklist lines.
            $lineRows = $allLineIds
                ? $this->odoo->read('fleet.vehicle.inspection.line', array_values(array_unique($allLineIds)),
                    ['id', 'inspection_id', 'inspection_item_id', 'result', 'result_description', 'sequence'])
                : [];
            $seenLines = [];
            foreach ($lineRows as $line) {
                $seenLines[] = $line['id'];
                FleetInspectionLine::updateOrCreate(
                    ['odoo_id' => $line['id']],
                    [
                        'odoo_inspection_id' => OdooService::many2oneId($line['inspection_id']) ?? 0,
                        'odoo_item_id'       => OdooService::many2oneId($line['inspection_item_id']),
                        'item_name'          => OdooService::many2oneName($line['inspection_item_id']),
                        'result'             => $line['result'] ?: 'todo',
                        'result_description' => $line['result_description'] ?: null,
                        'sequence'           => $line['sequence'] ?? 10,
                        'synced_at'          => now(),
                    ]
                );
            }
            FleetInspectionLine::whereNotIn('odoo_id', $seenLines)->delete();

            // Vehicle usage / checkout log (fleet_vehicle_usage).
            $rows = $this->odoo->searchRead(
                'fleet.vehicle.usage', [],
                ['id', 'name', 'vehicle_id', 'partner_id', 'state', 'date_picking', 'date_return', 'notes'],
                1000, 0, 'id desc'
            );
            $seenUsages = [];
            foreach ($rows as $row) {
                $seenUsages[] = $row['id'];
                FleetVehicleUsage::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'            => $row['name'] ?: null,
                        'odoo_vehicle_id' => OdooService::many2oneId($row['vehicle_id']) ?? 0,
                        'vehicle_name'    => OdooService::many2oneName($row['vehicle_id']),
                        'partner_name'    => OdooService::many2oneName($row['partner_id']),
                        'state'           => $row['state'] ?: 'draft',
                        'date_picking'    => $this->parseOdooDate($row['date_picking']),
                        'date_return'     => $this->parseOdooDate($row['date_return']),
                        'notes'           => $row['notes'] ?: null,
                        'synced_at'       => now(),
                    ]
                );
                $count++;
            }
            FleetVehicleUsage::whereNotIn('odoo_id', $seenUsages)->delete();

            return $count;
        });
    }

    /** CRM stages + leads/opportunities + customers in one logical unit. */
    public function syncCrm(): SyncLog
    {
        return $this->runSync('crm', function () {
            $count = 0;

            // Pipeline stages
            $rows = $this->odoo->searchRead(
                'crm.stage', [],
                ['id', 'name', 'sequence', 'is_won', 'fold'],
                0, 0, 'sequence asc'
            );
            foreach ($rows as $row) {
                CrmStage::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'      => $row['name'],
                        'sequence'  => $row['sequence'] ?? 0,
                        'is_won'    => (bool) ($row['is_won'] ?? false),
                        'fold'      => (bool) ($row['fold'] ?? false),
                        'synced_at' => now(),
                    ]
                );
                $count++;
            }

            // Tags + lost reasons (config for the edit form). Build a tag-name map.
            $tagName = [];
            foreach ($this->odoo->searchRead('crm.tag', [], ['id', 'name', 'color'], 0, 0, 'name asc') as $row) {
                CrmTag::updateOrCreate(['odoo_id' => $row['id']],
                    ['name' => $row['name'], 'color' => $row['color'] ?? 0, 'synced_at' => now()]);
                $tagName[$row['id']] = $row['name'];
                $count++;
            }
            foreach ($this->odoo->searchRead('crm.lost.reason', [['active', 'in', [true, false]]], ['id', 'name'], 0, 0, 'name asc') as $row) {
                CrmLostReason::updateOrCreate(['odoo_id' => $row['id']], ['name' => $row['name'], 'synced_at' => now()]);
                $count++;
            }

            // Leads / opportunities (archived = lost; kept for history). Last 1000.
            $rows = $this->odoo->searchRead(
                'crm.lead', [['active', 'in', [true, false]]],
                ['id', 'name', 'type', 'contact_name', 'partner_name', 'partner_id',
                 'email_from', 'phone', 'mobile', 'expected_revenue', 'probability',
                 'stage_id', 'user_id', 'team_id', 'date_deadline', 'priority', 'lost_reason_id',
                 'tag_ids', 'description', 'active', 'create_date'],
                1000, 0, 'id desc'
            );
            foreach ($rows as $row) {
                $tags = collect($row['tag_ids'] ?? [])->map(fn ($id) => $tagName[$id] ?? null)->filter()->implode(', ');
                CrmLead::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'             => $row['name'] ?: '—',
                        'type'             => $row['type'] ?: 'opportunity',
                        'contact_name'     => $row['contact_name'] ?: null,
                        'partner_name'     => $row['partner_name'] ?: null,
                        'odoo_partner_id'  => OdooService::many2oneId($row['partner_id']),
                        'email_from'       => $row['email_from'] ?: null,
                        'phone'            => $row['phone'] ?: null,
                        'mobile'           => $row['mobile'] ?: null,
                        'expected_revenue' => $row['expected_revenue'] ?: null,
                        'probability'      => $row['probability'] ?: null,
                        'odoo_stage_id'    => OdooService::many2oneId($row['stage_id']),
                        'stage_name'       => OdooService::many2oneName($row['stage_id']),
                        'odoo_user_id'     => OdooService::many2oneId($row['user_id']),
                        'salesperson_name' => OdooService::many2oneName($row['user_id']),
                        'odoo_team_id'     => OdooService::many2oneId($row['team_id']),
                        'team_name'        => OdooService::many2oneName($row['team_id']),
                        'date_deadline'    => $this->parseOdooDate($row['date_deadline']),
                        'priority'         => $row['priority'] ?: null,
                        'odoo_lost_reason_id' => OdooService::many2oneId($row['lost_reason_id']),
                        'lost_reason'      => OdooService::many2oneName($row['lost_reason_id']),
                        'tag_names'        => $tags ?: null,
                        'description'      => is_string($row['description']) ? strip_tags($row['description']) : null,
                        'active'           => (bool) ($row['active'] ?? true),
                        'odoo_create_date' => $this->parseOdooDate($row['create_date']),
                        'synced_at'        => now(),
                    ]
                );
                $count++;
            }

            // Customers: partners flagged as customers (customer_rank > 0)
            $rows = $this->odoo->searchRead(
                'res.partner', [['customer_rank', '>', 0]],
                ['id', 'name', 'is_company', 'email', 'phone', 'mobile', 'city', 'country_id', 'vat',
                 'user_id', 'credit_limit', 'active'],
                1000, 0, 'id desc'
            );
            foreach ($rows as $row) {
                CrmCustomer::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'            => $row['name'] ?: '—',
                        'is_company'      => (bool) ($row['is_company'] ?? false),
                        'email'           => $row['email'] ?: null,
                        'phone'           => $row['phone'] ?: null,
                        'mobile'          => $row['mobile'] ?: null,
                        'city'            => $row['city'] ?: null,
                        'country_name'    => OdooService::many2oneName($row['country_id']),
                        'vat'             => $row['vat'] ?: null,
                        'odoo_user_id'    => OdooService::many2oneId($row['user_id']),
                        'account_manager' => OdooService::many2oneName($row['user_id']),
                        'credit_limit'    => $row['credit_limit'] ?: null,
                        'active'          => (bool) ($row['active'] ?? true),
                        'synced_at'       => now(),
                    ]
                );
                $count++;
            }

            return $count;
        });
    }

    /** Jobs + stages + applicants in one logical unit. */
    public function syncRecruitment(): SyncLog
    {
        return $this->runSync('hr.recruitment', function () {
            $count = 0;

            // Stages
            $rows = $this->odoo->searchRead(
                'hr.recruitment.stage', [],
                ['id', 'name', 'sequence', 'hired_stage', 'fold'],
                0, 0, 'sequence asc'
            );
            foreach ($rows as $row) {
                RecruitmentStage::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'        => $row['name'],
                        'sequence'    => $row['sequence'] ?? 0,
                        'hired_stage' => (bool) ($row['hired_stage'] ?? false),
                        'fold'        => (bool) ($row['fold'] ?? false),
                        'synced_at'   => now(),
                    ]
                );
                $count++;
            }

            // Job positions (including archived, so closed roles keep history)
            $rows = $this->odoo->searchRead(
                'hr.job', [['active', 'in', [true, false]]],
                ['id', 'name', 'department_id', 'no_of_recruitment', 'application_count', 'user_id', 'active'],
                0, 0, 'id asc'
            );
            foreach ($rows as $row) {
                JobPosition::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'               => $row['name'],
                        'odoo_department_id' => OdooService::many2oneId($row['department_id']),
                        'department_name'    => OdooService::many2oneName($row['department_id']),
                        'no_of_recruitment'  => $row['no_of_recruitment'] ?? 0,
                        'application_count'  => $row['application_count'] ?? 0,
                        'recruiter_name'     => OdooService::many2oneName($row['user_id']),
                        'active'             => (bool) ($row['active'] ?? true),
                        'synced_at'          => now(),
                    ]
                );
                $count++;
            }

            // Applicants (archived = refused; kept for history). Last 1000.
            $rows = $this->odoo->searchRead(
                'hr.applicant', [['active', 'in', [true, false]]],
                ['id', 'partner_name', 'email_from', 'partner_phone', 'partner_mobile',
                 'job_id', 'stage_id', 'department_id', 'salary_expected', 'salary_proposed',
                 'availability', 'priority', 'kanban_state', 'refuse_reason_id', 'active', 'create_date'],
                1000, 0, 'id desc'
            );
            foreach ($rows as $row) {
                Applicant::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'partner_name'       => $row['partner_name'] ?: '—',
                        'email_from'         => $row['email_from'] ?: null,
                        'partner_phone'      => $row['partner_phone'] ?: null,
                        'partner_mobile'     => $row['partner_mobile'] ?: null,
                        'odoo_job_id'        => OdooService::many2oneId($row['job_id']),
                        'job_name'           => OdooService::many2oneName($row['job_id']),
                        'odoo_stage_id'      => OdooService::many2oneId($row['stage_id']),
                        'stage_name'         => OdooService::many2oneName($row['stage_id']),
                        'odoo_department_id' => OdooService::many2oneId($row['department_id']),
                        'department_name'    => OdooService::many2oneName($row['department_id']),
                        'salary_expected'    => $row['salary_expected'] ?: null,
                        'salary_proposed'    => $row['salary_proposed'] ?: null,
                        'availability'       => $this->parseOdooDate($row['availability']),
                        'priority'           => $row['priority'] ?: null,
                        'kanban_state'       => $row['kanban_state'] ?: null,
                        'refuse_reason'      => OdooService::many2oneName($row['refuse_reason_id']),
                        'active'             => (bool) ($row['active'] ?? true),
                        'odoo_create_date'   => $this->parseOdooDate($row['create_date']),
                        'synced_at'          => now(),
                    ]
                );
                $count++;
            }

            return $count;
        });
    }

    /** Courier daily performance (mj_courier_daily) — batched, can be large. */
    public function syncCourierDaily(): SyncLog
    {
        return $this->runSync('mj.courier.daily', function () {
            $count = 0; $offset = 0; $batch = 1000;
            do {
                $rows = $this->odoo->searchRead('mj.courier.daily', [],
                    ['id', 'date', 'month', 'employee_id', 'courier_name', 'vehicle_plate',
                     'city', 'project', 'present', 'ofd', 'delivered', 'performance'],
                    $batch, $offset, 'id asc');
                foreach ($rows as $row) {
                    CourierDaily::updateOrCreate(['odoo_id' => $row['id']], [
                        'date'             => $this->parseOdooDate($row['date']),
                        'month'            => $row['month'] ?: null,
                        'odoo_employee_id' => OdooService::many2oneId($row['employee_id']),
                        'courier_name'     => $row['courier_name'] ?: null,
                        'vehicle_plate'    => $row['vehicle_plate'] ?: null,
                        'city'             => $row['city'] ?: null,
                        'project'          => $row['project'] ?: null,
                        'present'          => (bool) ($row['present'] ?? false),
                        'ofd'              => $row['ofd'] ?? 0,
                        'delivered'        => $row['delivered'] ?? 0,
                        'performance'      => $row['performance'] ?? 0,
                        'synced_at'        => now(),
                    ]);
                    $count++;
                }
                $offset += $batch;
            } while (count($rows) === $batch);
            return $count;
        });
    }

    public function syncContracts(): SyncLog
    {
        return $this->runSync('hr.contract', function () {
            $rows = $this->odoo->searchRead(
                'hr.contract', [],
                ['id', 'name', 'employee_id', 'wage', 'date_start', 'date_end', 'state', 'struct_id',
                 'mj_signed', 'mj_signed_date', 'mj_signed_by'],
                0, 0, 'id asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                Contract::updateOrCreate(['odoo_id' => $row['id']], $this->contractColumns($row));
                $count++;
            }
            return $count;
        });
    }

    /** Shared mapping of an Odoo hr.contract row to local columns. */
    protected function contractColumns(array $row): array
    {
        return [
            'name'             => $row['name'],
            'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
            'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
            'wage'             => $row['wage'] ?? 0,
            'date_start'       => $this->parseOdooDate($row['date_start']),
            'date_end'         => $this->parseOdooDate($row['date_end']),
            'state'            => $row['state'] ?? 'draft',
            'odoo_struct_id'   => OdooService::many2oneId($row['struct_id']),
            'struct_name'      => OdooService::many2oneName($row['struct_id']),
            'signed'           => (bool) ($row['mj_signed'] ?? false),
            'signed_date'      => $this->parseOdooDate($row['mj_signed_date'] ?? false),
            'signed_by'        => $row['mj_signed_by'] ?: null,
            'synced_at'        => now(),
        ];
    }

    /** Reference data: countries (res.country) — read-only mirror. */
    public function syncCountries(): SyncLog
    {
        return $this->runSync('res.country', function () {
            $rows = $this->odoo->searchRead(
                'res.country', [],
                ['id', 'name', 'code', 'phone_code', 'currency_id'],
                0, 0, 'name asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                Country::updateOrCreate(
                    ['odoo_id' => $row['id']],
                    [
                        'name'          => $row['name'],
                        'code'          => $row['code'] ?: null,
                        'phone_code'    => $row['phone_code'] ?: null,
                        'currency_name' => OdooService::many2oneName($row['currency_id']),
                        'synced_at'     => now(),
                    ]
                );
                $count++;
            }
            return $count;
        });
    }

    public function syncCompanies(): SyncLog
    {
        return $this->runSync('res.company', function () {
            $rows = $this->odoo->searchRead(
                'res.company', [['active', 'in', [true, false]]],
                ['id', 'name', 'parent_id', 'company_registry', 'vat',
                 'phone', 'email', 'city', 'country_id', 'active'],
                0, 0, 'id asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                $this->writeCompany($row);
                $count++;
            }
            return $count;
        });
    }

    protected function writeCompany(array $row): Company
    {
        return Company::updateOrCreate(
            ['odoo_id' => $row['id']],
            [
                'name'             => $row['name'],
                'odoo_parent_id'   => OdooService::many2oneId($row['parent_id']),
                'parent_name'      => OdooService::many2oneName($row['parent_id']),
                'company_registry' => $row['company_registry'] ?: null,
                'vat'              => $row['vat'] ?: null,
                'phone'            => $row['phone'] ?: null,
                'email'            => $row['email'] ?: null,
                'city'             => $row['city'] ?: null,
                'country_name'     => OdooService::many2oneName($row['country_id']),
                'active'           => (bool) ($row['active'] ?? true),
                'synced_at'        => now(),
            ]
        );
    }

    public function refreshCompany(int $odooId): ?Company
    {
        try {
            $rows = $this->odoo->read('res.company', [$odooId],
                ['id', 'name', 'parent_id', 'company_registry', 'vat',
                 'phone', 'email', 'city', 'country_id', 'active']);
            return empty($rows) ? null : $this->writeCompany($rows[0]);
        } catch (Throwable) {
            return null;
        }
    }

    public function syncLoans(): SyncLog
    {
        return $this->runSync('hr.loan', function () {
            $rows = $this->odoo->searchRead(
                'hr.loan', [],
                ['id', 'name', 'employee_id', 'date', 'amount', 'installment',
                 'reason', 'state', 'repaid_amount', 'balance'],
                0, 0, 'id asc'
            );

            $count = 0;
            foreach ($rows as $row) {
                $this->writeLoan($row);
                $count++;
            }

            // Repayment lines (full mirror — volumes are tiny)
            $lines = $this->odoo->searchRead(
                'hr.loan.line', [],
                ['id', 'loan_id', 'date', 'amount', 'note'],
                0, 0, 'id asc'
            );
            $seen = [];
            foreach ($lines as $line) {
                $seen[] = $line['id'];
                LoanLine::updateOrCreate(
                    ['odoo_id' => $line['id']],
                    [
                        'odoo_loan_id' => OdooService::many2oneId($line['loan_id']) ?? 0,
                        'date'         => $this->parseOdooDate($line['date']),
                        'amount'       => $line['amount'] ?? 0,
                        'note'         => $line['note'] ?: null,
                        'synced_at'    => now(),
                    ]
                );
            }
            // Lines deleted in Odoo disappear locally too (cascade deletes are common here).
            LoanLine::whereNotIn('odoo_id', $seen)->delete();

            return $count;
        });
    }

    /** Fuel logs + accidents (mj_fleet_ops). Returns rows written. */
    protected function syncFleetOps(): int
    {
        $n = 0;
        $rows = $this->odoo->searchRead('fleet.vehicle.log.fuel', [['active', 'in', [true, false]]],
            ['id', 'vehicle_id', 'purchaser_id', 'date', 'liter', 'amount', 'price_per_liter', 'odometer', 'state'],
            2000, 0, 'id desc');
        $seen = [];
        foreach ($rows as $r) {
            $seen[] = $r['id'];
            FleetFuelLog::updateOrCreate(['odoo_id' => $r['id']], [
                'odoo_vehicle_id' => OdooService::many2oneId($r['vehicle_id']) ?? 0,
                'vehicle_name'    => OdooService::many2oneName($r['vehicle_id']),
                'driver_name'     => OdooService::many2oneName($r['purchaser_id']),
                'date'            => $this->parseOdooDate($r['date']),
                'liters'          => $r['liter'] ?? 0,
                'amount'          => $r['amount'] ?? 0,
                'price_per_liter' => $r['price_per_liter'] ?? 0,
                'odometer'        => $r['odometer'] ?: null,
                'state'           => $r['state'] ?? 'todo',
                'synced_at'       => now(),
            ]);
            $n++;
        }
        FleetFuelLog::whereNotIn('odoo_id', $seen ?: [0])->delete();

        $rows = $this->odoo->searchRead('fleet.accident', [],
            ['id', 'name', 'vehicle_id', 'driver_id', 'date', 'location', 'severity', 'description',
             'third_party', 'repair_cost', 'insurer', 'claim_state', 'claim_amount', 'state'],
            2000, 0, 'id desc');
        $seen = [];
        foreach ($rows as $r) {
            $seen[] = $r['id'];
            FleetAccident::updateOrCreate(['odoo_id' => $r['id']], [
                'name'            => $r['name'] ?: null,
                'odoo_vehicle_id' => OdooService::many2oneId($r['vehicle_id']) ?? 0,
                'vehicle_name'    => OdooService::many2oneName($r['vehicle_id']),
                'driver_name'     => OdooService::many2oneName($r['driver_id']),
                'date'            => $this->parseOdooDate($r['date']),
                'location'        => $r['location'] ?: null,
                'severity'        => $r['severity'] ?: 'minor',
                'description'     => $r['description'] ?: null,
                'third_party'     => $r['third_party'] ?: null,
                'repair_cost'     => $r['repair_cost'] ?? 0,
                'insurer'         => $r['insurer'] ?: null,
                'claim_state'     => $r['claim_state'] ?: 'none',
                'claim_amount'    => $r['claim_amount'] ?? 0,
                'state'           => $r['state'] ?? 'draft',
                'synced_at'       => now(),
            ]);
            $n++;
        }
        FleetAccident::whereNotIn('odoo_id', $seen ?: [0])->delete();

        return $n;
    }

    protected function writeLoan(array $row): Loan
    {
        return Loan::updateOrCreate(
            ['odoo_id' => $row['id']],
            [
                'name'             => $row['name'] ?: null,
                'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                'date'             => $this->parseOdooDate($row['date']),
                'amount'           => $row['amount'] ?? 0,
                'installment'      => $row['installment'] ?? 0,
                'reason'           => $row['reason'] ?: null,
                'state'            => $row['state'] ?? 'draft',
                'repaid_amount'    => $row['repaid_amount'] ?? 0,
                'balance'          => $row['balance'] ?? 0,
                'synced_at'        => now(),
            ]
        );
    }

    public function refreshLoan(int $odooId): ?Loan
    {
        try {
            $rows = $this->odoo->read('hr.loan', [$odooId],
                ['id', 'name', 'employee_id', 'date', 'amount', 'installment',
                 'reason', 'state', 'repaid_amount', 'balance']);
            if (empty($rows)) return null;
            $loan = $this->writeLoan($rows[0]);

            $lines = $this->odoo->searchRead('hr.loan.line', [['loan_id', '=', $odooId]],
                ['id', 'loan_id', 'date', 'amount', 'note'], 0, 0, 'id asc');
            $seen = [];
            foreach ($lines as $line) {
                $seen[] = $line['id'];
                LoanLine::updateOrCreate(
                    ['odoo_id' => $line['id']],
                    [
                        'odoo_loan_id' => $odooId,
                        'date'         => $this->parseOdooDate($line['date']),
                        'amount'       => $line['amount'] ?? 0,
                        'note'         => $line['note'] ?: null,
                        'synced_at'    => now(),
                    ]
                );
            }
            LoanLine::where('odoo_loan_id', $odooId)->whereNotIn('odoo_id', $seen)->delete();

            return $loan;
        } catch (Throwable) {
            return null;
        }
    }

    /** Salary adjustments (penalties / deductions / rewards / allowances) — full mirror, volumes are small. */
    public function syncSalaryAdjustments(): SyncLog
    {
        return $this->runSync('hr.salary.adjustment', function () {
            $rows = $this->odoo->searchRead(
                'hr.salary.adjustment', [],
                ['id', 'name', 'employee_id', 'kind', 'date', 'amount', 'reason', 'state'],
                0, 0, 'id asc'
            );
            $seen = [];
            foreach ($rows as $row) {
                $seen[] = $row['id'];
                $this->writeSalaryAdjustment($row);
            }
            // Adjustments deleted in Odoo disappear locally too.
            SalaryAdjustment::whereNotIn('odoo_id', $seen)->delete();
            return count($rows);
        });
    }

    protected function writeSalaryAdjustment(array $row): SalaryAdjustment
    {
        return SalaryAdjustment::updateOrCreate(
            ['odoo_id' => $row['id']],
            [
                'name'             => $row['name'] ?: null,
                'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                'kind'             => $row['kind'] ?? 'penalty',
                'date'             => $this->parseOdooDate($row['date']),
                'amount'           => $row['amount'] ?? 0,
                'reason'           => $row['reason'] ?: null,
                'state'            => $row['state'] ?? 'draft',
                'synced_at'        => now(),
            ]
        );
    }

    public function refreshSalaryAdjustment(int $odooId): ?SalaryAdjustment
    {
        try {
            $rows = $this->odoo->read('hr.salary.adjustment', [$odooId],
                ['id', 'name', 'employee_id', 'kind', 'date', 'amount', 'reason', 'state']);
            return empty($rows) ? null : $this->writeSalaryAdjustment($rows[0]);
        } catch (Throwable) {
            return null;
        }
    }

    /** Employee documents — metadata only (the file bytes stay in Odoo's filestore). */
    /** Employee self-service requests (mj_hr_ess). */
    public function syncHrRequests(): SyncLog
    {
        return $this->runSync('mj.hr.request', function () {
            $rows = $this->odoo->searchRead('mj.hr.request', [],
                ['id', 'name', 'employee_id', 'request_type', 'state', 'date_request', 'summary',
                 'description', 'approver_id', 'manager_note', 'certificate_kind', 'addressed_to',
                 'certificate_pdf', 'target_department_id', 'last_working_day', 'resign_reason', 'item', 'qty'],
                2000, 0, 'id desc');
            $seen = [];
            foreach ($rows as $r) {
                $seen[] = $r['id'];
                $this->writeHrRequest($r);
            }
            HrRequest::whereNotIn('odoo_id', $seen ?: [0])->delete();
            return count($rows);
        });
    }

    protected function writeHrRequest(array $r): HrRequest
    {
        return HrRequest::updateOrCreate(['odoo_id' => $r['id']], [
            'name'             => $r['name'] ?: null,
            'odoo_employee_id' => OdooService::many2oneId($r['employee_id']) ?? 0,
            'employee_name'    => OdooService::many2oneName($r['employee_id']),
            'request_type'     => $r['request_type'] ?? 'other',
            'state'            => $r['state'] ?? 'draft',
            'date_request'     => $this->parseOdooDate($r['date_request']),
            'summary'          => $r['summary'] ?: null,
            'description'      => $r['description'] ?: null,
            'approver_name'    => OdooService::many2oneName($r['approver_id']),
            'manager_note'     => $r['manager_note'] ?: null,
            'certificate_kind' => $r['certificate_kind'] ?: null,
            'addressed_to'     => $r['addressed_to'] ?: null,
            'has_certificate'  => !empty($r['certificate_pdf']),
            'target_department' => OdooService::many2oneName($r['target_department_id']),
            'last_working_day' => $this->parseOdooDate($r['last_working_day']),
            'resign_reason'    => $r['resign_reason'] ?: null,
            'item'             => $r['item'] ?: null,
            'qty'              => $r['qty'] ?: null,
            'synced_at'        => now(),
        ]);
    }

    public function refreshHrRequest(int $odooId): ?HrRequest
    {
        try {
            $rows = $this->odoo->read('mj.hr.request', [$odooId],
                ['id', 'name', 'employee_id', 'request_type', 'state', 'date_request', 'summary',
                 'description', 'approver_id', 'manager_note', 'certificate_kind', 'addressed_to',
                 'certificate_pdf', 'target_department_id', 'last_working_day', 'resign_reason', 'item', 'qty']);
            return empty($rows) ? null : $this->writeHrRequest($rows[0]);
        } catch (Throwable) {
            return null;
        }
    }

    public function syncEmployeeDocuments(): SyncLog
    {
        return $this->runSync('hr.employee.document', function () {
            $rows = $this->odoo->searchRead(
                'hr.employee.document', [],
                ['id', 'name', 'employee_id', 'category', 'filename', 'mimetype',
                 'issue_date', 'expiry_date', 'note'],
                0, 0, 'id asc'
            );
            $seen = [];
            foreach ($rows as $row) {
                $seen[] = $row['id'];
                $this->writeEmployeeDocument($row);
            }
            EmployeeDocument::whereNotIn('odoo_id', $seen)->delete();
            return count($rows);
        });
    }

    protected function writeEmployeeDocument(array $row): EmployeeDocument
    {
        return EmployeeDocument::updateOrCreate(
            ['odoo_id' => $row['id']],
            [
                'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                'name'             => $row['name'] ?: null,
                'category'         => $row['category'] ?? 'other',
                'filename'         => $row['filename'] ?: null,
                'mimetype'         => $row['mimetype'] ?: null,
                'issue_date'       => $this->parseOdooDate($row['issue_date']),
                'expiry_date'      => $this->parseOdooDate($row['expiry_date']),
                'note'             => $row['note'] ?: null,
                'synced_at'        => now(),
            ]
        );
    }

    public function refreshEmployeeDocument(int $odooId): ?EmployeeDocument
    {
        try {
            $rows = $this->odoo->read('hr.employee.document', [$odooId],
                ['id', 'name', 'employee_id', 'category', 'filename', 'mimetype',
                 'issue_date', 'expiry_date', 'note']);
            return empty($rows) ? null : $this->writeEmployeeDocument($rows[0]);
        } catch (Throwable) {
            return null;
        }
    }

    // ─── HR Forms (mj_hr_forms addon) — full mirrors, volumes are small ───

    public function syncWarnings(): SyncLog
    {
        return $this->runSync('hr.warning', function () {
            $rows = $this->odoo->searchRead('hr.warning', [],
                ['id', 'name', 'employee_id', 'warning_type', 'date', 'subject', 'description', 'state'],
                0, 0, 'id asc');
            $seen = [];
            foreach ($rows as $row) {
                $seen[] = $row['id'];
                Warning::updateOrCreate(['odoo_id' => $row['id']], [
                    'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                    'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                    'name'         => $row['name'] ?: null,
                    'warning_type' => $row['warning_type'] ?? 'first',
                    'date'         => $this->parseOdooDate($row['date']),
                    'subject'      => $row['subject'] ?: null,
                    'description'  => $row['description'] ?: null,
                    'state'        => $row['state'] ?? 'draft',
                    'synced_at'    => now(),
                ]);
            }
            Warning::whereNotIn('odoo_id', $seen)->delete();
            return count($rows);
        });
    }

    public function syncSickLeaves(): SyncLog
    {
        return $this->runSync('hr.sick.leave', function () {
            $rows = $this->odoo->searchRead('hr.sick.leave', [],
                ['id', 'name', 'employee_id', 'date_from', 'date_to', 'days', 'diagnosis',
                 'doctor_name', 'facility', 'note', 'state'],
                0, 0, 'id asc');
            $seen = [];
            foreach ($rows as $row) {
                $seen[] = $row['id'];
                SickLeave::updateOrCreate(['odoo_id' => $row['id']], [
                    'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                    'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                    'name'        => $row['name'] ?: null,
                    'date_from'   => $this->parseOdooDate($row['date_from']),
                    'date_to'     => $this->parseOdooDate($row['date_to']),
                    'days'        => $row['days'] ?? 0,
                    'diagnosis'   => $row['diagnosis'] ?: null,
                    'doctor_name' => $row['doctor_name'] ?: null,
                    'facility'    => $row['facility'] ?: null,
                    'note'        => $row['note'] ?: null,
                    'state'       => $row['state'] ?? 'draft',
                    'synced_at'   => now(),
                ]);
            }
            SickLeave::whereNotIn('odoo_id', $seen)->delete();
            return count($rows);
        });
    }

    public function syncServiceEnds(): SyncLog
    {
        return $this->runSync('hr.service.end', function () {
            $rows = $this->odoo->searchRead('hr.service.end', [],
                ['id', 'name', 'employee_id', 'reason', 'last_working_day', 'notice_served',
                 'custody_returned', 'custody_note', 'settlement_amount', 'clearance_note', 'state'],
                0, 0, 'id asc');
            $seen = [];
            foreach ($rows as $row) {
                $seen[] = $row['id'];
                ServiceEnd::updateOrCreate(['odoo_id' => $row['id']], [
                    'odoo_employee_id' => OdooService::many2oneId($row['employee_id']) ?? 0,
                    'employee_name'    => OdooService::many2oneName($row['employee_id']) ?? '—',
                    'name'             => $row['name'] ?: null,
                    'reason'           => $row['reason'] ?? 'resignation',
                    'last_working_day' => $this->parseOdooDate($row['last_working_day']),
                    'notice_served'    => (bool) ($row['notice_served'] ?? false),
                    'custody_returned' => (bool) ($row['custody_returned'] ?? false),
                    'custody_note'     => $row['custody_note'] ?: null,
                    'settlement_amount' => $row['settlement_amount'] ?? 0,
                    'clearance_note'   => $row['clearance_note'] ?: null,
                    'state'            => $row['state'] ?? 'draft',
                    'synced_at'        => now(),
                ]);
            }
            ServiceEnd::whereNotIn('odoo_id', $seen)->delete();
            return count($rows);
        });
    }

    /** Full mirror of every payslip payment (volumes are tiny). */
    public function syncPayslipPayments(): SyncLog
    {
        return $this->runSync('hr.payslip.payment', function () {
            $rows = $this->odoo->searchRead(
                'hr.payslip.payment', [],
                ['id', 'payslip_id', 'date', 'amount', 'method', 'reference', 'note'],
                0, 0, 'id asc'
            );
            $seen = [];
            foreach ($rows as $line) {
                $seen[] = $line['id'];
                PayslipPayment::updateOrCreate(
                    ['odoo_id' => $line['id']],
                    [
                        'odoo_payslip_id' => OdooService::many2oneId($line['payslip_id']) ?? 0,
                        'date'            => $this->parseOdooDate($line['date']),
                        'amount'          => $line['amount'] ?? 0,
                        'method'          => $line['method'] ?: null,
                        'reference'       => $line['reference'] ?: null,
                        'note'            => $line['note'] ?: null,
                        'synced_at'       => now(),
                    ]
                );
            }
            PayslipPayment::whereNotIn('odoo_id', $seen)->delete();
            return count($rows);
        });
    }

    /** Payslip payments — refreshed per-payslip after a write (and as part of a full sync). */
    public function refreshPayslipPayments(int $payslipOdooId): void
    {
        $lines = $this->odoo->searchRead('hr.payslip.payment', [['payslip_id', '=', $payslipOdooId]],
            ['id', 'payslip_id', 'date', 'amount', 'method', 'reference', 'note'], 0, 0, 'id asc');
        $seen = [];
        foreach ($lines as $line) {
            $seen[] = $line['id'];
            PayslipPayment::updateOrCreate(
                ['odoo_id' => $line['id']],
                [
                    'odoo_payslip_id' => $payslipOdooId,
                    'date'            => $this->parseOdooDate($line['date']),
                    'amount'          => $line['amount'] ?? 0,
                    'method'          => $line['method'] ?: null,
                    'reference'       => $line['reference'] ?: null,
                    'note'            => $line['note'] ?: null,
                    'synced_at'       => now(),
                ]
            );
        }
        PayslipPayment::where('odoo_payslip_id', $payslipOdooId)->whereNotIn('odoo_id', $seen)->delete();
    }

    public function syncPayslips(): SyncLog
    {
        return $this->runSync('hr.payslip', function () {
            $rows = $this->odoo->searchRead(
                'hr.payslip', [],
                ['id', 'number', 'employee_id', 'contract_id', 'date_from', 'date_to', 'state', 'line_ids',
                 'payment_status', 'amount_paid', 'amount_due', 'employee_confirmed'],
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
                ['id', 'number', 'employee_id', 'contract_id', 'date_from', 'date_to', 'state', 'line_ids',
                 'payment_status', 'amount_paid', 'amount_due', 'employee_confirmed'],
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
                'payment_status'   => $row['payment_status'] ?? 'unpaid',
                'amount_paid'      => $row['amount_paid'] ?? 0,
                'amount_due'       => $row['amount_due'] ?? 0,
                'employee_confirmed' => (bool) ($row['employee_confirmed'] ?? false),
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
                ['id', 'name', 'employee_id', 'wage', 'date_start', 'date_end', 'state', 'struct_id',
                 'mj_signed', 'mj_signed_date', 'mj_signed_by']);
            if (empty($rows)) return null;

            return Contract::updateOrCreate(['odoo_id' => $rows[0]['id']], $this->contractColumns($rows[0]));
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
            // active in [true,false]: Odoo hides archived records by default —
            // without this, employees archived in Odoo stay "active" locally.
            $rows = $this->odoo->searchRead(
                'hr.employee', [['active', 'in', [true, false]]],
                ['id', 'name', 'job_title', 'work_email', 'work_phone',
                 'mobile_phone', 'department_id', 'parent_id', 'work_location_id',
                 'company_id', 'active', 'image_128'],
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
                        'odoo_company_id'    => OdooService::many2oneId($row['company_id']),
                        'company_name'       => OdooService::many2oneName($row['company_id']),
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
                 'company_id', 'active', 'image_128']);
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
                    'odoo_company_id'    => OdooService::many2oneId($row['company_id']),
                    'company_name'       => OdooService::many2oneName($row['company_id']),
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
