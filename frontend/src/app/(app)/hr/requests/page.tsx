"use client";

// Employee self-service request hub (mj_hr_ess): raise certificates / equipment /
// uniform / vehicle / resignation / transfer; track status; HR approves & issues.

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, PageHeader, Pagination, Spinner, SuccessBox, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

interface Req {
  id: number;
  name: string | null;
  employee_name: string | null;
  request_type: string;
  state: string;
  date_request: string | null;
  summary: string | null;
  certificate_kind: string | null;
  has_certificate: boolean;
}
type ReqPage = Paginated<Req> & { totals: { to_approve: number } };

const STATE_TONE: Record<string, string> = { draft: "slate", submitted: "amber", approved: "brand", done: "green", refused: "rose", cancelled: "rose" };
const TYPES = ["certificate", "equipment", "uniform", "vehicle", "resignation", "transfer", "other"] as const;

export default function RequestsPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const isHr = can("hr.view_all");

  const [page, setPage] = useState<ReqPage | null>(null);
  const [tab, setTab] = useState<"mine" | "approve">(isHr ? "approve" : "mine");
  const [pageNum, setPageNum] = useState(1);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const load = useCallback(() => {
    const params = isHr && tab === "approve" ? { to_approve: 1, page: pageNum } : { page: pageNum };
    api.get<ReqPage>(`/hr/requests${qs(params)}`).then(setPage).catch(() => setPage(null));
  }, [isHr, tab, pageNum]);
  useEffect(load, [load]);

  async function act(r: Req, action: string) {
    setError(null);
    try { await api.post(`/hr/requests/${r.id}/${action}`, {}); setOk(t("common.saved")); load(); }
    catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("ess.title")}>
        <button onClick={() => setShowForm((v) => !v)} className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">+ {t("ess.new")}</button>
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {ok && <SuccessBox message={ok} />}

      {showForm && <NewRequest onDone={() => { setShowForm(false); setOk(t("common.saved")); load(); }} onError={setError} />}

      {isHr && (
        <div className="flex gap-1 border-b border-slate-200 dark:border-slate-800 mb-4">
          {(["approve", "mine"] as const).map((k) => (
            <button key={k} onClick={() => { setTab(k); setPageNum(1); }} className={`px-4 h-10 text-sm font-medium border-b-2 -mb-px ${tab === k ? "border-brand-600 text-brand-700 dark:text-brand-300" : "border-transparent text-slate-500"}`}>
              {k === "approve" ? `${t("ess.to_approve")}${page?.totals?.to_approve ? ` (${page.totals.to_approve})` : ""}` : t("ess.all")}
            </button>
          ))}
        </div>
      )}

      {!page ? <Spinner /> : (
        <>
          <Table head={<><Th>{t("loan.reference")}</Th>{isHr && <Th>{t("hr.employee")}</Th>}<Th>{t("ess.type")}</Th><Th>{t("common.details")}</Th><Th>{t("common.date")}</Th><Th>{t("common.status")}</Th><Th end>{t("common.actions")}</Th></>}>
            {page.data.length === 0 && <EmptyRow colSpan={isHr ? 7 : 6} />}
            {page.data.map((r) => (
              <tr key={r.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-mono text-xs text-slate-500">{r.name ?? "—"}</Td>
                {isHr && <Td className="font-medium text-slate-900 dark:text-slate-100">{r.employee_name}</Td>}
                <Td>{t(`ess.type_${r.request_type}`)}{r.certificate_kind && <span className="block text-xs text-slate-400">{t(`ess.cert_${r.certificate_kind}`)}</span>}</Td>
                <Td className="text-sm">{r.summary ?? "—"}</Td>
                <Td className="text-xs tabular-nums">{r.date_request}</Td>
                <Td><Badge tone={STATE_TONE[r.state] ?? "slate"}>{t(`ess.state_${r.state}`)}</Badge></Td>
                <Td end>
                  {r.state === "draft" && <button onClick={() => act(r, "submit")} className="text-brand-600 hover:underline text-xs me-2">{t("ess.submit")}</button>}
                  {isHr && r.state === "submitted" && <><button onClick={() => act(r, "approve")} className="text-emerald-600 hover:underline text-xs me-2">{t("ess.approve")}</button><button onClick={() => act(r, "refuse")} className="text-rose-600 hover:underline text-xs me-2">{t("ess.refuse")}</button></>}
                  {isHr && r.state === "approved" && <button onClick={() => act(r, "issue")} className="text-emerald-600 hover:underline text-xs me-2">{t("ess.issue")}</button>}
                  {r.has_certificate && <a href={`/api/v1/hr/requests/${r.id}/certificate`} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline text-xs me-2">{t("ess.download")}</a>}
                  {["draft", "submitted"].includes(r.state) && <button onClick={() => act(r, "cancel")} className="text-slate-500 hover:underline text-xs">{t("common.cancel")}</button>}
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

function NewRequest({ onDone, onError }: { onDone: () => void; onError: (m: string) => void }) {
  const { t } = useI18n();
  const [type, setType] = useState<string>("certificate");
  const [form, setForm] = useState<Record<string, string>>({ certificate_kind: "salary" });
  const [busy, setBusy] = useState(false);
  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true);
    const body: Record<string, unknown> = { request_type: type, summary: form.summary || undefined, description: form.description || undefined };
    if (type === "certificate") { body.certificate_kind = form.certificate_kind; body.addressed_to = form.addressed_to || undefined; }
    if (type === "resignation") { body.last_working_day = form.last_working_day || undefined; body.resign_reason = form.resign_reason || undefined; }
    if (["equipment", "uniform", "vehicle"].includes(type)) { body.item = form.item || undefined; body.qty = form.qty ? Number(form.qty) : undefined; }
    try { await api.post("/hr/requests", body); onDone(); }
    catch (err) { onError(err instanceof ApiError ? err.message : t("common.error")); setBusy(false); }
  }

  return (
    <div className="mb-5">
      <FormCard onSubmit={submit}>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label={t("ess.type")} required>
            <Select value={type} onChange={(e) => setType(e.target.value)}>
              {TYPES.map((x) => <option key={x} value={x}>{t(`ess.type_${x}`)}</option>)}
            </Select>
          </Field>
          <Field label={t("ess.summary")}><TextInput value={form.summary ?? ""} onChange={set("summary")} /></Field>
          {type === "certificate" && <>
            <Field label={t("ess.cert_kind")} required>
              <Select value={form.certificate_kind} onChange={set("certificate_kind")}>
                <option value="salary">{t("ess.cert_salary")}</option>
                <option value="employment">{t("ess.cert_employment")}</option>
              </Select>
            </Field>
            <Field label={t("ess.addressed_to")}><TextInput value={form.addressed_to ?? ""} onChange={set("addressed_to")} /></Field>
          </>}
          {type === "resignation" && <>
            <Field label={t("forms.last_working_day")}><TextInput type="date" value={form.last_working_day ?? ""} onChange={set("last_working_day")} /></Field>
            <Field label={t("ess.reason")}><TextInput value={form.resign_reason ?? ""} onChange={set("resign_reason")} /></Field>
          </>}
          {["equipment", "uniform", "vehicle"].includes(type) && <>
            <Field label={t("ess.item")}><TextInput value={form.item ?? ""} onChange={set("item")} /></Field>
            <Field label={t("ess.qty")}><TextInput type="number" min="1" value={form.qty ?? ""} onChange={set("qty")} /></Field>
          </>}
          <div className="md:col-span-2"><Field label={t("common.details")}><TextInput value={form.description ?? ""} onChange={set("description")} /></Field></div>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}
