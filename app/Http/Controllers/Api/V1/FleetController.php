<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FleetInspection;
use App\Models\FleetInspectionItem;
use App\Models\FleetInspectionLine;
use App\Models\FleetInspectionTemplate;
use App\Models\FleetServiceLog;
use App\Models\FleetServiceType;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleCategory;
use App\Models\FleetVehicleModel;
use App\Models\FleetVehicleState;
use App\Models\FleetVehicleUsage;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class FleetController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    public function vehicles(Request $request): JsonResponse
    {
        $query = FleetVehicle::where('active', true);

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('license_plate', 'like', "%{$search}%")
                  ->orWhere('model_name', 'like', "%{$search}%")
                  ->orWhere('driver_name', 'like', "%{$search}%");
            });
        }
        if ($stateId = $request->get('state_id')) {
            $query->where('odoo_state_id', $stateId);
        }

        $page = $query->orderBy('name')->paginate(min((int) $request->get('per_page', 20), 100))
            ->withQueryString()->through(fn ($v) => $this->vehicleSummary($v));

        return response()->json($page->toArray() + [
            'states' => FleetVehicleState::orderBy('sequence')->get()->map(fn ($s) => [
                'odoo_id' => $s->odoo_id, 'name' => $s->name, 'sequence' => $s->sequence,
            ]),
            'stats' => [
                'total'         => FleetVehicle::where('active', true)->count(),
                'assigned'      => FleetVehicle::where('active', true)->whereNotNull('odoo_driver_partner_id')->count(),
                'unassigned'    => FleetVehicle::where('active', true)->whereNull('odoo_driver_partner_id')->count(),
                'services_open' => FleetServiceLog::whereIn('state', ['todo', 'running'])->count(),
            ],
        ]);
    }

    public function vehicle(int $id): JsonResponse
    {
        $vehicle = FleetVehicle::findOrFail($id);

        return response()->json(['data' => $this->vehicleSummary($vehicle) + [
            'vin_sn'           => $vehicle->vin_sn,
            'model_year'       => $vehicle->model_year,
            'color'            => $vehicle->color,
            'seats'            => $vehicle->seats,
            'doors'            => $vehicle->doors,
            'acquisition_date' => $vehicle->acquisition_date,
            'car_value'        => $vehicle->car_value,
            'fuel_capacity'    => $vehicle->fuel_capacity,
            'category_name'    => $vehicle->category_name,
            'in_use'           => $vehicle->in_use,
            'services'         => $vehicle->serviceLogs()->orderByDesc('date')->limit(20)->get()
                ->map(fn ($s) => $this->serviceSummary($s)),
            'inspections'      => $vehicle->inspections()->with('lines')->orderByDesc('date_inspected')->limit(20)->get()
                ->map(fn ($i) => $this->inspectionSummary($i)),
            'usages'           => $vehicle->usages()->orderByDesc('date_picking')->limit(20)->get()
                ->map(fn ($u) => $this->usageSummary($u)),
            'service_types'    => FleetServiceType::orderBy('name')->get()
                ->map(fn ($t) => ['odoo_id' => $t->odoo_id, 'name' => $t->name]),
            'inspection_templates' => FleetInspectionTemplate::orderBy('name')->get()
                ->map(fn ($t) => ['odoo_id' => $t->odoo_id, 'name' => $t->name]),
            'inspection_items' => FleetInspectionItem::orderBy('name')->get()
                ->map(fn ($t) => ['odoo_id' => $t->odoo_id, 'name' => $t->name]),
        ]]);
    }

    /** Vehicle categories for the new-vehicle picker (fleet_vehicle_category). */
    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => FleetVehicleCategory::orderBy('name')->get()
                ->map(fn ($c) => ['odoo_id' => $c->odoo_id, 'name' => $c->name]),
        ]);
    }

    public function addInspection(Request $request, int $id): JsonResponse
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate([
            'direction'      => 'required|in:in,out',
            'date_inspected' => 'nullable|date',
            'note'           => 'nullable|string|max:2000',
            'template_id'    => 'nullable|integer|exists:fleet_inspection_templates,odoo_id',
            'item_ids'       => 'nullable|array',
            'item_ids.*'     => 'integer|exists:fleet_inspection_items,odoo_id',
        ]);

        try {
            // Build the checklist lines from a template, or from picked items.
            // (The addon populates lines via an onchange that XML-RPC skips.)
            $lineCommands = [];
            if (!empty($data['template_id'])) {
                $tplLines = $this->odoo->searchRead('fleet.vehicle.inspection.template.line',
                    [['inspection_template_id', '=', (int) $data['template_id']]],
                    ['inspection_template_item_id', 'sequence'], 0, 0, 'sequence asc');
                foreach ($tplLines as $tl) {
                    $itemId = OdooService::many2oneId($tl['inspection_template_item_id']);
                    if ($itemId) {
                        $lineCommands[] = [0, 0, ['inspection_item_id' => $itemId, 'sequence' => $tl['sequence'] ?? 10]];
                    }
                }
            }
            foreach ($data['item_ids'] ?? [] as $itemId) {
                $lineCommands[] = [0, 0, ['inspection_item_id' => (int) $itemId]];
            }

            $payload = array_filter([
                'vehicle_id'     => $vehicle->odoo_id,
                'direction'      => $data['direction'],
                'date_inspected' => $data['date_inspected'] ?? now()->toDateTimeString(),
                'note'           => $data['note'] ?? null,
            ]);
            if ($lineCommands) {
                $payload['inspection_line_ids'] = $lineCommands;
            }
            $this->odoo->create('fleet.vehicle.inspection', $payload);
            $this->sync->syncFleet();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'created'], 201);
    }

    /** Set a checklist line's result (todo/success/failure) + optional note. */
    public function setInspectionLine(Request $request, int $lineId): JsonResponse
    {
        $line = FleetInspectionLine::findOrFail($lineId);
        $data = $request->validate([
            'result'             => 'required|in:todo,success,failure',
            'result_description' => 'nullable|string|max:255',
        ]);

        try {
            $this->odoo->write('fleet.vehicle.inspection.line', [$line->odoo_id], array_filter([
                'result'             => $data['result'],
                'result_description' => $data['result_description'] ?? null,
            ], fn ($v) => $v !== null));
            $this->sync->syncFleet();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $line->id]]);
    }

    /** Inspection state transition: confirm | draft | cancel | delete. */
    public function inspectionAction(string $action, int $id): JsonResponse
    {
        $inspection = FleetInspection::findOrFail($id);
        $methods = ['confirm' => 'button_confirm', 'draft' => 'button_draft', 'cancel' => 'button_cancel'];

        try {
            if ($action === 'delete') {
                $this->odoo->unlink('fleet.vehicle.inspection', [$inspection->odoo_id]);
                $inspection->lines()->delete();
                $inspection->delete();

                return response()->json(['message' => 'deleted']);
            }
            abort_unless(isset($methods[$action]), 404);
            $this->odoo->executeKw('fleet.vehicle.inspection', $methods[$action], [[$inspection->odoo_id]]);
            $this->sync->syncFleet();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $inspection->id]]);
    }

    // ─── Vehicle usage (checkout / return) ───────────────────────────

    public function addUsage(Request $request, int $id): JsonResponse
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate([
            'employee_id'  => 'required|integer|exists:employees,id', // the driver
            'date_picking' => 'nullable|date',
            'date_return'  => 'nullable|date',
            'notes'        => 'nullable|string|max:2000',
        ]);

        // Usage requires a login account (res.users) as the driver.
        $employee = Employee::findOrFail($data['employee_id']);
        $rows = $this->odoo->read('hr.employee', [$employee->odoo_id], ['user_id', 'work_contact_id']);
        $userId = OdooService::many2oneId($rows[0]['user_id'] ?? false);
        if (!$userId) {
            return response()->json([
                'message' => __('This employee has no login account, so they cannot be set as the usage driver.'),
            ], 422);
        }

        try {
            $payload = array_filter([
                'vehicle_id'   => $vehicle->odoo_id,
                'user_id'      => $userId,
                'partner_id'   => OdooService::many2oneId($rows[0]['work_contact_id'] ?? false),
                'date_picking' => $data['date_picking'] ?? null,
                'date_return'  => $data['date_return'] ?? null,
                'notes'        => $data['notes'] ?? null,
            ]);
            $this->odoo->create('fleet.vehicle.usage', $payload);
            $this->sync->syncFleet();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'created'], 201);
    }

    /** Usage transition: pick (in use) | return | cancel. */
    public function usageAction(string $action, int $id): JsonResponse
    {
        $usage = FleetVehicleUsage::findOrFail($id);
        $methods = ['pick' => 'action_pick', 'return' => 'action_return', 'cancel' => 'action_cancel'];
        abort_unless(isset($methods[$action]), 404);

        try {
            $this->odoo->executeKw('fleet.vehicle.usage', $methods[$action], [[$usage->odoo_id]]);
            $this->sync->syncFleet();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $usage->id]]);
    }

    public function services(Request $request): JsonResponse
    {
        $query = FleetServiceLog::query();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('vehicle_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('service_type_name', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderByDesc('date')->paginate(min((int) $request->get('per_page', 20), 100))
                ->withQueryString()->through(fn ($s) => $this->serviceSummary($s))
        );
    }

    public function models(): JsonResponse
    {
        return response()->json([
            'data' => FleetVehicleModel::orderBy('name')->get()
                ->map(fn ($m) => ['odoo_id' => $m->odoo_id, 'name' => $m->name]),
        ]);
    }

    /** Minimal employee list for driver assignment (names only). */
    public function drivers(): JsonResponse
    {
        return response()->json([
            'data' => Employee::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeVehicle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'model_id'      => 'required|integer|exists:fleet_vehicle_models,odoo_id',
            'license_plate' => 'nullable|string|max:32',
            'vin_sn'        => 'nullable|string|max:64',
            'model_year'    => 'nullable|string|max:8',
            'fuel_type'     => 'nullable|in:gasoline,diesel,electric,full_hybrid,lpg',
            'fuel_capacity' => 'nullable|numeric|min:0',
            'category_id'   => 'nullable|integer|exists:fleet_vehicle_categories,odoo_id',
            'employee_id'   => 'nullable|integer|exists:employees,id',
        ]);

        $values = array_filter([
            'model_id'           => (int) $data['model_id'],
            'license_plate'      => $data['license_plate'] ?? null,
            'vin_sn'             => $data['vin_sn'] ?? null,
            'model_year'         => $data['model_year'] ?? null,
            'fuel_type'          => $data['fuel_type'] ?? null,
            'fuel_capacity'      => $data['fuel_capacity'] ?? null,
            'vehicle_category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
        ], fn ($v) => $v !== null);

        try {
            if (!empty($data['employee_id'])) {
                $partnerId = $this->employeePartnerId((int) $data['employee_id']);
                if ($partnerId) $values['driver_id'] = $partnerId;
            }
            $this->odoo->create('fleet.vehicle', $values);
            $this->sync->syncFleet();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'created'], 201);
    }

    public function updateState(Request $request, int $id): JsonResponse
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate(['state_id' => 'required|integer|exists:fleet_vehicle_states,odoo_id']);

        try {
            $this->odoo->write('fleet.vehicle', [$vehicle->odoo_id], ['state_id' => (int) $data['state_id']]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $state = FleetVehicleState::where('odoo_id', $data['state_id'])->first();
        $vehicle->update(['odoo_state_id' => $state->odoo_id, 'state_name' => $state->name, 'synced_at' => now()]);

        return response()->json(['data' => $this->vehicleSummary($vehicle->fresh())]);
    }

    public function updateOdometer(Request $request, int $id): JsonResponse
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate(['odometer' => 'required|numeric|min:0']);

        if ((float) $data['odometer'] < (float) $vehicle->odometer) {
            return response()->json([
                'message' => __('The new odometer value must not be lower than the current one (:current).',
                    ['current' => number_format($vehicle->odometer)]),
            ], 422);
        }

        try {
            $this->odoo->create('fleet.vehicle.odometer', [
                'vehicle_id' => $vehicle->odoo_id,
                'value'      => (float) $data['odometer'],
                'date'       => now()->toDateString(),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $vehicle->update(['odometer' => $data['odometer'], 'synced_at' => now()]);

        return response()->json(['data' => $this->vehicleSummary($vehicle->fresh())]);
    }

    public function assignDriver(Request $request, int $id): JsonResponse
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate(['employee_id' => 'nullable|integer|exists:employees,id']);

        try {
            if (empty($data['employee_id'])) {
                $this->odoo->write('fleet.vehicle', [$vehicle->odoo_id], ['driver_id' => false]);
                $vehicle->update(['odoo_driver_partner_id' => null, 'driver_name' => null, 'synced_at' => now()]);

                return response()->json(['data' => $this->vehicleSummary($vehicle->fresh())]);
            }

            $partnerId = $this->employeePartnerId((int) $data['employee_id']);
            if (!$partnerId) {
                return response()->json([
                    'message' => __('This employee has no contact record in Odoo, so they cannot be set as a driver.'),
                ], 422);
            }
            $this->odoo->write('fleet.vehicle', [$vehicle->odoo_id], ['driver_id' => $partnerId]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $employee = Employee::find($data['employee_id']);
        $vehicle->update(['odoo_driver_partner_id' => $partnerId, 'driver_name' => $employee->name, 'synced_at' => now()]);

        return response()->json(['data' => $this->vehicleSummary($vehicle->fresh())]);
    }

    public function addService(Request $request, int $id): JsonResponse
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate([
            'service_type_id' => 'required|integer|exists:fleet_service_types,odoo_id',
            'description'     => 'nullable|string|max:255',
            'amount'          => 'nullable|numeric|min:0',
            'date'            => 'nullable|date',
            'service_ids'     => 'nullable|array',
            'service_ids.*'   => 'integer|exists:fleet_service_types,odoo_id',
        ]);

        try {
            $payload = array_filter([
                'vehicle_id'      => $vehicle->odoo_id,
                'service_type_id' => (int) $data['service_type_id'],
                'description'     => $data['description'] ?? null,
                'amount'          => $data['amount'] ?? null,
                'date'            => $data['date'] ?? now()->toDateString(),
            ]);
            if (!empty($data['service_ids'])) {
                // Many2many set: included sub-services.
                $payload['service_ids'] = [[6, 0, array_map('intval', $data['service_ids'])]];
            }
            $this->odoo->create('fleet.vehicle.log.services', $payload);
            $this->sync->syncFleet();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'created'], 201);
    }

    /** Resolve an employee's Odoo contact (res.partner) id for driver fields. */
    protected function employeePartnerId(int $employeeId): ?int
    {
        $employee = Employee::findOrFail($employeeId);
        $rows = $this->odoo->read('hr.employee', [$employee->odoo_id], ['work_contact_id']);

        return OdooService::many2oneId($rows[0]['work_contact_id'] ?? false);
    }

    protected function vehicleSummary(FleetVehicle $v): array
    {
        return [
            'id'            => $v->id,
            'odoo_id'       => $v->odoo_id,
            'name'          => $v->name,
            'model_name'    => $v->model_name,
            'license_plate' => $v->license_plate,
            'driver_name'   => $v->driver_name,
            'state_id'      => $v->odoo_state_id,
            'state_name'    => $v->state_name,
            'odometer'      => $v->odometer,
            'odometer_unit' => $v->odometer_unit,
            'fuel_type'     => $v->fuel_type,
            'category_name' => $v->category_name,
        ];
    }

    protected function inspectionSummary(FleetInspection $i): array
    {
        return [
            'id'             => $i->id,
            'name'           => $i->name,
            'direction'      => $i->direction,
            'state'          => $i->state,
            'result'         => $i->result,
            'date_inspected' => $i->date_inspected?->toDateTimeString(),
            'odometer'       => $i->odometer,
            'inspected_by'   => $i->inspected_by_name,
            'note'           => $i->note,
            'lines'          => $i->relationLoaded('lines')
                ? $i->lines->map(fn ($l) => [
                    'id'                 => $l->id,
                    'item_name'          => $l->item_name,
                    'result'             => $l->result,
                    'result_description' => $l->result_description,
                ])->values()
                : [],
        ];
    }

    protected function usageSummary(FleetVehicleUsage $u): array
    {
        return [
            'id'           => $u->id,
            'name'         => $u->name,
            'partner_name' => $u->partner_name,
            'state'        => $u->state,
            'date_picking' => $u->date_picking?->toDateTimeString(),
            'date_return'  => $u->date_return?->toDateTimeString(),
            'notes'        => $u->notes,
        ];
    }

    protected function serviceSummary(FleetServiceLog $s): array
    {
        return [
            'id'                => $s->id,
            'vehicle_name'      => $s->vehicle_name,
            'service_type'      => $s->service_type_name,
            'included_services' => $s->included_services,
            'description'       => $s->description,
            'date'              => $s->date,
            'amount'            => $s->amount,
            'vendor'            => $s->vendor_name,
            'state'             => $s->state,
        ];
    }
}
