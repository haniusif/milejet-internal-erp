"use client";

// Lead / opportunity detail + inline edit + activities + notes + mini-360.
// mj_crm_core — surfaces existing Odoo crm.lead richly.

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { CrmLeadDetail, CrmConfig } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, ErrorBox, money, PageHeader, Spinner, SuccessBox } from "@/components/ui";
import { Field, inputCls, Select, TextInput } from "@/components/form";

const ACT_TONE: Record<string, string> = { overdue: "rose", today: "amber", planned: "slate" };

export default function LeadDetailPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const writable = can("crm.write");

  const [lead, setLead] = useState<CrmLeadDetail | null>(null);
  const [cfg, setCfg] = useState<CrmConfig | null>(null);
  const [form, setForm] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const load = useCallback(() => {
    api.get<{ data: CrmLeadDetail }>(`/crm/leads/${id}`).then((r) => {
      setLead(r.data);
      setForm({
        name: r.data.name ?? "",
        contact_name: r.data.contact_name ?? "",
        email_from: r.data.email_from ?? "",
        phone: r.data.phone ?? "",
        expected_revenue: r.data.expected_revenue != null ? String(r.data.expected_revenue) : "",
        probability: r.data.probability != null ? String(r.data.probability) : "",
        date_deadline: r.data.date_deadline ?? "",
        description: r.data.description ?? "",
      });
    }).catch((e) => setError(e instanceof ApiError ? e.message : "error"));
  }, [id]);

  useEffect(load, [load]);
  useEffect(() => {
    if (writable) api.get<{ data: CrmConfig }>("/crm/config").then((r) => setCfg(r.data)).catch(() => {});
  }, [writable]);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function save() {
    setBusy(true); setError(null); setOk(null);
    try {
      await api.put(`/crm/leads/${id}`, {
        name: form.name,
        contact_name: form.contact_name || null,
        email_from: form.email_from || null,
        phone: form.phone || null,
        expected_revenue: form.expected_revenue === "" ? null : Number(form.expected_revenue),
        probability: form.probability === "" ? null : Number(form.probability),
        date_deadline: form.date_deadline || null,
        description: form.description || null,
      });
      setOk(t("common.saved")); load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally { setBusy(false); }
  }

  async function act(path: string, body?: unknown) {
    setError(null);
    try { await api.post(`/crm/leads/${id}/${path}`, body); load(); }
    catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  if (!lead) return error ? <ErrorBox message={error} /> : <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.crm")} title={lead.name}>
        <Link href="/crm" className="text-sm text-brand-600 hover:underline">← {t("crm.pipeline")}</Link>
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {ok && <SuccessBox message={ok} />}

      <div className="flex flex-wrap items-center gap-2 mb-4">
        <Badge tone={lead.active ? (lead.stage_name === "Won" ? "green" : "brand") : "rose"}>
          {lead.active ? lead.stage_name ?? t("crm.open") : t("crm.lost")}
        </Badge>
        {lead.probability != null && <span className="text-xs text-slate-500">{lead.probability}%</span>}
        {lead.tag_names && <span className="text-xs text-brand-500">{lead.tag_names}</span>}
        {writable && lead.active && (
          <span className="ms-auto flex gap-2">
            <button onClick={() => act("won")} className="h-8 px-3 rounded-md bg-emerald-600 text-white text-xs">{t("crm.mark_won")}</button>
            <button
              onClick={() => {
                const rid = cfg?.lost_reasons?.length ? prompt(t("crm.lost_reason_prompt") + "\n" + cfg.lost_reasons.map((r) => `${r.odoo_id}=${r.name}`).join("\n")) : null;
                act("lost", rid ? { lost_reason_id: Number(rid) } : {});
              }}
              className="h-8 px-3 rounded-md bg-rose-600 text-white text-xs">{t("crm.mark_lost")}</button>
          </span>
        )}
        {writable && !lead.active && (
          <button onClick={() => act("restore")} className="ms-auto h-8 px-3 rounded-md bg-slate-600 text-white text-xs">{t("crm.restore")}</button>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {/* DETAILS — inline edit */}
        <div className="lg:col-span-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-3">
          <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("common.details")}</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <Field label={t("common.name")}><TextInput value={form.name} onChange={set("name")} disabled={!writable} /></Field>
            <Field label={t("crm.contact")}><TextInput value={form.contact_name} onChange={set("contact_name")} disabled={!writable} /></Field>
            <Field label={t("auth.email")}><TextInput type="email" value={form.email_from} onChange={set("email_from")} disabled={!writable} /></Field>
            <Field label={t("hr.work_phone")}><TextInput value={form.phone} onChange={set("phone")} disabled={!writable} /></Field>
            <Field label={t("crm.expected_revenue")}><TextInput type="number" min="0" step="0.01" value={form.expected_revenue} onChange={set("expected_revenue")} disabled={!writable} /></Field>
            <Field label={t("crm.probability")}><TextInput type="number" min="0" max="100" value={form.probability} onChange={set("probability")} disabled={!writable} /></Field>
            <Field label={t("crm.deadline")}><TextInput type="date" value={form.date_deadline} onChange={set("date_deadline")} disabled={!writable} /></Field>
          </div>
          <Field label={t("common.details")}>
            <textarea value={form.description} onChange={set("description")} disabled={!writable} rows={3} className={inputCls} />
          </Field>
          {writable && (
            <button onClick={save} disabled={busy} className="h-9 px-5 rounded-md bg-brand-700 hover:bg-brand-800 disabled:opacity-50 text-white text-sm font-medium">
              {busy ? "…" : t("common.save")}
            </button>
          )}
        </div>

        {/* ACTIVITY + NOTES + mini-360 */}
        <div className="space-y-5">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("crm.activity")}</h2>
              {writable && (
                <select
                  onChange={(e) => { if (e.target.value) { const s = prompt(t("crm.activity_summary")); act("activities", { type: e.target.value, summary: s || undefined }); e.target.value = ""; } }}
                  className="h-8 text-xs border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900">
                  <option value="">+ {t("crm.log_activity")}</option>
                  {["call", "meeting", "todo", "email"].map((x) => <option key={x} value={x}>{t(`crm.act_${x}`)}</option>)}
                </select>
              )}
            </div>
            {lead.activities.length === 0 ? <p className="text-xs text-slate-400">{t("common.empty")}</p> : (
              <ul className="space-y-2">
                {lead.activities.map((a) => (
                  <li key={a.odoo_id} className="flex items-start justify-between gap-2 text-sm">
                    <span>
                      <Badge tone={ACT_TONE[a.state ?? "planned"] ?? "slate"}>{a.type}</Badge>{" "}
                      <span className="text-slate-700 dark:text-slate-200">{a.summary}</span>
                      <span className="block text-[11px] text-slate-400">{a.deadline} · {a.user}</span>
                    </span>
                    {writable && (
                      <button onClick={() => api.post(`/crm/activities/${a.odoo_id}/done`, {}).then(load)} className="text-emerald-600 hover:underline text-xs shrink-0">✓</button>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
            <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">{t("crm.notes")}</h2>
            {writable && (
              <form onSubmit={(e) => { e.preventDefault(); const fd = new FormData(e.currentTarget); const n = (fd.get("note") as string)?.trim(); if (n) act("note", { note: n }); e.currentTarget.reset(); }} className="flex gap-2 mb-3">
                <input name="note" placeholder={t("crm.write_note")} className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm flex-1 bg-white dark:bg-slate-900" />
                <button className="h-9 px-3 rounded-md bg-brand-700 text-white text-sm">{t("crm.post")}</button>
              </form>
            )}
            {lead.notes.length === 0 ? <p className="text-xs text-slate-400">{t("common.empty")}</p> : (
              <ul className="space-y-2">
                {lead.notes.map((n, i) => (
                  <li key={i} className="text-sm border-b border-slate-100 dark:border-slate-800 pb-1.5 last:border-0">
                    <p className="text-slate-700 dark:text-slate-200 whitespace-pre-line">{n.body}</p>
                    <p className="text-[11px] text-slate-400">{n.author} · {n.date?.slice(0, 16).replace("T", " ")}</p>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {lead.customer && (
            <Link href={`/crm/customers/${lead.customer.id}`} className="block bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 hover:ring-1 hover:ring-brand-300">
              <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">{t("crm.customer")}</h2>
              <p className="font-medium text-slate-900 dark:text-slate-100">{lead.customer.name}</p>
              <p className="text-xs text-slate-500">{t("crm.account_manager")}: {lead.customer.account_manager ?? "—"} · {t("crm.credit")}: {money(lead.customer.credit_limit)}</p>
              <p className="text-xs text-brand-600 mt-1">{t("crm.open_360")} →</p>
            </Link>
          )}
        </div>
      </div>
    </div>
  );
}
