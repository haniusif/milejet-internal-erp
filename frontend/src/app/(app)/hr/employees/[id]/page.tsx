"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { EmployeeDetail } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import EmployeeDocuments from "@/components/EmployeeDocuments";
import EmployeeForms from "@/components/EmployeeForms";
import {
  Badge,
  ErrorBox,
  leaveStateTone,
  money,
  payslipStateTone,
  Spinner,
  Table,
  Td,
  Th,
} from "@/components/ui";

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-slate-400 dark:text-slate-500 mb-0.5">{label}</dt>
      <dd className="text-sm text-slate-900 dark:text-slate-100">{value ?? "—"}</dd>
    </div>
  );
}

function InfoCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
      <h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{title}</h2>
      <dl className="space-y-3">{children}</dl>
    </div>
  );
}

const FAMILY_STATUSES = ["S", "M", "W", "D", "C"];

function contractStateTone(state: string): string {
  return { open: "green", pending: "amber", draft: "slate", close: "slate", cancel: "rose" }[state] ?? "slate";
}

/**
 * Suspension / access management — mirrors the recommended Odoo flows:
 * archive employee (keeps history), disable the login account, end contract.
 */
function ManageCard({ emp, onChanged }: { emp: EmployeeDetail; onChanged: () => void }) {
  const { t } = useI18n();
  const [access, setAccess] = useState<{ has_user: boolean; user_active: boolean; login: string | null } | null>(
    null,
  );
  const [endDate, setEndDate] = useState("");
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: { has_user: boolean; user_active: boolean; login: string | null } }>(
        `/hr/employees/${emp.id}/access`,
      )
      .then((r) => setAccess(r.data))
      .catch(() => setAccess(null));
  }, [emp.id]);

  async function run(key: string, fn: () => Promise<unknown>, confirmMsg?: string) {
    if (confirmMsg && !confirm(confirmMsg)) return;
    setBusy(key);
    setError(null);
    try {
      await fn();
      onChanged();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(null);
    }
  }

  const btn =
    "h-8 px-3 rounded-md text-xs font-medium border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50";

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
      <h2 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">{t("hr.manage_title")}</h2>
      {error && <p className="text-xs text-rose-600 mb-2">{error}</p>}
      <div className="space-y-3 text-sm">
        {/* Option 1 — archive / restore */}
        <div className="flex items-center justify-between gap-2">
          <span className="text-slate-600 dark:text-slate-300">
            {t("hr.manage_employee_state")}{" "}
            <Badge tone={emp.active ? "green" : "rose"}>
              {emp.active ? t("hr.state_active") : t("hr.state_archived")}
            </Badge>
          </span>
          {emp.active ? (
            <button
              disabled={busy !== null}
              onClick={() =>
                run("archive", () => api.post(`/hr/employees/${emp.id}/archive`, {}), t("hr.confirm_archive"))
              }
              className={`${btn} text-rose-600 dark:text-rose-400`}
            >
              {busy === "archive" ? "…" : t("hr.archive")}
            </button>
          ) : (
            <button
              disabled={busy !== null}
              onClick={() => run("restore", () => api.post(`/hr/employees/${emp.id}/restore`, {}))}
              className={`${btn} text-emerald-600 dark:text-emerald-400`}
            >
              {busy === "restore" ? "…" : t("hr.restore")}
            </button>
          )}
        </div>

        {/* Option 2/4 — login account */}
        <div className="flex items-center justify-between gap-2">
          <span className="text-slate-600 dark:text-slate-300 min-w-0">
            {t("hr.manage_login")}{" "}
            {access === null ? (
              <span className="text-xs text-slate-400">…</span>
            ) : !access.has_user ? (
              <Badge tone="slate">{t("hr.no_account")}</Badge>
            ) : (
              <Badge tone={access.user_active ? "green" : "rose"}>
                {access.user_active ? t("hr.state_active") : t("hr.state_disabled")}
              </Badge>
            )}
            {access?.login && <span className="block text-xs text-slate-400 truncate">{access.login}</span>}
          </span>
          {access?.has_user && (
            <button
              disabled={busy !== null}
              onClick={() =>
                run(
                  "access",
                  async () => {
                    await api.post(`/hr/employees/${emp.id}/access`, { enabled: !access.user_active });
                    setAccess({ ...access, user_active: !access.user_active });
                  },
                  access.user_active ? t("hr.confirm_disable_login") : undefined,
                )
              }
              className={`${btn} ${access.user_active ? "text-rose-600 dark:text-rose-400" : "text-emerald-600 dark:text-emerald-400"}`}
            >
              {busy === "access" ? "…" : access.user_active ? t("hr.disable_login") : t("hr.enable_login")}
            </button>
          )}
        </div>

        {/* Attendance geofence — restricted to office vs punch from anywhere */}
        <div className="flex items-center justify-between gap-2">
          <span className="text-slate-600 dark:text-slate-300">
            {t("hr.manage_geofence")}{" "}
            <Badge tone={emp.geofence_exempt ? "amber" : "green"}>
              {emp.geofence_exempt ? t("hr.geofence_anywhere") : t("hr.geofence_restricted")}
            </Badge>
          </span>
          <button
            disabled={busy !== null}
            onClick={() =>
              run(
                "geofence",
                () => api.post(`/hr/employees/${emp.id}/geofence`, { exempt: !emp.geofence_exempt }),
                emp.geofence_exempt ? undefined : t("hr.confirm_geofence_exempt"),
              )
            }
            className={`${btn} ${emp.geofence_exempt ? "text-emerald-600 dark:text-emerald-400" : "text-amber-600 dark:text-amber-400"}`}
          >
            {busy === "geofence"
              ? "…"
              : emp.geofence_exempt
                ? t("hr.geofence_restrict")
                : t("hr.geofence_exempt")}
          </button>
        </div>

        {/* Option 3 — end contract */}
        {emp.contract && ["open", "pending", "draft"].includes(emp.contract.state) && (
          <div className="flex items-center justify-between gap-2 flex-wrap">
            <span className="text-slate-600 dark:text-slate-300">{t("hr.manage_contract")}</span>
            <span className="flex items-center gap-2">
              <input
                type="date"
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="h-8 px-2 border border-slate-200 dark:border-slate-700 rounded-md text-xs bg-white dark:bg-slate-900"
              />
              <button
                disabled={busy !== null || !endDate}
                onClick={() =>
                  run(
                    "contract",
                    () => api.post(`/hr/employees/${emp.id}/end-contract`, { date_end: endDate }),
                    t("hr.confirm_end_contract"),
                  )
                }
                className={`${btn} text-amber-600 dark:text-amber-400`}
              >
                {busy === "contract" ? "…" : t("hr.end_contract")}
              </button>
            </span>
          </div>
        )}
      </div>
    </div>
  );
}

export default function EmployeeProfile() {
  const { t } = useI18n();
  const { can } = useAuth();
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const [emp, setEmp] = useState<EmployeeDetail | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function destroy() {
    if (!confirm(t("common.confirm_delete"))) return;
    try {
      await api.del(`/hr/employees/${id}`);
      router.push("/hr/employees");
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  const load = useCallback(() => {
    api
      .get<{ data: EmployeeDetail }>(`/hr/employees/${id}`)
      .then((r) => setEmp(r.data))
      .catch((e) => setError(e instanceof ApiError ? e.message : t("common.error")));
  }, [id, t]);

  useEffect(load, [load]);

  if (error) return <ErrorBox message={error} />;
  if (!emp) return <Spinner />;

  return (
    <div>
      {/* Hero */}
      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden mb-5">
        <div className="h-20 bg-gradient-to-r from-brand-700 via-brand-800 to-brand-900" />
        <div className="px-6 pb-5">
          <div className="flex flex-wrap items-end gap-4 -mt-10">
            {emp.avatar ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={emp.avatar}
                alt={emp.name}
                className="w-20 h-20 rounded-2xl object-cover ring-4 ring-white dark:ring-slate-900 shadow-soft bg-white"
              />
            ) : (
              <span className="grid place-items-center w-20 h-20 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white text-2xl font-bold ring-4 ring-white dark:ring-slate-900 shadow-soft">
                {emp.name.charAt(0)}
              </span>
            )}
            <div className="pb-1 min-w-0 flex-1">
              <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 break-words">
                {emp.name}
              </h1>
              <p className="text-sm text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-2">
                <span>{emp.job_title ?? "—"}</span>
                {emp.emp_code && (
                  <span className="font-mono text-xs px-2 py-0.5 rounded bg-brand-50 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 ring-1 ring-brand-200 dark:ring-brand-800">
                    {emp.emp_code}
                  </span>
                )}
                {emp.contract_status && (
                  <Badge tone={emp.contract_status.toLowerCase() === "active" ? "green" : "amber"}>
                    {emp.contract_status}
                  </Badge>
                )}
              </p>
            </div>
            <div className="flex items-center gap-2 pb-1 ms-auto shrink-0">
              {can("employees.write") && (
                <Link
                  href={`/hr/employees/${emp.id}/edit`}
                  className="h-9 px-4 inline-flex items-center rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-medium"
                >
                  {t("common.edit")}
                </Link>
              )}
              {can("employees.delete") && (
                <button
                  onClick={destroy}
                  className="h-9 px-3 rounded-md bg-rose-50 dark:bg-rose-950/50 hover:bg-rose-100 dark:hover:bg-rose-900/50 text-rose-600 dark:text-rose-400 text-sm font-medium"
                >
                  {t("common.delete")}
                </button>
              )}
            </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div className="space-y-5">
          {can("employees.write") && <ManageCard emp={emp} onChanged={load} />}

          {/* Contact + org */}
          <InfoCard title={t("hr.contact")}>
            <Row label={t("auth.email")} value={emp.work_email} />
            <Row label={t("hr.work_phone")} value={emp.work_phone} />
            <Row label={t("hr.mobile_phone")} value={emp.mobile_phone} />
            <Row label={t("hr.work_location")} value={emp.work_location} />
            <Row label={t("hr.department")} value={emp.department} />
            <Row label={t("co.company_branch")} value={emp.company} />
            <Row label={t("hr.manager")} value={emp.manager} />
          </InfoCard>

          {/* Sensitive (self or gated) */}
          {emp.sensitive && (
            <>
              <InfoCard title={t("hr.section_personal")}>
                <Row label={t("hr.birthday")} value={emp.sensitive.birthday} />
                <Row
                  label={t("hr.family_status")}
                  value={
                    emp.sensitive.family_status
                      ? FAMILY_STATUSES.includes(emp.sensitive.family_status)
                        ? t(`hr.family_status_${emp.sensitive.family_status}`)
                        : emp.sensitive.family_status
                      : null
                  }
                />
                <Row
                  label={t("hr.nationality")}
                  value={
                    emp.sensitive.nationality
                      ? `${emp.sensitive.nationality}${emp.sensitive.nationality_code ? ` (${emp.sensitive.nationality_code})` : ""}`
                      : null
                  }
                />
                <Row label={t("hr.region")} value={emp.sensitive.region} />
                <Row label={t("hr.iqama")} value={emp.sensitive.iqama_id} />
                <Row label={t("hr.iqama_expiry")} value={emp.sensitive.iqama_expiry_date} />
                <Row label={t("hr.passport")} value={emp.sensitive.passport_id} />
                <Row label={t("hr.passport_expiry")} value={emp.sensitive.passport_expiry_date} />
                <Row label={t("hr.license_expiry")} value={emp.sensitive.license_expiry_date} />
                <Row label={t("hr.cchi_card_type")} value={emp.sensitive.cchi_card_type} />
              </InfoCard>

              <InfoCard title={t("hr.section_employment")}>
                <Row label={t("hr.date_of_joining")} value={emp.sensitive.date_of_joining} />
                <Row
                  label={t("hr.contract_type")}
                  value={
                    ["fixed_term", "open_ended"].includes(emp.sensitive.contract_type ?? "")
                      ? t(`hr.contract_type_${emp.sensitive.contract_type}`)
                      : emp.sensitive.contract_type
                  }
                />
                {emp.sensitive.contract_duration_months != null && (
                  <Row label={t("hr.contract_duration")} value={t(`hr.duration_${emp.sensitive.contract_duration_months}`)} />
                )}
                <Row label={t("hr.contract_end_date")} value={emp.sensitive.contract_end_date} />
                {emp.sensitive.work_schedule && (
                  <Row label={t("hr.work_schedule")} value={t(`hr.work_schedule_${emp.sensitive.work_schedule}`)} />
                )}
                {emp.sensitive.notice_period_days != null && (
                  <Row label={t("hr.notice_period")} value={String(emp.sensitive.notice_period_days)} />
                )}
                {emp.sensitive.probation_period_days != null && (
                  <Row label={t("hr.probation_period")} value={String(emp.sensitive.probation_period_days)} />
                )}
                {emp.sensitive.auto_renewal != null && (
                  <Row label={t("hr.auto_renewal")} value={emp.sensitive.auto_renewal ? t("common.yes") : t("common.no")} />
                )}
              </InfoCard>

              <InfoCard title={t("hr.section_salary")}>
                <Row
                  label={t("hr.total_salary")}
                  value={
                    emp.sensitive.total_salary != null
                      ? `${money(emp.sensitive.total_salary)} ${t("common.sar")}`
                      : null
                  }
                />
                <Row
                  label={t("hr.basic_salary")}
                  value={
                    emp.sensitive.basic_salary != null
                      ? `${money(emp.sensitive.basic_salary)} ${t("common.sar")}`
                      : null
                  }
                />
                {(
                  [
                    ["allowance_house", "hr.allowance_house"],
                    ["allowance_rent", "hr.allowance_rent"],
                    ["allowance_transport", "hr.allowance_transport"],
                    ["allowance_car", "hr.allowance_car"],
                    ["allowance_special", "hr.allowance_special"],
                    ["allowance_project", "hr.allowance_project"],
                    ["allowance_food", "hr.allowance_food"],
                    ["allowance_other", "hr.allowance_other"],
                    ["ot_allowance", "hr.ot_allowance"],
                  ] as const
                )
                  .filter(([k]) => {
                    const v = emp.sensitive![k];
                    return v != null && Number(v) > 0;
                  })
                  .map(([k, label]) => (
                    <Row
                      key={k}
                      label={t(label)}
                      value={`${money(emp.sensitive![k]!)} ${t("common.sar")}`}
                    />
                  ))}
                {emp.sensitive.gosi_pm != null && Number(emp.sensitive.gosi_pm) > 0 && (
                  <>
                    <Row label={t("hr.gosi")} value={`${money(emp.sensitive.gosi_pm)} ${t("common.sar")}`} />
                    {emp.sensitive.total_salary != null && (
                      <Row
                        label={t("hr.net_salary")}
                        value={`${money(Number(emp.sensitive.total_salary) - Number(emp.sensitive.gosi_pm))} ${t("common.sar")}`}
                      />
                    )}
                  </>
                )}
              </InfoCard>

              {emp.contract && (
                <InfoCard title={t("hr.section_contract")}>
                  <Row label={t("common.name")} value={emp.contract.name} />
                  <Row
                    label={t("hr.total_salary")}
                    value={`${money(emp.contract.wage)} ${t("common.sar")}`}
                  />
                  <Row label={t("hr.date_of_joining")} value={emp.contract.date_start} />
                  <Row label={t("hr.contract_end_date")} value={emp.contract.date_end} />
                  <Row
                    label={t("common.status")}
                    value={
                      <Badge tone={contractStateTone(emp.contract.state)}>{emp.contract.state}</Badge>
                    }
                  />
                  <div className="flex items-center gap-3 pt-2">
                    <a
                      href={`/api/v1/hr/contracts/${emp.contract.id}/pdf`}
                      target="_blank"
                      rel="noreferrer"
                      className="text-brand-600 hover:underline text-xs"
                    >
                      {t("con.download_pdf")}
                    </a>
                    {emp.contract.signed ? (
                      <Badge tone="green">{t("con.signed")}</Badge>
                    ) : (
                      <button
                        onClick={async () => {
                          if (!confirm(t("con.confirm_sign"))) return;
                          await api.post(`/hr/contracts/${emp.contract!.id}/sign`, {});
                          load();
                        }}
                        className="text-emerald-600 hover:underline text-xs"
                      >
                        {t("con.sign")}
                      </button>
                    )}
                  </div>
                </InfoCard>
              )}
            </>
          )}
        </div>

        <div className="lg:col-span-2 space-y-5">
          {/* Recent leaves */}
          <div>
            <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">
              {t("nav.leaves")}
            </h2>
            <Table
              head={
                <>
                  <Th>{t("leave.type")}</Th>
                  <Th>{t("leave.from")}</Th>
                  <Th>{t("leave.to")}</Th>
                  <Th>{t("leave.days")}</Th>
                  <Th>{t("common.status")}</Th>
                </>
              }
            >
              {(emp.recent_leaves ?? []).length === 0 && (
                <tr>
                  <td colSpan={5} className="px-4 py-6 text-center text-slate-400 text-sm">
                    {t("common.empty")}
                  </td>
                </tr>
              )}
              {(emp.recent_leaves ?? []).map((l) => (
                <tr key={l.id}>
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
          </div>

          {/* Recent payslips (self or payroll gate) */}
          {emp.recent_payslips && (
            <div>
              <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">
                {t("nav.payslips")}
              </h2>
              <Table
                head={
                  <>
                    <Th>{t("pay.reference")}</Th>
                    <Th>{t("pay.period")}</Th>
                    <Th>{t("pay.net")}</Th>
                    <Th>{t("common.status")}</Th>
                  </>
                }
              >
                {emp.recent_payslips.length === 0 && (
                  <tr>
                    <td colSpan={4} className="px-4 py-6 text-center text-slate-400 text-sm">
                      {t("common.empty")}
                    </td>
                  </tr>
                )}
                {emp.recent_payslips.map((p) => (
                  <tr key={p.id}>
                    <Td className="font-mono text-xs">{p.number ?? "—"}</Td>
                    <Td className="text-xs">
                      {p.date_from} → {p.date_to}
                    </Td>
                    <Td className="font-bold text-emerald-700 tabular-nums">{money(p.net_total)}</Td>
                    <Td>
                      <Badge tone={payslipStateTone(p.state)}>{t(`pay.state.${p.state}`)}</Badge>
                    </Td>
                  </tr>
                ))}
              </Table>
            </div>
          )}

          {/* Employee documents */}
          <EmployeeDocuments employeeOdooId={emp.odoo_id} />

          {/* HR forms: warnings / sick leave / end of service */}
          {can("employees.write") && <EmployeeForms employeeOdooId={emp.odoo_id} />}

          {/* Direct reports */}
          {(emp.reports ?? []).length > 0 && (
            <div>
              <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">
                {t("hr.direct_reports")} ({emp.reports!.length})
              </h2>
              <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl divide-y divide-slate-100 dark:divide-slate-800">
                {emp.reports!.map((r) => (
                  <div key={r.id} className="flex items-center gap-3 px-4 py-2.5">
                    <span className="grid place-items-center w-7 h-7 rounded-full bg-brand-600 text-white text-xs font-bold">
                      {r.name.charAt(0)}
                    </span>
                    <div className="min-w-0">
                      <p className="text-sm text-slate-900 dark:text-slate-100 truncate">{r.name}</p>
                      {r.job_title && <p className="text-xs text-slate-400 truncate">{r.job_title}</p>}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
