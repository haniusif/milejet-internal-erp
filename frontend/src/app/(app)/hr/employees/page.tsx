"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { Department, EmployeeSummary, Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ExportExcelButton, PageHeader, Pagination, Spinner, StatCard, Table, Td, Th } from "@/components/ui";

type EmployeePage = Paginated<EmployeeSummary> & {
  totals?: {
    active: number;
    suspended: number;
    iqama_expiry: number;
    license_expiry: number;
    passport_expiry: number;
  };
};

const EXPIRY_FIELD: Record<string, "iqama_expiry_date" | "license_expiry_date" | "passport_expiry_date"> = {
  iqama_expiry: "iqama_expiry_date",
  license_expiry: "license_expiry_date",
  passport_expiry: "passport_expiry_date",
};

export default function EmployeesPage() {
  const { t } = useI18n();
  const { user, loading, can } = useAuth();
  const router = useRouter();

  const [page, setPage] = useState<EmployeePage | null>(null);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [q, setQ] = useState("");
  const [dept, setDept] = useState("");
  const [filter, setFilter] = useState("");
  const [pageNum, setPageNum] = useState(1);

  const hrStaff = can("hr.view_all");

  // Employee-only users have no directory — go straight to their profile.
  useEffect(() => {
    if (!loading && user && !hrStaff) {
      router.replace(user.employee ? `/hr/employees/${user.employee.id}` : "/hr");
    }
  }, [loading, user, hrStaff, router]);

  useEffect(() => {
    if (!hrStaff) return;
    api
      .get<EmployeePage>(
        `/hr/employees${qs({ q, department_id: dept, filter, page: pageNum })}`
      )
      .then(setPage)
      .catch(() => setPage(null));
  }, [hrStaff, q, dept, filter, pageNum]);

  useEffect(() => {
    if (!hrStaff) return;
    api.get<{ data: Department[] }>("/hr/departments").then((r) => setDepartments(r.data)).catch(() => {});
  }, [hrStaff]);

  if (!hrStaff) return <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.employees")}>
        <ExportExcelButton path="/hr/employees/export" params={{ q, department_id: dept, filter }} />
        <Link
          href="/hr/org-chart"
          className="h-9 px-3 inline-flex items-center rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium"
        >
          {t("nav.org_chart")}
        </Link>
        {can("employees.write") && (
          <Link
            href="/hr/employees/new"
            className="h-9 px-4 inline-flex items-center rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
          >
            + {t("hr.new_employee")}
          </Link>
        )}
      </PageHeader>

      {page?.totals && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
          {(
            [
              ["", "hr.card_employees", page.totals.active, "green", "hr.hint_employees"],
              ["suspended", "hr.card_suspended", page.totals.suspended, "rose", "hr.hint_suspended"],
              ["iqama_expiry", "hr.card_iqama_expiry", page.totals.iqama_expiry, "amber", "hr.hint_iqama_expiry"],
              ["license_expiry", "hr.card_license_expiry", page.totals.license_expiry, "amber", "hr.hint_license_expiry"],
              ["passport_expiry", "hr.card_passport_expiry", page.totals.passport_expiry, "amber", "hr.hint_passport_expiry"],
            ] as const
          ).map(([f, label, value, tone, hint]) => (
            <button
              key={label}
              type="button"
              onClick={() => {
                setFilter(f);
                setPageNum(1);
              }}
              className={`text-start rounded-xl transition ${
                filter === f ? "ring-2 ring-brand-500" : "hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700"
              }`}
            >
              <StatCard label={t(label)} value={value} tone={tone} hint={t(hint)} />
            </button>
          ))}
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
          value={dept}
          onChange={(e) => {
            setDept(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("common.all")}</option>
          {departments.map((d) => (
            <option key={d.odoo_id} value={d.odoo_id}>
              {d.name}
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
                <Th>{t("hr.emp_code")}</Th>
                <Th>{t("hr.employee")}</Th>
                <Th>{t("hr.job_title")}</Th>
                <Th>{t("hr.department")}</Th>
                <Th>{t("hr.manager")}</Th>
                <Th>{t("hr.contract_status")}</Th>
                {EXPIRY_FIELD[filter] && <Th>{t("hr.expiry_date")}</Th>}
                <Th end />
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={EXPIRY_FIELD[filter] ? 8 : 7} />}
            {page.data.map((e) => (
              <tr key={e.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-mono text-xs text-slate-500">{e.emp_code ?? "—"}</Td>
                <Td className="font-medium text-slate-900 dark:text-slate-100">
                  <span className="flex items-center gap-2">
                    {e.avatar ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={e.avatar} alt="" className="w-7 h-7 rounded-full object-cover" />
                    ) : (
                      <span className="grid place-items-center w-7 h-7 rounded-full bg-brand-600 text-white text-xs font-bold">
                        {e.name.charAt(0)}
                      </span>
                    )}
                    {e.name}
                  </span>
                </Td>
                <Td>{e.job_title ?? "—"}</Td>
                <Td>{e.department ?? "—"}</Td>
                <Td>{e.manager ?? "—"}</Td>
                <Td>
                  {e.contract_status ? (
                    <Badge
                      tone={
                        e.contract_status.toLowerCase() === "active"
                          ? "green"
                          : e.contract_status.toLowerCase() === "expired"
                            ? "rose"
                            : "amber"
                      }
                    >
                      {e.contract_status}
                    </Badge>
                  ) : (
                    "—"
                  )}
                </Td>
                {EXPIRY_FIELD[filter] && (
                  <Td
                    className={`tabular-nums text-xs ${
                      (e[EXPIRY_FIELD[filter]] ?? "9999") < new Date().toISOString().slice(0, 10)
                        ? "text-rose-600 font-semibold"
                        : "text-amber-600"
                    }`}
                  >
                    {e[EXPIRY_FIELD[filter]] ?? "—"}
                  </Td>
                )}
                <Td end>
                  <Link href={`/hr/employees/${e.id}`} className="text-brand-600 hover:underline text-xs">
                    {t("common.view")}
                  </Link>
                </Td>
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}
