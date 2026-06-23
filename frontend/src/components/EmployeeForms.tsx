"use client";

// HR Forms for one employee: warnings (إنذارات), sick-leave forms (أورنيك مرض)
// and end-of-service clearance (مخالصة). Each record is created in Odoo and can
// be printed to PDF (QWeb). Embedded on the employee profile.

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, money, Spinner, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

type FormType = "warning" | "sick-leave" | "service-end";

const TABS: FormType[] = ["warning", "sick-leave", "service-end"];

const STATE_TONES: Record<string, string> = {
  draft: "amber",
  issued: "brand",
  acknowledged: "green",
  confirmed: "green",
  cleared: "green",
};

// State-transition buttons available per type+state: [action slug, label key].
const ACTIONS: Record<FormType, Record<string, [string, string][]>> = {
  warning: {
    draft: [["issue", "forms.issue"]],
    issued: [["acknowledge", "forms.acknowledge"]],
  },
  "sick-leave": { draft: [["confirm", "forms.confirm"]] },
  "service-end": { draft: [["clear", "forms.clear"]] },
};

interface FormRow {
  id: number;
  name: string | null;
  state: string;
  // warning
  warning_type?: string;
  subject?: string | null;
  date?: string | null;
  // sick
  date_from?: string | null;
  date_to?: string | null;
  days?: number;
  diagnosis?: string | null;
  // eos
  reason?: string;
  last_working_day?: string | null;
  settlement_amount?: number;
}

export default function EmployeeForms({ employeeOdooId }: { employeeOdooId: number }) {
  const { t } = useI18n();
  const { can } = useAuth();
  const manager = can("employees.write");
  const [tab, setTab] = useState<FormType>("warning");

  return (
    <div>
      <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("forms.title")}</h2>
      <div className="flex gap-1 border-b border-slate-200 dark:border-slate-800 mb-3">
        {TABS.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={`px-3 h-9 text-sm font-medium border-b-2 -mb-px transition ${
              tab === key
                ? "border-brand-600 text-brand-700 dark:text-brand-300"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            {t(`forms.tab.${key}`)}
          </button>
        ))}
      </div>
      <FormSection key={tab} type={tab} employeeOdooId={employeeOdooId} manager={manager} />
    </div>
  );
}

function FormSection({
  type,
  employeeOdooId,
  manager,
}: {
  type: FormType;
  employeeOdooId: number;
  manager: boolean;
}) {
  const { t } = useI18n();
  const [rows, setRows] = useState<FormRow[] | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    api
      .get<{ data: FormRow[] }>(`/hr/forms/${type}?employee_id=${employeeOdooId}`)
      .then((r) => setRows(r.data))
      .catch(() => setRows([]));
  }, [type, employeeOdooId]);

  useEffect(load, [load]);

  async function act(row: FormRow, action: string) {
    setError(null);
    try {
      await api.post(`/hr/forms/${type}/${row.id}/action/${action}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  async function remove(row: FormRow) {
    if (!confirm(t("common.confirm_delete"))) return;
    setError(null);
    try {
      await api.del(`/hr/forms/${type}/${row.id}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  // Primary descriptive cell per type.
  function describe(r: FormRow): string {
    if (type === "warning") return `${t(`forms.warning.${r.warning_type}`)} — ${r.subject ?? ""}`;
    if (type === "sick-leave") return `${r.date_from} → ${r.date_to} (${r.days} ${t("forms.days")})`;
    return `${t(`forms.reason.${r.reason}`)} — ${r.last_working_day ?? ""}`;
  }

  return (
    <div>
      {error && <ErrorBox message={error} />}

      {manager && (
        <div className="flex justify-end mb-3">
          <button
            onClick={() => setShowForm((v) => !v)}
            className="h-8 px-3 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-xs font-medium"
          >
            + {t("forms.add")}
          </button>
        </div>
      )}

      {showForm && manager && (
        <div className="mb-4">
          <CreateForm
            type={type}
            employeeOdooId={employeeOdooId}
            onDone={() => {
              setShowForm(false);
              load();
            }}
          />
        </div>
      )}

      {!rows ? (
        <Spinner />
      ) : (
        <Table
          head={
            <>
              <Th>{t("loan.reference")}</Th>
              <Th>{t("common.details")}</Th>
              <Th>{t("common.status")}</Th>
              <Th end>{t("common.actions")}</Th>
            </>
          }
        >
          {rows.length === 0 && <EmptyRow colSpan={4} />}
          {rows.map((r) => (
            <tr key={r.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
              <Td className="font-mono text-xs text-slate-500">{r.name ?? "—"}</Td>
              <Td className="text-sm text-slate-900 dark:text-slate-100">
                {describe(r)}
                {type === "service-end" && r.settlement_amount ? (
                  <span className="block text-xs text-slate-400">
                    {t("forms.settlement")}: {money(r.settlement_amount)}
                  </span>
                ) : null}
              </Td>
              <Td>
                <Badge tone={STATE_TONES[r.state] ?? "slate"}>{t(`forms.state.${r.state}`)}</Badge>
              </Td>
              <Td end>
                <a
                  href={`/api/v1/hr/forms/${type}/${r.id}/pdf`}
                  target="_blank"
                  rel="noreferrer"
                  className="text-brand-600 hover:underline text-xs me-3"
                >
                  {t("forms.pdf")}
                </a>
                {manager &&
                  (ACTIONS[type][r.state] ?? []).map(([slug, label]) => (
                    <button
                      key={slug}
                      onClick={() => act(r, slug)}
                      className="text-emerald-600 hover:underline text-xs me-3"
                    >
                      {t(label)}
                    </button>
                  ))}
                {manager && (
                  <button onClick={() => remove(r)} className="text-rose-600 hover:underline text-xs">
                    {t("common.delete")}
                  </button>
                )}
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </div>
  );
}

function CreateForm({
  type,
  employeeOdooId,
  onDone,
}: {
  type: FormType;
  employeeOdooId: number;
  onDone: () => void;
}) {
  const { t } = useI18n();
  const [form, setForm] = useState<Record<string, string>>(
    type === "warning"
      ? { warning_type: "first", subject: "", description: "", date: "" }
      : type === "sick-leave"
        ? { date_from: "", date_to: "", diagnosis: "", doctor_name: "", facility: "", note: "" }
        : { reason: "resignation", last_working_day: "", settlement_amount: "", custody_note: "", clearance_note: "" },
  );
  const [checks, setChecks] = useState({ notice_served: false, custody_returned: false });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    const body: Record<string, unknown> = { employee_id: employeeOdooId };
    for (const [k, v] of Object.entries(form)) if (v !== "") body[k] = k === "settlement_amount" ? Number(v) : v;
    if (type === "service-end") {
      body.notice_served = checks.notice_served;
      body.custody_returned = checks.custody_returned;
    }
    try {
      await api.post(`/hr/forms/${type}`, body);
      onDone();
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
          {type === "warning" && (
            <>
              <Field label={t("forms.warning.type")} required>
                <Select required value={form.warning_type} onChange={set("warning_type")}>
                  {["first", "second", "final"].map((w) => (
                    <option key={w} value={w}>
                      {t(`forms.warning.${w}`)}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label={t("common.date")}>
                <TextInput type="date" value={form.date} onChange={set("date")} />
              </Field>
              <div className="md:col-span-2">
                <Field label={t("forms.subject")} required>
                  <TextInput required value={form.subject} onChange={set("subject")} />
                </Field>
              </div>
              <div className="md:col-span-2">
                <Field label={t("forms.details")}>
                  <TextInput value={form.description} onChange={set("description")} />
                </Field>
              </div>
            </>
          )}

          {type === "sick-leave" && (
            <>
              <Field label={t("forms.from")} required>
                <TextInput required type="date" value={form.date_from} onChange={set("date_from")} />
              </Field>
              <Field label={t("forms.to")} required>
                <TextInput required type="date" value={form.date_to} onChange={set("date_to")} />
              </Field>
              <Field label={t("forms.diagnosis")}>
                <TextInput value={form.diagnosis} onChange={set("diagnosis")} />
              </Field>
              <Field label={t("forms.doctor")}>
                <TextInput value={form.doctor_name} onChange={set("doctor_name")} />
              </Field>
              <Field label={t("forms.facility")}>
                <TextInput value={form.facility} onChange={set("facility")} />
              </Field>
              <Field label={t("loan.note")}>
                <TextInput value={form.note} onChange={set("note")} />
              </Field>
            </>
          )}

          {type === "service-end" && (
            <>
              <Field label={t("forms.reason_label")} required>
                <Select required value={form.reason} onChange={set("reason")}>
                  {["resignation", "dismissal", "probation_end", "contract_end", "mutual"].map((r) => (
                    <option key={r} value={r}>
                      {t(`forms.reason.${r}`)}
                    </option>
                  ))}
                </Select>
              </Field>
              <Field label={t("forms.last_working_day")}>
                <TextInput type="date" value={form.last_working_day} onChange={set("last_working_day")} />
              </Field>
              <Field label={t("forms.settlement")}>
                <TextInput type="number" min="0" step="0.01" value={form.settlement_amount} onChange={set("settlement_amount")} />
              </Field>
              <div className="flex items-center gap-4 pt-6">
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={checks.notice_served}
                    onChange={(e) => setChecks((c) => ({ ...c, notice_served: e.target.checked }))}
                  />
                  {t("forms.notice_served")}
                </label>
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={checks.custody_returned}
                    onChange={(e) => setChecks((c) => ({ ...c, custody_returned: e.target.checked }))}
                  />
                  {t("forms.custody_returned")}
                </label>
              </div>
              <div className="md:col-span-2">
                <Field label={t("forms.custody_note")}>
                  <TextInput value={form.custody_note} onChange={set("custody_note")} />
                </Field>
              </div>
              <div className="md:col-span-2">
                <Field label={t("forms.clearance_note")}>
                  <TextInput value={form.clearance_note} onChange={set("clearance_note")} />
                </Field>
              </div>
            </>
          )}
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </>
  );
}
