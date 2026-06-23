<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FleetServiceLog;
use App\Models\FleetServiceType;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleModel;
use App\Models\FleetVehicleState;
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
            'services'         => $vehicle->serviceLogs()->orderByDesc('date')->limit(20)->get()
                ->map(fn ($s) => $this->serviceSummary($s)),
            'service_types'    => FleetServiceType::orderBy('name')->get()
                ->map(fn ($t) => ['odoo_id' => $t->odoo_id, 'name' => $t->name]),
        ]]);
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
            'employee_id'   => 'nullable|integer|exists:employees,id',
        ]);

        $values = array_filter([
            'model_id'      => (int) $data['model_id'],
            'license_plate' => $data['license_plate'] ?? null,
            'vin_sn'        => $data['vin_sn'] ?? null,
            'model_year'    => $data['model_year'] ?? null,
            'fuel_type'     => $data['fuel_type'] ?? null,
        ]);

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
        ]);

        try {
            $this->odoo->create('fleet.vehicle.log.services', array_filter([
                'vehicle_id'      => $vehicle->odoo_id,
                'service_type_id' => (int) $data['service_type_id'],
                'description'     => $data['description'] ?? null,
                'amount'          => $data['amount'] ?? null,
                'date'            => $data['date'] ?? now()->toDateString(),
            ]));
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
        ];
    }

    protected function serviceSummary(FleetServiceLog $s): array
    {
        return [
            'id'           => $s->id,
            'vehicle_name' => $s->vehicle_name,
            'service_type' => $s->service_type_name,
            'description'  => $s->description,
            'date'         => $s->date,
            'amount'       => $s->amount,
            'vendor'       => $s->vendor_name,
            'state'        => $s->state,
        ];
    }
}
