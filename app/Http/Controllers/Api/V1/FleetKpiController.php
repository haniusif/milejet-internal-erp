<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourierDaily;
use App\Models\FleetAccident;
use App\Models\FleetFuelLog;
use App\Models\FleetOdometerLog;
use App\Models\FleetServiceLog;
use App\Models\FleetVehicle;
use App\Services\OdooService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fleet analytics (mj_fleet_kpi) — read-only rollups over cached fleet data +
 * native fleet.vehicle.cost.report for contract cost. Cost/km is an estimate
 * (odometer coverage is sparse) and labelled as such.
 */
class FleetKpiController extends Controller
{
    public function __construct(protected OdooService $odoo) {}

    /** [from, to] Carbon range from ?month=YYYY-MM or ?from&?to; default = all-time. */
    private function range(Request $request): array
    {
        if ($m = $request->get('month')) {
            $start = Carbon::createFromFormat('Y-m', $m)->startOfMonth();
            return [$start, (clone $start)->endOfMonth()];
        }
        $from = $request->get('from') ? Carbon::parse($request->get('from'))->startOfDay() : Carbon::parse('2000-01-01');
        $to = $request->get('to') ? Carbon::parse($request->get('to'))->endOfDay() : now()->endOfDay();
        return [$from, $to];
    }

    public function kpi(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $inRange = fn ($q, $col = 'date') => $q->whereBetween($col, [$from, $to]);

        // ── costs (cached) ──
        $fuelByVeh = FleetFuelLog::whereBetween('date', [$from, $to])
            ->selectRaw('odoo_vehicle_id, SUM(amount) amt, SUM(liters) liters')->groupBy('odoo_vehicle_id')
            ->get()->keyBy('odoo_vehicle_id');
        $maintByVeh = FleetServiceLog::whereBetween('date', [$from, $to])
            ->selectRaw('odoo_vehicle_id, SUM(amount) amt')->groupBy('odoo_vehicle_id')
            ->get()->keyBy('odoo_vehicle_id');
        $accByVeh = FleetAccident::whereBetween('date', [$from, $to])
            ->selectRaw('odoo_vehicle_id, SUM(repair_cost) amt, COUNT(*) cnt')->groupBy('odoo_vehicle_id')
            ->get()->keyBy('odoo_vehicle_id');

        // contract cost from Odoo's native cost report (live read_group)
        $contractByVeh = [];
        try {
            $rg = $this->odoo->searchRead('fleet.vehicle.cost.report',
                [['cost_type', '=', 'contract'], ['date_start', '>=', $from->toDateString()], ['date_start', '<=', $to->toDateString()]],
                ['vehicle_id', 'cost'], 0, 0);
            foreach ($rg as $row) {
                $vid = OdooService::many2oneId($row['vehicle_id']);
                if ($vid) $contractByVeh[$vid] = ($contractByVeh[$vid] ?? 0) + (float) $row['cost'];
            }
        } catch (\Throwable) {
        }

        // distance per vehicle = last − first odometer reading in range
        $odo = FleetOdometerLog::whereBetween('date', [$from, $to])
            ->selectRaw('odoo_vehicle_id, MAX(value) mx, MIN(value) mn, COUNT(*) cnt')->groupBy('odoo_vehicle_id')
            ->get()->keyBy('odoo_vehicle_id');

        $vehicles = FleetVehicle::where('active', true)->orderBy('name')->get();
        $rows = [];
        $tot = ['fuel' => 0.0, 'maint' => 0.0, 'accident' => 0.0, 'contract' => 0.0, 'distance' => 0.0, 'liters' => 0.0];
        foreach ($vehicles as $v) {
            $oid = $v->odoo_id;
            $fuel = (float) ($fuelByVeh[$oid]->amt ?? 0);
            $liters = (float) ($fuelByVeh[$oid]->liters ?? 0);
            $maint = (float) ($maintByVeh[$oid]->amt ?? 0);
            $acc = (float) ($accByVeh[$oid]->amt ?? 0);
            $incidents = (int) ($accByVeh[$oid]->cnt ?? 0);
            $contract = (float) ($contractByVeh[$oid] ?? 0);
            $total = $fuel + $maint + $acc + $contract;
            $odoCnt = (int) ($odo[$oid]->cnt ?? 0);
            $distance = $odoCnt >= 2 ? max(0, (float) $odo[$oid]->mx - (float) $odo[$oid]->mn) : 0;
            $costPerKm = $distance > 0 ? round($total / $distance, 3) : null;

            $tot['fuel'] += $fuel; $tot['maint'] += $maint; $tot['accident'] += $acc;
            $tot['contract'] += $contract; $tot['distance'] += $distance; $tot['liters'] += $liters;

            if ($total > 0 || $incidents > 0 || $distance > 0) {
                $rows[] = [
                    'vehicle_id' => $v->id, 'name' => $v->name, 'plate' => $v->license_plate,
                    'fuel' => round($fuel, 2), 'maint' => round($maint, 2), 'accident' => round($acc, 2),
                    'contract' => round($contract, 2), 'total' => round($total, 2),
                    'distance' => round($distance, 1), 'cost_per_km' => $costPerKm,
                    'incidents' => $incidents, 'odo_readings' => $odoCnt,
                ];
            }
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totalCost = $tot['fuel'] + $tot['maint'] + $tot['accident'] + $tot['contract'];
        $activeCount = $vehicles->count();
        // utilization: vehicles with a usage in range ÷ active
        $usedCount = \App\Models\FleetVehicleUsage::whereBetween('date_picking', [$from, $to])
            ->distinct('odoo_vehicle_id')->count('odoo_vehicle_id');
        $downtime = FleetVehicle::where('active', false)->count()
            + FleetVehicle::where('active', true)->where('state_name', 'like', '%owngrad%')->count();

        return response()->json(['data' => [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'tiles' => [
                'total_cost'   => round($totalCost, 2),
                'fuel_cost'    => round($tot['fuel'], 2),
                'maint_cost'   => round($tot['maint'], 2),
                'accident_cost' => round($tot['accident'], 2),
                'contract_cost' => round($tot['contract'], 2),
                'distance'     => round($tot['distance'], 1),
                'cost_per_km'  => $tot['distance'] > 0 ? round($totalCost / $tot['distance'], 3) : null,
                'avg_kmpl'     => $tot['liters'] > 0 ? round($tot['distance'] / $tot['liters'], 2) : null,
                'active_vehicles' => $activeCount,
                'utilization_pct' => $activeCount ? round($usedCount / $activeCount * 100, 1) : 0,
                'open_claims'  => FleetAccident::whereIn('claim_state', ['filed', 'approved'])->count(),
                'downtime_count' => $downtime,
                'cost_per_km_estimate' => true, // sparse odometer
            ],
            'vehicles' => array_slice($rows, 0, 100),
            'trends' => $this->trends(),
        ]]);
    }

    /** Last 6 months cost + fuel + distance series. */
    private function trends(): array
    {
        $cost = []; $fuel = []; $dist = [];
        for ($i = 5; $i >= 0; $i--) {
            $s = now()->subMonths($i)->startOfMonth();
            $e = (clone $s)->endOfMonth();
            $label = $s->format('Y-m');
            $f = (float) FleetFuelLog::whereBetween('date', [$s, $e])->sum('amount');
            $m = (float) FleetServiceLog::whereBetween('date', [$s, $e])->sum('amount');
            $a = (float) FleetAccident::whereBetween('date', [$s, $e])->sum('repair_cost');
            $cost[] = ['label' => $label, 'value' => round($f + $m + $a, 2)];
            $fuel[] = ['label' => $label, 'value' => round($f, 2)];
            // distance: sum of per-vehicle (max-min) that month — approximate
            $od = FleetOdometerLog::whereBetween('date', [$s, $e])
                ->selectRaw('odoo_vehicle_id, MAX(value)-MIN(value) d, COUNT(*) c')->groupBy('odoo_vehicle_id')->get();
            $dist[] = ['label' => $label, 'value' => round($od->where('c', '>=', 2)->sum('d'), 1)];
        }
        return ['cost_by_month' => $cost, 'fuel_by_month' => $fuel, 'distance_by_month' => $dist];
    }

    public function drivers(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        // productivity from courier-daily
        $perf = CourierDaily::whereBetween('date', [$from, $to])
            ->selectRaw('odoo_employee_id, courier_name, SUM(ofd) ofd, SUM(delivered) delivered, SUM(present) present_days, COUNT(*) days')
            ->groupBy('odoo_employee_id', 'courier_name')->get();

        // accident penalties by driver name (accidents store driver_name)
        $accByName = FleetAccident::whereBetween('date', [$from, $to])
            ->selectRaw('driver_name, COUNT(*) cnt, SUM(CASE WHEN severity IN ("major","total_loss") THEN 1 ELSE 0 END) major')
            ->groupBy('driver_name')->get()->keyBy(fn ($r) => strtoupper(trim((string) $r->driver_name)));

        $out = [];
        foreach ($perf as $p) {
            if (!$p->odoo_employee_id && !$p->courier_name) continue;
            $ofd = (int) $p->ofd; $del = (int) $p->delivered;
            $base = $ofd > 0 ? ($del / $ofd) * 100 : 0;
            $acc = $accByName[strtoupper(trim((string) $p->courier_name))] ?? null;
            $penalty = $acc ? (15 * (int) $acc->cnt + 25 * (int) $acc->major) : 0;
            $score = $ofd > 0 ? max(0, min(100, round($base - $penalty))) : null;
            $out[] = [
                'name' => $p->courier_name, 'present_days' => (int) $p->present_days,
                'ofd' => $ofd, 'delivered' => $del,
                'performance' => $ofd > 0 ? round($base, 1) : null,
                'accidents' => $acc ? (int) $acc->cnt : 0,
                'score' => $score,
            ];
        }
        usort($out, fn ($a, $b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1));

        return response()->json(['data' => array_slice($out, 0, 100)]);
    }
}
