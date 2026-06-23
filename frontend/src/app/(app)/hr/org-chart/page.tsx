"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";
import type { OrgEmployee } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { PageHeader, Spinner } from "@/components/ui";

function Node({ emp, byParent }: { emp: OrgEmployee; byParent: Map<number, OrgEmployee[]> }) {
  const children = byParent.get(emp.odoo_id) ?? [];
  return (
    <li>
      <Link
        href={`/hr/employees/${emp.id}`}
        className="inline-flex items-center gap-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg px-3 py-2 hover:border-brand-400 transition mb-2"
      >
        {emp.avatar ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={emp.avatar} alt="" className="w-8 h-8 rounded-full object-cover" />
        ) : (
          <span className="grid place-items-center w-8 h-8 rounded-full bg-brand-600 text-white text-xs font-bold">
            {emp.name.charAt(0)}
          </span>
        )}
        <span>
          <span className="block text-sm font-medium text-slate-900 dark:text-slate-100">{emp.name}</span>
          <span className="block text-xs text-slate-400">
            {emp.job_title ?? "—"}
            {emp.department && ` · ${emp.department}`}
          </span>
        </span>
      </Link>
      {children.length > 0 && (
        <ul className="ms-8 border-s-2 border-slate-200 dark:border-slate-700 ps-4 space-y-1">
          {children.map((c) => (
            <Node key={c.odoo_id} emp={c} byParent={byParent} />
          ))}
        </ul>
      )}
    </li>
  );
}

export default function OrgChartPage() {
  const { t } = useI18n();
  const [employees, setEmployees] = useState<OrgEmployee[] | null>(null);

  useEffect(() => {
    api.get<{ data: OrgEmployee[] }>("/hr/org-chart").then((r) => setEmployees(r.data)).catch(() => {});
  }, []);

  const { roots, byParent } = useMemo(() => {
    const list = employees ?? [];
    const ids = new Set(list.map((e) => e.odoo_id));
    const byParent = new Map<number, OrgEmployee[]>();
    for (const e of list) {
      if (e.parent_odoo_id && ids.has(e.parent_odoo_id)) {
        byParent.set(e.parent_odoo_id, [...(byParent.get(e.parent_odoo_id) ?? []), e]);
      }
    }
    const roots = list.filter((e) => !e.parent_odoo_id || !ids.has(e.parent_odoo_id));
    return { roots, byParent };
  }, [employees]);

  if (!employees) return <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.org_chart")} />
      <ul className="space-y-2">
        {roots.map((r) => (
          <Node key={r.odoo_id} emp={r} byParent={byParent} />
        ))}
      </ul>
    </div>
  );
}
