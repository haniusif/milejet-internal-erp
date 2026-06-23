"use client";

import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { Payslip, PayslipPayment } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import {
  Badge,
  EmptyRow,
  ErrorBox,
  money,
  PageHeader,
  payslipStateTone,
  Spinner,
  Table,
  Td,
  Th,
} from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

const PAY_STATUS_TONES: Record<string, string> = { paid: "green", partial: "amber", unpaid: "rose" };

interface PaymentsData {
  data: PayslipPayment[];
  summary: { net_total: number; amount_paid: number; amount_due: number; payment_status: string };
}

function PaymentsSection({ payslipId }: { payslipId: string }) {
  const { t } = useI18n();
  const { can } = useAuth();
  const manager = can("payslips.create");
  const [pay, setPay] = useState<PaymentsData | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ amount: "", date: "", method: "bank", reference: "", note: "" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    api
      .get<PaymentsData>(`/hr/payslips/${payslipId}/payments`)
      .then(setPay)
      .catch(() => setPay(null));
  }, [payslipId]);

  useEffect(load, [load]);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.post(`/hr/payslips/${payslipId}/payments`, {
        amount: Number(form.amount),
        date: form.date || undefined,
        method: form.method || undefined,
        reference: form.reference || undefined,
        note: form.note || undefined,
      });
      setForm({ amount: "", date: "", method: "bank", reference: "", note: "" });
      setShowForm(false);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  }

  async function remove(id: number) {
    if (!confirm(t("common.confirm_delete"))) return;
    setError(null);
    try {
      await api.del(`/hr/payments/${id}`);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
    }
  }

  if (!pay) return null;
  const s = pay.summary;
  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  return (
    <div className="mt-6">
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{t("pay.payments")}</h2>
        {manager && (
          <button
            onClick={() => setShowForm((v) => !v)}
            className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
          >
            + {t("pay.add_payment")}
          </button>
        )}
      </div>

      {error && <ErrorBox message={error} />}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 mb-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
          <p className="text-xs text-slate-400 mb-0.5">{t("pay.status")}</p>
          <Badge tone={PAY_STATUS_TONES[s.payment_status] ?? "slate"}>{t(`pay.pstatus.${s.payment_status}`)}</Badge>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">{t("pay.net")}</p>
          <p className="font-medium tabular-nums">{money(s.net_total)}</p>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">{t("pay.paid")}</p>
          <p className="font-medium tabular-nums text-emerald-700 dark:text-emerald-400">{money(s.amount_paid)}</p>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">{t("pay.due")}</p>
          <p className="font-bold tabular-nums text-rose-600">{money(s.amount_due)}</p>
        </div>
      </div>

      {showForm && manager && (
        <div className="mb-4">
          <FormCard onSubmit={add}>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <Field label={t("common.amount")} required>
                <TextInput required type="number" min="0.01" step="0.01" value={form.amount} onChange={set("amount")} />
              </Field>
              <Field label={t("common.date")}>
                <TextInput type="date" value={form.date} onChange={set("date")} />
              </Field>
              <Field label={t("pay.method")}>
                <Select value={form.method} onChange={set("method")}>
                  {["bank", "cash", "cheque", "other"].map((m) => (
                    <option key={m} value={m}>
                      {t(`pay.method.${m}`)}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label={t("pay.reference")}>
                <TextInput value={form.reference} onChange={set("reference")} />
              </Field>
              <Field label={t("loan.note")}>
                <TextInput value={form.note} onChange={set("note")} />
              </Field>
            </div>
            <SubmitButton busy={busy} />
          </FormCard>
        </div>
      )}

      <Table
        head={
          <>
            <Th>{t("common.date")}</Th>
            <Th>{t("common.amount")}</Th>
            <Th>{t("pay.method")}</Th>
            <Th>{t("pay.reference")}</Th>
            {manager && <Th end>{t("common.actions")}</Th>}
          </>
        }
      >
        {pay.data.length === 0 && <EmptyRow colSpan={manager ? 5 : 4} />}
        {pay.data.map((p) => (
          <tr key={p.id}>
            <Td className="tabular-nums text-xs">{p.date ?? "—"}</Td>
            <Td className="tabular-nums font-medium text-emerald-700 dark:text-emerald-400">{money(p.amount)}</Td>
            <Td className="text-xs">{p.method ? t(`pay.method.${p.method}`) : "—"}</Td>
            <Td className="text-xs text-slate-500">{p.reference ?? "—"}</Td>
            {manager && (
              <Td end>
                <button onClick={() => remove(p.id)} className="text-rose-600 hover:underline text-xs">
                  {t("common.delete")}
                </button>
              </Td>
            )}
          </tr>
        ))}
      </Table>
    </div>
  );
}

export default function PayslipDetail() {
  const { t } = useI18n();
  const { can } = useAuth();
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const [slip, setSlip] = useState<Payslip | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function load() {
    api
      .get<{ data: Payslip }>(`/hr/payslips/${id}`)
      .then((r) => setSlip(r.data))
      .catch((e) => setError(e instanceof ApiError ? e.message : t("common.error")));
  }

  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(load, [id]);

  async function compute() {
    setBusy(true);
    setError(null);
    try {
      await api.post(`/hr/payslips/${id}/compute`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  }

  async function destroy() {
    if (!confirm(t("common.confirm_delete"))) return;
    setBusy(true);
    setError(null);
    try {
      await api.del(`/hr/payslips/${id}`);
      router.push("/hr/payslips");
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
      setBusy(false);
    }
  }

  if (error && !slip) return <ErrorBox message={error} />;
  if (!slip) return <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.payslips")} title={slip.number ?? slip.employee_name}>
        <Badge tone={payslipStateTone(slip.state)}>{t(`pay.state.${slip.state}`)}</Badge>
        {can("payslips.create") && (
          <button
            onClick={compute}
            disabled={busy}
            className="h-9 px-3 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium disabled:opacity-50"
          >
            {t("pay.compute")}
          </button>
        )}
        {can("payslips.delete") && (
          <button
            onClick={destroy}
            disabled={busy}
            className="h-9 px-3 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-600 text-sm font-medium disabled:opacity-50"
          >
            {t("common.delete")}
          </button>
        )}
      </PageHeader>
      {error && <ErrorBox message={error} />}

      <div className="bg-white border border-slate-200 rounded-xl p-5 mb-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
          <p className="text-xs text-slate-400 mb-0.5">{t("hr.employee")}</p>
          <p className="font-medium text-slate-900 dark:text-slate-100">{slip.employee_name}</p>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">{t("pay.period")}</p>
          <p className="tabular-nums">
            {slip.date_from} → {slip.date_to}
          </p>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">{t("pay.gross")}</p>
          <p className="font-medium tabular-nums">{money(slip.gross_total)}</p>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">{t("pay.net")}</p>
          <p className="font-bold text-emerald-700 tabular-nums">
            {money(slip.net_total)} {t("common.sar")}
          </p>
        </div>
      </div>

      <Table
        head={
          <>
            <Th>{t("pay.reference")}</Th>
            <Th>{t("hr.employee")}</Th>
            <Th end>{t("common.amount")}</Th>
          </>
        }
      >
        {(slip.lines ?? []).map((l, i) => (
          <tr key={i} className={l.category_code === "NET" ? "bg-emerald-50/50 font-bold" : ""}>
            <Td className="font-mono text-xs text-slate-500">{l.code}</Td>
            <Td>{l.name}</Td>
            <Td end className="tabular-nums">
              {money(l.total)}
            </Td>
          </tr>
        ))}
      </Table>

      <PaymentsSection payslipId={id} />
    </div>
  );
}
