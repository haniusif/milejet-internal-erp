"use client";

// Salary-adjustment manager for one kind (penalty / deduction / reward /
// allowance). Used by the HR Configuration tabs and reusable per-employee.
// Approved records flow into Odoo's payslip computation (mj_hr_actions addon).

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Paginated, SalaryAdjustment } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import {
  Badge,
  EmptyRow,
  ErrorBox,
  money,
  Pagination,
  Spinner,
  SuccessBox,
  Table,
  Td,
  Th,
} from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

type AdjKind = SalaryAdjustment["kind"];
type AdjPage = Paginated<SalaryAdjustment> & {
  totals?: { approved: number; approved_count: number; draft_count: number };
};

const STATE_TONES: Record<string, string> = { approved: "green", draft: "amber", cancel: "rose" };

// Penalties/deductions reduce pay (shown red); rewards/allowances add (green).
const NEGATIVE: AdjKind[] = ["penalty", "deduction"];

export default function AdjustmentsManager({ kind }: { kind: AdjKind }) {
  const { t } = useI18n();
  const { can } = useAuth();
  const manager = can("payslips.create");

  const [page, setPage] = useState<AdjPage | null>(null);
  const [pageNum, setPageNum] = useState(1);
  const [q, setQ] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  // Reset paging when the active tab (kind) changes.
  useEffect(() => {
    setPageNum(1);
    setShowForm(false);
    setQ("");
  }, [kind]);

  const load = useCallback(() => {
    api
      .get<AdjPage>(`/hr/adjustments${qs({ kind, q, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [kind, q, pageNum]);

  useEffect(load, [load]);

  async function act(a: SalaryAdjustment, action: "approve" | "cancel" | "delete") {
    if ((action === "cancel" || action === "delete") && !confirm(t("common.confirm_delete"))) return;
    setError(null);
    try {
      if (action === "delete") await api.del(`/hr/adjustments/${a.id}`);
      else await api.post(`/hr/adjustments/${a.id}/${action}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  const tone = NEGATIVE.includes(kind) ? "text-rose-600" : "text-emerald-700 dark:text-emerald-400";
  const sign = NEGATIVE.includes(kind) ? "−" : "+";

  return (
    <div>
      {error && <ErrorBox message={error} />}
      {success && <SuccessBox message={success} />}

      <div className="flex flex-wrap items-center gap-2 mb-4">
        <input
          value={q}
          onChange={(e) => {
            setQ(e.target.value);
            setPageNum(1);
          }}
          placeholder={t("common.search")}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm flex-1 min-w-40 bg-white dark:bg-slate-900"
        />
        {page?.totals && (
          <span className="text-xs text-slate-500">
            {t("adj.approved_total")}: <span className={`font-semibold ${tone}`}>{money(page.totals.approved)}</span>
          </span>
        )}
        {manager && (
          <button
            onClick={() => setShowForm((v) => !v)}
            className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
          >
            + {t(`adj.new_${kind}`)}
          </button>
        )}
      </div>

      {showForm && manager && (
        <div className="mb-4">
          <AdjustmentForm
            kind={kind}
            onDone={(msg) => {
              setShowForm(false);
              if (msg) setSuccess(msg);
              load();
            }}
          />
        </div>
      )}

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("loan.reference")}</Th>
                <Th>{t("hr.employee")}</Th>
                <Th>{t("common.date")}</Th>
                <Th>{t("common.amount")}</Th>
                <Th>{t("loan.reason")}</Th>
                <Th>{t("common.status")}</Th>
                {manager && <Th end>{t("common.actions")}</Th>}
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={manager ? 7 : 6} />}
            {page.data.map((a) => (
              <tr key={a.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-mono text-xs text-slate-500">{a.name ?? "—"}</Td>
                <Td className="font-medium text-slate-900 dark:text-slate-100">{a.employee_name}</Td>
                <Td className="tabular-nums text-xs">{a.date ?? "—"}</Td>
                <Td className={`tabular-nums font-semibold ${tone}`}>
                  {sign}
                  {money(a.amount)}
                </Td>
                <Td className="text-xs text-slate-500">{a.reason ?? "—"}</Td>
                <Td>
                  <Badge tone={STATE_TONES[a.state] ?? "slate"}>{t(`adj.state.${a.state}`)}</Badge>
                </Td>
                {manager && (
                  <Td end>
                    {a.state === "draft" && (
                      <button onClick={() => act(a, "approve")} className="text-emerald-600 hover:underline text-xs me-3">
                        {t("loan.approve")}
                      </button>
                    )}
                    {a.state !== "cancel" && (
                      <button onClick={() => act(a, "cancel")} className="text-amber-600 hover:underline text-xs me-3">
                        {t("common.cancel")}
                      </button>
                    )}
                    <button onClick={() => act(a, "delete")} className="text-rose-600 hover:underline text-xs">
                      {t("common.delete")}
                    </button>
                  </Td>
                )}
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}

function AdjustmentForm({ kind, onDone }: { kind: AdjKind; onDone: (msg: string | null) => void }) {
  const { t } = useI18n();
  const [employees, setEmployees] = useState<{ odoo_id: number; name: string; emp_code: string | null }[]>([]);
  const [form, setForm] = useState({ employee_id: "", amount: "", date: "", reason: "" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: { odoo_id: number; name: string; emp_code: string | null }[] }>("/hr/employees?per_page=300")
      .then((r) => setEmployees(r.data))
      .catch(() => {});
  }, []);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.post("/hr/adjustments", {
        employee_id: Number(form.employee_id),
        kind,
        amount: Number(form.amount),
        date: form.date || undefined,
        reason: form.reason || undefined,
        approve: true,
      });
      onDone(t("common.saved"));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  return (
    <>
      {error && <ErrorBox message={error} />}
      <FormCard onSubmit={submit}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label={t("hr.employee")} required>
            <Select required value={form.employee_id} onChange={set("employee_id")}>
              <option value="">—</option>
              {employees.map((e) => (
                <option key={e.odoo_id} value={e.odoo_id}>
                  {e.emp_code ? `${e.emp_code} — ` : ""}
                  {e.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("common.amount")} required>
            <TextInput required type="number" min="0.01" step="0.01" value={form.amount} onChange={set("amount")} />
          </Field>
          <Field label={t("common.date")}>
            <TextInput type="date" value={form.date} onChange={set("date")} />
          </Field>
          <Field label={t("loan.reason")}>
            <TextInput value={form.reason} onChange={set("reason")} />
          </Field>
        </div>
        <p className="text-[11px] text-slate-400">{t("adj.period_hint")}</p>
        <div className="flex items-center gap-2">
          <SubmitButton busy={busy} />
          <button
            type="button"
            onClick={() => onDone(null)}
            className="h-9 px-4 rounded-md border border-slate-200 dark:border-slate-700 text-sm text-slate-600 dark:text-slate-300"
          >
            {t("common.cancel")}
          </button>
        </div>
      </FormCard>
    </>
  );
}
