"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api } from "@/lib/api";
import type { Leave } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, leaveStateTone, money, PageHeader, Spinner, StatCard, Table, Td, Th } from "@/components/ui";
import { ChartCard, ChartPoint, ColumnChart, HBarChart } from "@/components/charts";

interface Dashboard {
  scope: "company" | "self";
  stats: Record<string, number | boolean>;
  kpis?: {
    payroll_month: number;
    payroll_month_label: string;
    contracts_expiring: number;
    docs_expiring: number;
    on_leave_today: number;
  };
  charts?: {
    by_department: ChartPoint[];
    by_nationality: ChartPoint[];
    attendance_week: ChartPoint[];
    leaves_by_month: ChartPoint[];
    payroll_by_month: ChartPoint[];
  };
  recent_leaves: Leave[];
}

interface Alerts {
  probation_ending: { employee_id: number; name: string; ends_on: string; days_left: number }[];
  contracts_expiring: { contract_id: number; employee_name: string; ends_on: string; days_left: number }[];
}

export default function HrDashboard() {
  const { t } = useI18n();
  const { user, can } = useAuth();
  const [data, setData] = useState<Dashboard | null>(null);
  const [alerts, setAlerts] = useState<Alerts | null>(null);

  useEffect(() => {
    api.get<Dashboard>("/hr/dashboard").then(setData).catch(() => setData(null));
    if (can("hr.view_all")) {
      api.get<{ data: Alerts }>("/hr/alerts").then((r) => setAlerts(r.data)).catch(() => {});
    }
  }, [can]);

  if (!data) return <Spinner />;

  const hasAlerts = alerts && (alerts.probation_ending.length > 0 || alerts.contracts_expiring.length > 0);

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("hr.dashboard")} />

      {hasAlerts && (
        <div className="mb-5 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 p-4">
          <p className="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-2">⚠ {t("alert.title")}</p>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1">
            {alerts!.probation_ending.map((p) => (
              <Link
                key={`p${p.employee_id}`}
                href={`/hr/employees/${p.employee_id}`}
                className="text-sm text-amber-900 dark:text-amber-200 hover:underline flex justify-between gap-2"
              >
                <span>🧪 {t("alert.probation", { name: p.name })}</span>
                <span className="tabular-nums whitespace-nowrap">{t("alert.in_days", { d: p.days_left })}</span>
              </Link>
            ))}
            {alerts!.contracts_expiring.map((c) => (
              <Link
                key={`c${c.contract_id}`}
                href="/hr/contracts"
                className="text-sm text-amber-900 dark:text-amber-200 hover:underline flex justify-between gap-2"
              >
                <span>📄 {t("alert.contract", { name: c.employee_name })}</span>
                <span className="tabular-nums whitespace-nowrap">{t("alert.in_days", { d: c.days_left })}</span>
              </Link>
            ))}
          </div>
        </div>
      )}

      {data.scope === "company" ? (
        <>
          <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mb-3">
            <Link href="/hr/employees" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
              <StatCard label={t("hr.active_employees")} value={Number(data.stats.employees)} tone="brand" />
            </Link>
            <Link href="/hr/departments" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
              <StatCard label={t("hr.departments")} value={Number(data.stats.departments)} />
            </Link>
            <Link href="/hr/leaves" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
              <StatCard label={t("hr.pending_leaves")} value={Number(data.stats.pending_leaves)} tone="amber" />
            </Link>
            <Link href="/hr/leaves" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
              <StatCard label={t("hr.approved_leaves")} value={Number(data.stats.approved_leaves)} tone="green" />
            </Link>
            <Link href="/hr/attendance" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
              <StatCard label={t("hr.today_attendance")} value={Number(data.stats.today_attendance)} />
            </Link>
          </div>

          {data.kpis && (
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
              <Link href="/hr/payslips" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
                <StatCard
                  label={t("hr.kpi_payroll", { month: data.kpis.payroll_month_label })}
                  value={money(data.kpis.payroll_month)}
                  tone="green"
                  hint={t("hr.hint_kpi_payroll")}
                />
              </Link>
              <Link href="/hr/contracts" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
                <StatCard
                  label={t("hr.kpi_contracts_expiring")}
                  value={data.kpis.contracts_expiring}
                  tone="amber"
                  hint={t("hr.hint_kpi_contracts")}
                />
              </Link>
              <Link href="/hr/employees" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
                <StatCard
                  label={t("hr.kpi_docs_expiring")}
                  value={data.kpis.docs_expiring}
                  tone="rose"
                  hint={t("hr.hint_kpi_docs")}
                />
              </Link>
              <Link href="/hr/leaves" className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700">
                <StatCard
                  label={t("leave.on_leave_today")}
                  value={data.kpis.on_leave_today}
                  hint={t("leave.hint_today")}
                />
              </Link>
            </div>
          )}

          {data.charts && (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
              <ChartCard title={t("hr.chart_by_department")}>
                <HBarChart data={data.charts.by_department} />
              </ChartCard>
              <ChartCard title={t("hr.chart_by_nationality")}>
                <HBarChart data={data.charts.by_nationality} barClass="bg-accent-500" />
              </ChartCard>
              <ChartCard title={t("hr.chart_attendance_week")}>
                <ColumnChart data={data.charts.attendance_week} />
              </ChartCard>
              <ChartCard title={t("hr.chart_leaves_by_month")}>
                <ColumnChart data={data.charts.leaves_by_month} barClass="bg-amber-500" />
              </ChartCard>
              <div className="lg:col-span-2">
                <ChartCard title={t("hr.chart_payroll_by_month")}>
                  <ColumnChart
                    data={data.charts.payroll_by_month}
                    barClass="bg-emerald-500"
                    format={(v) => money(v)}
                  />
                </ChartCard>
              </div>
            </div>
          )}
        </>
      ) : (
        <div className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
          <StatCard label={t("hr.pending_leaves")} value={Number(data.stats.pending_leaves)} tone="amber" />
          <StatCard label={t("hr.approved_leaves")} value={Number(data.stats.approved_leaves)} tone="green" />
          <StatCard
            label={t("att.currently_in")}
            value={data.stats.checked_in ? "✓" : "—"}
            tone={data.stats.checked_in ? "green" : "default"}
          />
        </div>
      )}

      <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">
        {t("hr.recent_leave_requests")}
      </h2>
      <Table
        head={
          <>
            <Th>{t("hr.employee")}</Th>
            <Th>{t("leave.type")}</Th>
            <Th>{t("leave.from")}</Th>
            <Th>{t("leave.to")}</Th>
            <Th>{t("leave.days")}</Th>
            <Th>{t("common.status")}</Th>
          </>
        }
      >
        {data.recent_leaves.length === 0 && <EmptyRow colSpan={6} />}
        {data.recent_leaves.map((l) => (
          <tr key={l.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
            <Td className="font-medium text-slate-900 dark:text-slate-100">{l.employee_name}</Td>
            <Td>{l.leave_type ?? "—"}</Td>
            <Td>{l.date_from}</Td>
            <Td>{l.date_to}</Td>
            <Td>{l.number_of_days}</Td>
            <Td>
              <Badge tone={leaveStateTone(l.state)}>{t(`leave.state.${l.state}`)}</Badge>
            </Td>
          </tr>
        ))}
      </Table>

      {!can("hr.view_all") && user?.employee && (
        <p className="mt-4 text-sm">
          <Link href={`/hr/employees/${user.employee.id}`} className="text-brand-600 hover:underline">
            {t("nav.my_profile")} →
          </Link>
        </p>
      )}
    </div>
  );
}
