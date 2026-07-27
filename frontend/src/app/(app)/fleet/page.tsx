"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { FleetVehicle, Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, PageHeader, Pagination, Spinner, StatCard, Table, Td, Th } from "@/components/ui";

type VehiclePage = Paginated<FleetVehicle> & {
  states: { odoo_id: number; name: string }[];
  stats: { total: number; assigned: number; unassigned: number; services_open: number };
};

export default function FleetPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const [page, setPage] = useState<VehiclePage | null>(null);
  const [q, setQ] = useState("");
  const [stateId, setStateId] = useState("");
  const [pageNum, setPageNum] = useState(1);
  const [alerts, setAlerts] = useState<{
    inspection: { vehicle_id: number; vehicle: string; expires: string; days_left: number }[];
    contracts: { vehicle_id: number | null; vehicle: string; name: string; expires: string; days_left: number }[];
    licence: { employee_id: number; driver: string; expires: string; days_left: number }[];
    counts: { inspection: number; contracts: number; licence: number };
  } | null>(null);

  useEffect(() => {
    api
      .get<VehiclePage>(`/fleet/vehicles${qs({ q, state_id: stateId, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [q, stateId, pageNum]);

  useEffect(() => {
    api.get<{ data: typeof alerts }>("/fleet/alerts").then((r) => setAlerts(r.data)).catch(() => {});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const hasAlerts = alerts && (alerts.counts.inspection + alerts.counts.contracts + alerts.counts.licence) > 0;

  return (
    <div>
      <PageHeader kicker={t("nav.fleet")} title={t("nav.vehicles")}>
        {can("fleet.write") && (
          <Link href="/fleet/vehicles/new" className="h-9 px-4 inline-flex items-center rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">
            + {t("fleet.new_vehicle")}
          </Link>
        )}
      </PageHeader>

      {hasAlerts && (
        <div className="mb-5 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 p-4">
          <p className="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-2">⚠ {t("fleet.compliance_alerts")}</p>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1">
            {alerts!.inspection.map((a) => (
              <Link key={`i${a.vehicle_id}`} href={`/fleet/vehicles/${a.vehicle_id}`} className="text-sm text-amber-900 dark:text-amber-200 hover:underline flex justify-between gap-2">
                <span>🔧 {t("fleet.insp_expiring", { v: a.vehicle })}</span><span className="tabular-nums whitespace-nowrap">{t("alert.in_days", { d: a.days_left })}</span>
              </Link>
            ))}
            {alerts!.contracts.map((a, i) => (
              <span key={`c${i}`} className="text-sm text-amber-900 dark:text-amber-200 flex justify-between gap-2">
                <span>📄 {a.vehicle} — {a.name}</span><span className="tabular-nums whitespace-nowrap">{t("alert.in_days", { d: a.days_left })}</span>
              </span>
            ))}
            {alerts!.licence.map((a) => (
              <span key={`l${a.employee_id}`} className="text-sm text-amber-900 dark:text-amber-200 flex justify-between gap-2">
                <span>🪪 {t("fleet.licence_expiring", { n: a.driver })}</span><span className="tabular-nums whitespace-nowrap">{t("alert.in_days", { d: a.days_left })}</span>
              </span>
            ))}
          </div>
        </div>
      )}

      {page && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
          <StatCard label={t("common.total")} value={page.stats.total} tone="brand" />
          <StatCard label={t("fleet.assigned")} value={page.stats.assigned} tone="green" />
          <StatCard label={t("fleet.unassigned")} value={page.stats.unassigned} />
          <StatCard label={t("fleet.open_services")} value={page.stats.services_open} tone="amber" />
        </div>
      )}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
        <input
          value={q}
          onChange={(e) => {
            setQ(e.target.value);
            setPageNum(1);
          }}
          placeholder={t("common.search")}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm flex-1 min-w-40 bg-white dark:bg-slate-900"
        />
        <select
          value={stateId}
          onChange={(e) => {
            setStateId(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("common.all")}</option>
          {(page?.states ?? []).map((s) => (
            <option key={s.odoo_id} value={s.odoo_id}>
              {s.name}
            </option>
          ))}
        </select>
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("fleet.vehicle")}</Th>
                <Th>{t("fleet.plate")}</Th>
                <Th>{t("fleet.driver")}</Th>
                <Th>{t("fleet.odometer")}</Th>
                <Th>{t("fleet.state")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={5} />}
            {page.data.map((v) => (
              <tr key={v.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-medium text-slate-900 dark:text-slate-100">
                  <Link href={`/fleet/vehicles/${v.id}`} className="hover:text-brand-600 hover:underline">
                    {v.name}
                  </Link>
                </Td>
                <Td className="font-mono text-xs">{v.license_plate ?? "—"}</Td>
                <Td>{v.driver_name ?? "—"}</Td>
                <Td className="tabular-nums">
                  {Number(v.odometer).toLocaleString()} {v.odometer_unit ?? "km"}
                </Td>
                <Td>{v.state_name ? <Badge tone="brand">{v.state_name}</Badge> : "—"}</Td>
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}
