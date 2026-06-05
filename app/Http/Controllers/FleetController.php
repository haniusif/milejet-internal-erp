<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FleetServiceLog;
use App\Models\FleetServiceType;
use App\Models\FleetVehicle;
use App\Models\FleetVehicleModel;
use App\Models\FleetVehicleState;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use RuntimeException;

class FleetController extends Controller
{
    public function __construct(protected OdooService $odoo, protected SyncService $sync) {}

    public function index(Request $request)
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

        $vehicles = $query->orderBy('name')->paginate(20)->withQueryString();
        $states   = FleetVehicleState::orderBy('sequence')->get();

        $stats = [
            'total'      => FleetVehicle::where('active', true)->count(),
            'assigned'   => FleetVehicle::where('active', true)->whereNotNull('odoo_driver_partner_id')->count(),
            'unassigned' => FleetVehicle::where('active', true)->whereNull('odoo_driver_partner_id')->count(),
            'services_open' => FleetServiceLog::whereIn('state', ['todo', 'running'])->count(),
        ];

        return view('fleet.vehicles', compact('vehicles', 'states', 'stats'));
    }

    public function create()
    {
        $models    = FleetVehicleModel::orderBy('name')->get();
        $employees = Employee::where('active', true)->orderBy('name')->get();

        return view('fleet.create_vehicle', compact('models', 'employees'));
    }

    public function store(Request $request)
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
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => __('Could not create the vehicle in Odoo: :error', ['error' => $e->getMessage()])]);
        }

        $this->sync->syncFleet();

        return redirect()->route('fleet.index')->with('status', __('✅ Vehicle created'));
    }

    public function show(int $id)
    {
        $vehicle      = FleetVehicle::findOrFail($id);
        $states       = FleetVehicleState::orderBy('sequence')->get();
        $serviceTypes = FleetServiceType::orderBy('name')->get();
        $employees    = Employee::where('active', true)->orderBy('name')->get();
        $services     = $vehicle->serviceLogs()->orderByDesc('date')->limit(20)->get();

        return view('fleet.show', compact('vehicle', 'states', 'serviceTypes', 'employees', 'services'));
    }

    public function updateState(Request $request, int $id)
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate(['state_id' => 'required|integer|exists:fleet_vehicle_states,odoo_id']);

        try {
            $this->odoo->write('fleet.vehicle', [$vehicle->odoo_id], ['state_id' => (int) $data['state_id']]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not update the vehicle: :error', ['error' => $e->getMessage()])]);
        }

        $state = FleetVehicleState::where('odoo_id', $data['state_id'])->first();
        $vehicle->update(['odoo_state_id' => $state->odoo_id, 'state_name' => $state->name, 'synced_at' => now()]);

        return back()->with('status', __('✅ Vehicle state updated to ":state"', ['state' => $state->name]));
    }

    public function updateOdometer(Request $request, int $id)
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate(['odometer' => 'required|numeric|min:0']);

        if ((float) $data['odometer'] < (float) $vehicle->odometer) {
            return back()->withErrors(['odoo' => __('The new odometer value must not be lower than the current one (:current).', ['current' => number_format($vehicle->odometer)])]);
        }

        try {
            // Odometer is computed from log entries — create one, like the Odoo UI does
            $this->odoo->create('fleet.vehicle.odometer', [
                'vehicle_id' => $vehicle->odoo_id,
                'value'      => (float) $data['odometer'],
                'date'       => now()->toDateString(),
            ]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not update the odometer: :error', ['error' => $e->getMessage()])]);
        }

        $vehicle->update(['odometer' => $data['odometer'], 'synced_at' => now()]);

        return back()->with('status', __('✅ Odometer updated'));
    }

    public function assignDriver(Request $request, int $id)
    {
        $vehicle = FleetVehicle::findOrFail($id);
        $data = $request->validate(['employee_id' => 'nullable|integer|exists:employees,id']);

        try {
            if (empty($data['employee_id'])) {
                $this->odoo->write('fleet.vehicle', [$vehicle->odoo_id], ['driver_id' => false]);
                $vehicle->update(['odoo_driver_partner_id' => null, 'driver_name' => null, 'synced_at' => now()]);

                return back()->with('status', __('✅ Driver unassigned'));
            }

            $partnerId = $this->employeePartnerId((int) $data['employee_id']);
            if (!$partnerId) {
                return back()->withErrors(['odoo' => __('This employee has no contact record in Odoo, so they cannot be set as a driver.')]);
            }
            $this->odoo->write('fleet.vehicle', [$vehicle->odoo_id], ['driver_id' => $partnerId]);
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not assign the driver: :error', ['error' => $e->getMessage()])]);
        }

        $employee = Employee::find($data['employee_id']);
        $vehicle->update(['odoo_driver_partner_id' => $partnerId, 'driver_name' => $employee->name, 'synced_at' => now()]);

        return back()->with('status', __('✅ Driver set to :name', ['name' => $employee->name]));
    }

    public function addService(Request $request, int $id)
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
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => __('Could not record the service: :error', ['error' => $e->getMessage()])]);
        }

        $this->sync->syncFleet();

        return back()->with('status', __('✅ Service recorded'));
    }

    public function services(Request $request)
    {
        $query = FleetServiceLog::query();

        if ($search = trim((string) $request->get('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('vehicle_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('service_type_name', 'like', "%{$search}%");
            });
        }

        $services = $query->with('vehicle')->orderByDesc('date')->paginate(20)->withQueryString();

        return view('fleet.services', compact('services'));
    }

    /** Resolve an employee's Odoo contact (res.partner) id for driver fields. */
    private function employeePartnerId(int $employeeId): ?int
    {
        $employee = Employee::findOrFail($employeeId);
        $rows = $this->odoo->read('hr.employee', [$employee->odoo_id], ['work_contact_id']);

        return OdooService::many2oneId($rows[0]['work_contact_id'] ?? false);
    }
}
