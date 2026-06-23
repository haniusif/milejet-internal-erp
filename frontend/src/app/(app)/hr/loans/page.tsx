"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Loan, Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import {
  Badge,
  EmptyRow,
  ErrorBox,
  money,
  PageHeader,
  Pagination,
  Spinner,
  StatCard,
  SuccessBox,
  Table,
  Td,
  Th,
} from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

type LoanPage = Paginated<Loan> & {
  totals?: { active: number; outstanding: number; repaid: number; paid: number };
};

const STATE_TONES: Record<string, string> = { active: "brand", paid: "green", draft: "amber", cancel: "rose" };

export default function LoansPage() {
  const { t } = useI18n();
  const { can } = useAuth();

  const [page, setPage] = useState<LoanPage | null>(null);
  const [q, setQ] = useState("");
  const [state, setState] = useState("");
  const [pageNum, setPageNum] = useState(1);
  const [showForm, setShowForm] = useState(false);
  const [expanded, setExpanded] = useState<number | null>(null);
  const [repayFor, setRepayFor] = useState<Loan | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const manager = can("loans.manage");

  const load = useCallback(() => {
    api
      .get<LoanPage>(`/hr/loans${qs({ q, state, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [q, state, pageNum]);

  useEffect(load, [load]);

  async function act(loan: Loan, action: "approve" | "cancel") {
    if (action === "cancel" && !confirm(t("common.confirm_delete"))) return;
    setError(null);
    try {
      await api.post(`/hr/loans/${loan.id}/${action}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.loans")}>
        {manager && (
          <button
            onClick={() => setShowForm((v) => !v)}
            className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
          >
            + {t("loan.new")}
          </button>
        )}
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {success && <SuccessBox message={success} />}

      {page?.totals && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
          {(
            [
              ["active", "loan.active_count", page.totals.active, "brand", "loan.hint_active"],
              ["", "loan.outstanding", money(page.totals.outstanding), "amber", "loan.hint_outstanding"],
              ["", "loan.repaid_total", money(page.totals.repaid), "green", "loan.hint_repaid"],
              ["paid", "loan.paid_count", page.totals.paid, "default", "loan.hint_paid"],
            ] as const
          ).map(([f, label, value, tone, hint], i) =>
            i === 1 || i === 2 ? (
              <StatCard key={label} label={t(label)} value={value} tone={tone} hint={t(hint)} />
            ) : (
              <button
                key={label}
                type="button"
                onClick={() => {
                  setState(state === f ? "" : f);
                  setPageNum(1);
                }}
                className={`text-start rounded-xl transition ${
                  state === f && f !== ""
                    ? "ring-2 ring-brand-500"
                    : "hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700"
                }`}
              >
                <StatCard label={t(label)} value={value} tone={tone} hint={t(hint)} />
              </button>
            ),
          )}
        </div>
      )}

      {showForm && manager && (
        <LoanForm
          onDone={(msg) => {
            setShowForm(false);
            setSuccess(msg);
            load();
          }}
        />
      )}

      {repayFor && manager && (
        <RepayForm
          loan={repayFor}
          onDone={(msg) => {
            setRepayFor(null);
            if (msg) setSuccess(msg);
            load();
          }}
        />
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
          value={state}
          onChange={(e) => {
            setState(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("common.all")}</option>
          {["draft", "active", "paid", "cancel"].map((s) => (
            <option key={s} value={s}>
              {t(`loan.state.${s}`)}
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
                <Th>{t("loan.reference")}</Th>
                <Th>{t("hr.employee")}</Th>
                <Th>{t("common.date")}</Th>
                <Th>{t("common.amount")}</Th>
                <Th>{t("loan.installment")}</Th>
                <Th>{t("loan.repaid")}</Th>
                <Th>{t("loan.balance")}</Th>
                <Th>{t("common.status")}</Th>
                {manager && <Th end>{t("common.actions")}</Th>}
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={manager ? 9 : 8} />}
            {page.data.map((l) => (
              <LoanRow
                key={l.id}
                loan={l}
                manager={manager}
                expanded={expanded === l.id}
                onToggle={() => setExpanded(expanded === l.id ? null : l.id)}
                onRepay={() => setRepayFor(l)}
                onAct={act}
              />
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}

function LoanRow({
  loan,
  manager,
  expanded,
  onToggle,
  onRepay,
  onAct,
}: {
  loan: Loan;
  manager: boolean;
  expanded: boolean;
  onToggle: () => void;
  onRepay: () => void;
  onAct: (loan: Loan, action: "approve" | "cancel") => void;
}) {
  const { t } = useI18n();
  return (
    <>
      <tr className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 cursor-pointer" onClick={onToggle}>
        <Td className="font-mono text-xs text-slate-500">{loan.name ?? "—"}</Td>
        <Td className="font-medium text-slate-900 dark:text-slate-100">{loan.employee_name}</Td>
        <Td className="tabular-nums text-xs">{loan.date ?? "—"}</Td>
        <Td className="tabular-nums font-medium">{money(loan.amount)}</Td>
        <Td className="tabular-nums text-xs">{loan.installment ? money(loan.installment) : "—"}</Td>
        <Td className="tabular-nums text-emerald-700 dark:text-emerald-400">{money(loan.repaid_amount)}</Td>
        <Td className="tabular-nums font-semibold">{money(loan.balance)}</Td>
        <Td>
          <Badge tone={STATE_TONES[loan.state] ?? "slate"}>{t(`loan.state.${loan.state}`)}</Badge>
        </Td>
        {manager && (
          <Td end>
            <span onClick={(e) => e.stopPropagation()}>
              {loan.state === "draft" && (
                <button onClick={() => onAct(loan, "approve")} className="text-emerald-600 hover:underline text-xs me-3">
                  {t("loan.approve")}
                </button>
              )}
              {loan.state === "active" && (
                <button onClick={onRepay} className="text-brand-600 hover:underline text-xs me-3">
                  {t("loan.repay")}
                </button>
              )}
              {(loan.state === "draft" || loan.state === "active") && loan.repaid_amount === 0 && (
                <button onClick={() => onAct(loan, "cancel")} className="text-rose-600 hover:underline text-xs">
                  {t("common.cancel")}
                </button>
              )}
            </span>
          </Td>
        )}
      </tr>
      {expanded && (
        <tr className="bg-slate-50/60 dark:bg-slate-800/30">
          <td colSpan={manager ? 9 : 8} className="px-6 py-3">
            {loan.reason && (
              <p className="text-xs text-slate-500 mb-2">
                {t("loan.reason")}: <span className="text-slate-700 dark:text-slate-300">{loan.reason}</span>
              </p>
            )}
            {loan.lines.length === 0 ? (
              <p className="text-xs text-slate-400">{t("loan.no_repayments")}</p>
            ) : (
              <table className="text-xs w-full max-w-md">
                <tbody>
                  {loan.lines.map((ln) => (
                    <tr key={ln.id} className="border-b border-slate-100 dark:border-slate-800 last:border-0">
                      <td className="py-1 tabular-nums text-slate-500">{ln.date ?? "—"}</td>
                      <td className="py-1 tabular-nums font-medium text-emerald-700 dark:text-emerald-400">
                        {money(ln.amount)}
                      </td>
                      <td className="py-1 text-slate-500">{ln.note ?? ""}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </td>
        </tr>
      )}
    </>
  );
}

function LoanForm({ onDone }: { onDone: (msg: string) => void }) {
  const { t } = useI18n();
  const [employees, setEmployees] = useState<{ odoo_id: number; name: string; emp_code: string | null }[]>([]);
  const [form, setForm] = useState({ employee_id: "", amount: "", installment: "", date: "", reason: "" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: { odoo_id: number; name: string; emp_code: string | null }[] }>("/hr/loans/employees")
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
      await api.post("/hr/loans", {
        employee_id: Number(form.employee_id),
        amount: Number(form.amount),
        installment: form.installment ? Number(form.installment) : undefined,
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
    <div className="mb-5">
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
            <TextInput required type="number" min="1" step="0.01" value={form.amount} onChange={set("amount")} />
          </Field>
          <Field label={t("loan.installment")}>
            <TextInput type="number" min="0" step="0.01" value={form.installment} onChange={set("installment")} />
          </Field>
          <Field label={t("common.date")}>
            <TextInput type="date" value={form.date} onChange={set("date")} />
          </Field>
          <div className="md:col-span-2">
            <Field label={t("loan.reason")}>
              <TextInput value={form.reason} onChange={set("reason")} />
            </Field>
          </div>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}

function RepayForm({ loan, onDone }: { loan: Loan; onDone: (msg: string | null) => void }) {
  const { t } = useI18n();
  const [form, setForm] = useState({ amount: loan.installment ? String(loan.installment) : "", date: "", note: "" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.post(`/hr/loans/${loan.id}/repayments`, {
        amount: Number(form.amount),
        date: form.date || undefined,
        note: form.note || undefined,
      });
      onDone(t("common.saved"));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  return (
    <div className="mb-5">
      {error && <ErrorBox message={error} />}
      <FormCard onSubmit={submit}>
        <p className="text-sm font-medium text-slate-900 dark:text-slate-100">
          {t("loan.repay")} — {loan.employee_name}
          <span className="ms-2 text-xs text-slate-500">
            {t("loan.balance")}: {money(loan.balance)}
          </span>
        </p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Field label={t("common.amount")} required>
            <TextInput
              required
              type="number"
              min="0.01"
              max={loan.balance}
              step="0.01"
              value={form.amount}
              onChange={set("amount")}
            />
          </Field>
          <Field label={t("common.date")}>
            <TextInput type="date" value={form.date} onChange={set("date")} />
          </Field>
          <Field label={t("loan.note")}>
            <TextInput value={form.note} onChange={set("note")} />
          </Field>
        </div>
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
    </div>
  );
}
