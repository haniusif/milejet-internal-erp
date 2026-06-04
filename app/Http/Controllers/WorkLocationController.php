<?php

namespace App\Http\Controllers;

use App\Models\WorkLocation;
use App\Services\OdooService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use RuntimeException;

class WorkLocationController extends Controller
{
    public function __construct(
        protected OdooService $odoo,
        protected SyncService $sync,
    ) {}

    public function index()
    {
        $locations = WorkLocation::withCount('employees')->orderBy('name')->get();
        return view('work_locations.index', compact('locations'));
    }

    public function create()
    {
        return view('work_locations.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            // hr.work.location requires an address — default to the company
            // partner (the geofence coordinates live in Laravel, not Odoo).
            $payload = [
                'name'          => $data['name'],
                'location_type' => $data['location_type'],
                'address_id'    => $this->companyPartnerId(),
            ];
            $odooId = $this->odoo->create('hr.work.location', $payload);
            $location = $this->sync->refreshWorkLocation($odooId);
            $location?->update($this->geoColumns($data));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('work-locations.index')
            ->with('status', __('Work location created'));
    }

    public function edit(int $id)
    {
        $location = WorkLocation::findOrFail($id);
        return view('work_locations.edit', compact('location'));
    }

    public function update(Request $request, int $id)
    {
        $location = WorkLocation::findOrFail($id);
        $data = $this->validated($request);

        try {
            $this->odoo->write('hr.work.location', [$location->odoo_id], [
                'name'          => $data['name'],
                'location_type' => $data['location_type'],
            ]);
            $this->sync->refreshWorkLocation($location->odoo_id);
            $location->update($this->geoColumns($data));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('work-locations.index')
            ->with('status', __('Work location updated'));
    }

    public function destroy(int $id)
    {
        $location = WorkLocation::findOrFail($id);

        try {
            $this->odoo->unlink('hr.work.location', [$location->odoo_id]);
            $location->delete();
        } catch (RuntimeException $e) {
            return back()->withErrors(['odoo' => $e->getMessage()]);
        }

        return redirect()->route('work-locations.index')
            ->with('status', __('Work location deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'            => 'required|string|max:255',
            'location_type'   => 'required|in:office,home,other',
            'latitude'        => 'nullable|required_with:longitude|numeric|between:-90,90',
            'longitude'       => 'nullable|required_with:latitude|numeric|between:-180,180',
            'geofence_radius' => 'nullable|integer|min:10|max:100000',
        ]);
    }

    /** Laravel-managed geofence columns (never written to Odoo). */
    private function geoColumns(array $data): array
    {
        return [
            'latitude'        => $data['latitude'] ?? null,
            'longitude'       => $data['longitude'] ?? null,
            'geofence_radius' => $data['geofence_radius'] ?? null,
        ];
    }

    private function companyPartnerId(): int
    {
        $rows = $this->odoo->searchRead('res.company', [], ['partner_id'], 1, 0, 'id asc');
        $partnerId = OdooService::many2oneId($rows[0]['partner_id'] ?? null);
        if (!$partnerId) {
            throw new RuntimeException('Could not resolve the company address in Odoo.');
        }
        return $partnerId;
    }
}
