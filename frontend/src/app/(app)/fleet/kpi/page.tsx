"use client";

// Fleet KPI dashboard (mj_fleet_kpi): cost tiles, per-vehicle table, trend charts,
// driver scorecard. Read-only rollups. cost/km is an estimate (sparse odometer).

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, money, PageHeader, Spinner, StatCard, Table, Td, Th } from "@/components/ui";
import { ChartCard, ColumnChart } from "@/components/charts";

interface Tiles {
  total_cost: number; fuel_cost: number; maint_cost: number; accident_cost: number; contract_cost: number;
  distance: number; cost_per_km: number | null; avg_kmpl: number | null;
  active_vehicles: number; utilization_pct: number; open_claims: number; downtime_count: number;
  cost_per_km_estimate: boolean;
}
interface VehRow {
  vehicle_id: number; name: string; plate: string | null; fuel: number; maint: number; accident: number;
  contract: number; total: number; distance: number; cost_per_km: number | null; incidents: number; odo_readings: number;
}
interface Kpi {
  period: { from: string; to: string };
  tiles: Tiles;
  vehicles: VehRow[];
  trends: { cost_by_month: { label: string; value: number }[]; fuel_by_month: { label: string; value: number }[]; distance_by_month: { label: string; value: number }[] };
}
interface Driver { name: string | null; present_days: number; ofd: number; delivered: number; performance: number | null; accidents: number; score: number | null; }

function scoreTone(s: number | null): string {
  if (s == null) return "slate";
  if (s >= 80) return "green"; if (s >= 50) return "amber"; return "rose";
}

export default function FleetKpiPage() {
  const { t } = useI18n();
  const [k, setK] = useState<Kpi | null>(null);
  const [drivers, setDrivers] = useState<Driver[] | null>(null);
  const [month, setMonth] = useState("");

  useEffect(() => {
    api.get<{ data: Kpi }>(`/fleet/kpi${qs({ month })}`).then((r) => setK(r.data)).catch(() => setK(null));
    api.get<{ data: Driver[] }>(`/fleet/kpi/drivers${qs({ month })}`).then((r) => setDrivers(r.data)).catch(() => setDrivers([]));
  }, [month]);

  if (!k) return <Spinner />;
  const T = k.tiles;

  return (
    <div>
      <PageHeader kicker={t("nav.fleet")} title={t("fkpi.title")}>
        <input type="month" value={month} onChange={(e) => setMonth(e.target.value)}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900" />
      </PageHeader>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <StatCard label={t("fkpi.total_cost")} value={money(T.total_cost)} tone="brand" />
        <StatCard label={t("fkpi.cost_per_km")} value={T.cost_per_km != null ? money(T.cost_per_km) : "—"} tone="amber" hint={T.cost_per_km_estimate ? t("fkpi.estimate") : undefined} />
        <StatCard label={t("fleet.total_spend") + " · " + t("fleet.fuel")} value={money(T.fuel_cost)} />
        <StatCard label={t("fleet.repair_cost")} value={money(T.maint_cost)} />
        <StatCard label={t("fleet.accidents")} value={money(T.accident_cost)} tone="rose" />
        <StatCard label={t("fkpi.distance")} value={`${T.distance.toLocaleString()} km`} />
        <StatCard label={t("fkpi.utilization")} value={`${T.utilization_pct}%`} tone="green" />
        <StatCard label={t("fkpi.downtime")} value={T.downtime_count} tone="rose" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
        <ChartCard title={t("fkpi.cost_by_month")}><ColumnChart data={k.trends.cost_by_month} barClass="bg-brand-500" format={(v) => money(v)} /></ChartCard>
        <ChartCard title={t("fkpi.fuel_by_month")}><ColumnChart data={k.trends.fuel_by_month} barClass="bg-emerald-500" format={(v) => money(v)} /></ChartCard>
        <ChartCard title={t("fkpi.distance_by_month")}><ColumnChart data={k.trends.distance_by_month} barClass="bg-sky-500" format={(v) => `${Math.round(v).toLocaleString()}`} /></ChartCard>
      </div>

      <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("fkpi.by_vehicle")}</h2>
      <Table head={<><Th>{t("fleet.vehicle")}</Th><Th>{t("fleet.fuel")}</Th><Th>{t("fleet.repair_cost")}</Th><Th>{t("fleet.accidents")}</Th><Th>{t("common.total")}</Th><Th>{t("fkpi.distance")}</Th><Th>{t("fkpi.cost_per_km")}</Th></>}>
        {k.vehicles.length === 0 && <EmptyRow colSpan={7} />}
        {k.vehicles.map((v) => (
          <tr key={v.vehicle_id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
            <Td className="font-medium"><Link href={`/fleet/vehicles/${v.vehicle_id}`} className="hover:text-brand-600 hover:underline">{v.name}</Link>{v.plate && <span className="block text-xs text-slate-400">{v.plate}</span>}</Td>
            <Td className="tabular-nums">{money(v.fuel)}</Td>
            <Td className="tabular-nums">{money(v.maint)}</Td>
            <Td className="tabular-nums">{v.incidents ? <>{money(v.accident)} <span className="text-xs text-rose-500">({v.incidents})</span></> : "—"}</Td>
            <Td className="tabular-nums font-semibold">{money(v.total)}</Td>
            <Td className="tabular-nums text-xs">{v.distance ? `${v.distance.toLocaleString()} km` : "—"}</Td>
            <Td className="tabular-nums">{v.cost_per_km != null ? money(v.cost_per_km) : <span className="text-xs text-slate-400" title={t("fkpi.insufficient")}>—</span>}</Td>
          </tr>
        ))}
      </Table>

      <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mt-6 mb-2">{t("fkpi.driver_scorecard")}</h2>
      {!drivers ? <Spinner /> : (
        <Table head={<><Th>{t("fleet.driver")}</Th><Th>{t("cd.present")}</Th><Th>{t("cd.ofd")}</Th><Th>{t("cd.delivered")}</Th><Th>{t("cd.perf")}</Th><Th>{t("fleet.accidents")}</Th><Th>{t("fkpi.score")}</Th></>}>
          {drivers.length === 0 && <EmptyRow colSpan={7} />}
          {drivers.slice(0, 50).map((d, i) => (
            <tr key={i} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
              <Td className="font-medium text-slate-900 dark:text-slate-100">{d.name}</Td>
              <Td className="tabular-nums">{d.present_days}</Td>
              <Td className="tabular-nums">{d.ofd}</Td>
              <Td className="tabular-nums">{d.delivered}</Td>
              <Td className="tabular-nums">{d.performance != null ? `${d.performance}%` : "—"}</Td>
              <Td className="tabular-nums">{d.accidents || "—"}</Td>
              <Td>{d.score != null ? <Badge tone={scoreTone(d.score)}>{d.score}</Badge> : <span className="text-xs text-slate-400">n/a</span>}</Td>
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}
