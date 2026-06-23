"use client";

import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useI18n } from "@/lib/i18n";
import { ErrorBox, PageHeader, Spinner, SuccessBox } from "@/components/ui";
import { Field, TextInput } from "@/components/form";

interface PayableEmployee {
  id: number;
  odoo_id: number;
  name: string;
  job_title: string | null;
}

function monthRange(): { from: string; to: string } {
  const now = new Date();
  const from = new Date(now.getFullYear(), now.getMonth(), 1);
  const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  const fmt = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
  return { from: fmt(from), to: fmt(to) };
}

export default function NewPayslipPage() {
  const { t } = useI18n();
  const router = useRouter();
  const defaults = useMemo(() => monthRange(), []);

  const [employees, setEmployees] = useState<PayableEmployee[] | null>(null);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [from, setFrom] = useState(defaults.from);
  const [to, setTo] = useState(defaults.to);
  const [compute, setCompute] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: PayableEmployee[] }>("/hr/payable-employees")
      .then((r) => setEmployees(r.data))
      .catch(() => setEmployees([]));
  }, []);

  function toggle(odooId: number) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(odooId)) next.delete(odooId);
      else next.add(odooId);
      return next;
    });
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (selected.size === 0) return;
    setBusy(true);
    setError(null);
    setResult(null);
    try {
      const r = await api.post<{ created: unknown[]; skipped: unknown[]; failed: { employee: string; reason: string }[] }>(
        "/hr/payslips",
        {
          employee_ids: Array.from(selected),
          date_from: from,
          date_to: to,
          compute,
        }
      );
      const msg = t("pay.result", {
        created: r.created.length,
        skipped: r.skipped.length,
        failed: r.failed.length,
      });
      if (r.failed.length === 0) {
        router.push(`/hr/payslips?month=${from.slice(0, 7)}`);
        return;
      }
      setResult(msg);
      setError(r.failed.map((f) => `${f.employee}: ${f.reason}`).join(" · "));
      setBusy(false);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  if (!employees) return <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.payslips")} title={t("pay.create")} />

      {result && <SuccessBox message={result} />}
      {error && <ErrorBox message={error} />}

      <form onSubmit={submit} className="space-y-5 max-w-3xl">
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          <Field label={t("leave.from")} required>
            <TextInput type="date" required value={from} onChange={(e) => setFrom(e.target.value)} />
          </Field>
          <Field label={t("leave.to")} required>
            <TextInput type="date" required value={to} onChange={(e) => setTo(e.target.value)} />
          </Field>
          <label className="flex items-end gap-2 text-sm pb-2">
            <input type="checkbox" checked={compute} onChange={(e) => setCompute(e.target.checked)} />
            {t("pay.auto_compute")}
          </label>
        </div>

        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden">
          <div className="px-4 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <span className="text-sm font-semibold">
              {t("pay.select_employees")} ({selected.size}/{employees.length})
            </span>
            <button
              type="button"
              onClick={() =>
                setSelected(selected.size === employees.length ? new Set() : new Set(employees.map((e) => e.odoo_id)))
              }
              className="text-xs text-brand-600 hover:underline"
            >
              {t("common.all")}
            </button>
          </div>
          <div className="max-h-96 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
            {employees.map((emp) => (
              <label key={emp.odoo_id} className="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50/60 dark:hover:bg-slate-800/40 cursor-pointer">
                <input type="checkbox" checked={selected.has(emp.odoo_id)} onChange={() => toggle(emp.odoo_id)} />
                <span className="min-w-0">
                  <span className="block text-sm text-slate-900 dark:text-slate-100">{emp.name}</span>
                  {emp.job_title && <span className="block text-xs text-slate-400">{emp.job_title}</span>}
                </span>
              </label>
            ))}
            {employees.length === 0 && (
              <p className="px-4 py-8 text-center text-sm text-slate-400">{t("common.empty")}</p>
            )}
          </div>
        </div>

        <button
          type="submit"
          disabled={busy || selected.size === 0}
          className="h-10 px-6 rounded-md bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-sm font-semibold"
        >
          {t("pay.create")} ({selected.size})
        </button>
      </form>
    </div>
  );
}
