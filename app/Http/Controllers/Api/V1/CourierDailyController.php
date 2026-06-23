<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourierDaily;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Courier daily performance (mj.courier.daily) — attendance + delivery stats.
 */
class CourierDailyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $scope = function () use ($request) {
            $q = CourierDaily::query();
            if ($m = $request->get('month')) $q->where('month', $m);
            if ($city = $request->get('city')) $q->where('city', $city);
            if ($search = trim((string) $request->get('q', ''))) {
                $q->where(fn ($w) => $w->where('courier_name', 'like', "%{$search}%")
                    ->orWhere('vehicle_plate', 'like', "%{$search}%"));
            }
            return $q;
        };

        $page = $scope()->orderByDesc('date')->orderBy('courier_name')
            ->paginate(min((int) $request->get('per_page', 50), 200))
            ->withQueryString()->through(fn ($d) => [
                'id'            => $d->id,
                'date'          => $d->date?->toDateString(),
                'courier_name'  => $d->courier_name,
                'vehicle_plate' => $d->vehicle_plate,
                'city'          => $d->city,
                'present'       => $d->present,
                'ofd'           => $d->ofd,
                'delivered'     => $d->delivered,
                'performance'   => (float) $d->performance,
            ]);

        return response()->json($page->toArray() + [
            'months' => CourierDaily::distinct()->orderByDesc('month')->pluck('month')->filter()->values(),
            'totals' => [
                'rows'        => (clone $scope())->count(),
                'present'     => (clone $scope())->where('present', true)->count(),
                'ofd'         => (int) (clone $scope())->sum('ofd'),
                'delivered'   => (int) (clone $scope())->sum('delivered'),
            ],
        ]);
    }
}
