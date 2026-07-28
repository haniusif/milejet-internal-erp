"use client";

// Support tickets (mj_crm_helpdesk): SLA-tracked helpdesk with a contract-driven
// deadline. List with SLA badges + filters + stat tiles; detail drawer with the
// new→in_progress→waiting→resolved→closed workflow; create form.

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, PageHeader, Pagination, Spinner, StatCard, SuccessBox, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput, TextArea } from "@/components/form";

interface Row { id: number; name: string | null; subject: string; partner_name: string | null; odoo_partner_id: number | null; category: string; priority: string; stage: string; user_name: string | null; team_name: string | null; sla_state: string; sla_deadline: string | null; sla_hours: number | null }
interface Detail extends Row { description: string | null; resolution: string | null; odoo_contract_id: number | null; contract_name: string | null; source: string | null; related_ref: string | null; date_open: string | null; date_closed: string | null; satisfaction: string | null }
interface Stats { open: number; breached: number; due_soon: number; mine: number; by_stage: Record<string, number> }

const STAGE_TONE: Record<string, string> = { new: "brand", in_progress: "amber", waiting: "indigo", resolved: "green", closed: "slate", cancelled: "rose" };
const SLA_TONE: Record<string, string> = { on_track: "green", due_soon: "amber", breached: "rose" };
const PRIO_TONE: Record<string, string> = { low: "slate", normal: "brand", high: "amber", urgent: "rose" };
const CATEGORIES = ["delivery_issue", "billing", "complaint", "damage", "inquiry", "other"] as const;
const PRIORITIES = ["low", "normal", "high", "urgent"] as const;
const SOURCES = ["phone", "email", "portal", "whatsapp"] as const;

export default function TicketsPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const canWrite = can("crm.write");

  const [page, setPage] = useState<Paginated<Row> | null>(null);
  const [stats, setStats] = useState<Stats | null>(null);
  const [pageNum, setPageNum] = useState(1);
  const [filter, setFilter] = useState<"open" | "mine" | "breached" | "all">("open");
  const [sel, setSel] = useState<Detail | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const params = () => {
    const p: Record<string, string | number | undefined> = { page: pageNum };
    if (filter === "open") p.open = 1;
    if (filter === "mine") p.mine = 1;
    if (filter === "breached") p.breached = 1;
    return p;
  };

  const load = useCallback(() => {
    api.get<Paginated<Row>>(`/crm/tickets${qs(params())}`).then(setPage).catch(() => setPage(null));
    api.get<{ data: Stats }>("/crm/tickets/stats").then((r) => setStats(r.data)).catch(() => setStats(null));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pageNum, filter]);
  useEffect(load, [load]);

  const openDetail = useCallback((id: number) => {
    api.get<{ data: Detail }>(`/crm/tickets/${id}`).then((r) => setSel(r.data)).catch(() => setSel(null));
  }, []);

  async function act(id: number, action: string, body: Record<string, unknown> = {}) {
    setError(null); setOk(null);
    try {
      const r = await api.post<{ data: Detail }>(`/crm/tickets/${id}/${action}`, body);
      setSel(r.data); setOk(t("common.saved")); load();
    } catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.crm")} title={t("tkt.title")}>
        {canWrite && <button onClick={() => setShowNew((v) => !v)} className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">+ {t("tkt.new")}</button>}
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {ok && <SuccessBox message={ok} />}

      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
          <StatCard label={t("tkt.open")} value={stats.open} tone="brand" />
          <StatCard label={t("tkt.breached")} value={stats.breached} tone="rose" />
          <StatCard label={t("tkt.due_soon")} value={stats.due_soon} tone="amber" />
          <StatCard label={t("tkt.mine")} value={stats.mine} />
        </div>
      )}

      {showNew && <NewTicket onDone={(id) => { setShowNew(false); setOk(t("common.saved")); load(); openDetail(id); }} onError={setError} />}

      <div className="flex gap-1 border-b border-slate-200 dark:border-slate-800 mb-4">
        {(["open", "mine", "breached", "all"] as const).map((k) => (
          <button key={k} onClick={() => { setFilter(k); setPageNum(1); }}
            className={`px-4 h-10 text-sm font-medium border-b-2 -mb-px ${filter === k ? "border-brand-600 text-brand-700 dark:text-brand-300" : "border-transparent text-slate-500"}`}>
            {t(`tkt.${k}`)}
          </button>
        ))}
      </div>

      {!page ? <Spinner /> : (
        <>
          <Table head={<><Th>{t("loan.reference")}</Th><Th>{t("tkt.subject")}</Th><Th>{t("tkt.customer")}</Th><Th>{t("tkt.priority")}</Th><Th>{t("tkt.assignee")}</Th><Th>{t("tkt.sla")}</Th><Th>{t("tkt.stage")}</Th></>}>
            {page.data.length === 0 && <EmptyRow colSpan={7} />}
            {page.data.map((r) => (
              <tr key={r.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 cursor-pointer" onClick={() => openDetail(r.id)}>
                <Td className="font-mono text-xs text-slate-500">{r.name}</Td>
                <Td className="font-medium text-slate-900 dark:text-slate-100">{r.subject}<span className="block text-xs text-slate-400">{t(`tkt.cat_${r.category}`)}</span></Td>
                <Td className="text-sm">{r.partner_name ?? "—"}</Td>
                <Td><Badge tone={PRIO_TONE[r.priority] ?? "slate"}>{t(`tkt.prio_${r.priority}`)}</Badge></Td>
                <Td className="text-sm">{r.user_name ?? "—"}</Td>
                <Td>{["closed", "cancelled"].includes(r.stage) ? <span className="text-xs text-slate-400">—</span> : <Badge tone={SLA_TONE[r.sla_state] ?? "slate"}>{t(`tkt.sla_${r.sla_state}`)}</Badge>}</Td>
                <Td><Badge tone={STAGE_TONE[r.stage] ?? "slate"}>{t(`tkt.stage_${r.stage}`)}</Badge></Td>
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}

      {sel && <DetailDrawer d={sel} canWrite={canWrite} onClose={() => setSel(null)} onAct={act} />}
    </div>
  );
}

function DetailDrawer({ d, canWrite, onClose, onAct }: { d: Detail; canWrite: boolean; onClose: () => void; onAct: (id: number, action: string, body?: Record<string, unknown>) => void }) {
  const { t } = useI18n();
  const [resolution, setResolution] = useState(d.resolution ?? "");
  const [satisfaction, setSatisfaction] = useState(d.satisfaction ?? "");
  const open = ["new", "in_progress", "waiting"].includes(d.stage);

  return (
    <div className="fixed inset-0 z-40 flex justify-end bg-black/30" onClick={onClose}>
      <div className="w-full max-w-2xl h-full overflow-y-auto bg-white dark:bg-slate-900 shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-start justify-between mb-4">
          <div>
            <p className="font-mono text-xs text-slate-400">{d.name}</p>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{d.subject}</h2>
            <p className="text-sm text-slate-500">{d.partner_name ?? "—"}{d.contract_name ? ` · ${d.contract_name}` : ""} · {t(`tkt.cat_${d.category}`)}</p>
          </div>
          <div className="text-end space-y-1">
            <Badge tone={STAGE_TONE[d.stage] ?? "slate"}>{t(`tkt.stage_${d.stage}`)}</Badge>
            {open && <div><Badge tone={SLA_TONE[d.sla_state] ?? "slate"}>{t(`tkt.sla_${d.sla_state}`)}</Badge></div>}
          </div>
        </div>

        <div className="flex flex-wrap gap-2 mb-5">
          {canWrite && !["in_progress", "resolved", "closed", "cancelled"].includes(d.stage) && <Wf onClick={() => onAct(d.id, "assign")} label={t("tkt.assign")} />}
          {canWrite && d.stage === "in_progress" && <button onClick={() => onAct(d.id, "wait")} className="h-8 px-3 rounded-md bg-slate-100 dark:bg-slate-800 text-xs">{t("tkt.wait")}</button>}
          {canWrite && d.stage === "waiting" && <Wf onClick={() => onAct(d.id, "resume")} label={t("tkt.resume")} />}
          {canWrite && ["in_progress", "waiting"].includes(d.stage) && <button onClick={() => onAct(d.id, "resolve", { resolution })} className="h-8 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">{t("tkt.resolve")}</button>}
          {canWrite && ["resolved", "in_progress", "waiting"].includes(d.stage) && <button onClick={() => onAct(d.id, "close", { resolution, satisfaction: satisfaction || undefined })} className="h-8 px-3 rounded-md bg-slate-700 text-white text-xs">{t("tkt.close")}</button>}
          {canWrite && ["resolved", "closed"].includes(d.stage) && <button onClick={() => onAct(d.id, "reopen")} className="h-8 px-3 rounded-md text-brand-600 hover:bg-brand-50 text-xs">{t("tkt.reopen")}</button>}
          {canWrite && !["closed", "cancelled"].includes(d.stage) && <button onClick={() => onAct(d.id, "cancel")} className="h-8 px-3 rounded-md text-rose-600 hover:bg-rose-50 text-xs">{t("common.cancel")}</button>}
        </div>

        <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm mb-4">
          <Info label={t("tkt.priority")}>{t(`tkt.prio_${d.priority}`)}</Info>
          <Info label={t("tkt.source")}>{d.source ? t(`tkt.src_${d.source}`) : "—"}</Info>
          <Info label={t("tkt.assignee")}>{d.user_name ?? "—"}</Info>
          <Info label={t("tkt.team")}>{d.team_name ?? "—"}</Info>
          <Info label={t("tkt.sla_deadline")}>{d.sla_deadline ? new Date(d.sla_deadline).toLocaleString() : "—"}{d.sla_hours ? ` (${d.sla_hours}h)` : ""}</Info>
          <Info label={t("tkt.related_ref")}>{d.related_ref ?? "—"}</Info>
        </dl>

        {d.description && (
          <div className="mb-4">
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{t("tkt.description")}</h3>
            <p className="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{d.description}</p>
          </div>
        )}

        <div className="mb-4">
          <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{t("tkt.resolution")}</h3>
          {open ? <TextArea value={resolution} onChange={(e) => setResolution(e.target.value)} rows={2} placeholder={t("tkt.resolution")} />
            : <p className="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{d.resolution || "—"}</p>}
        </div>

        {["in_progress", "waiting", "resolved"].includes(d.stage) && canWrite && (
          <div className="mb-2">
            <h3 className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{t("tkt.satisfaction")}</h3>
            <Select value={satisfaction} onChange={(e) => setSatisfaction(e.target.value)}>
              <option value="">—</option>
              <option value="good">{t("tkt.sat_good")}</option>
              <option value="ok">{t("tkt.sat_ok")}</option>
              <option value="bad">{t("tkt.sat_bad")}</option>
            </Select>
          </div>
        )}
        {!open && d.satisfaction && <p className="text-sm text-slate-500">{t("tkt.satisfaction")}: {t(`tkt.sat_${d.satisfaction}`)}</p>}
      </div>
    </div>
  );
}

function Info({ label, children }: { label: string; children: React.ReactNode }) {
  return <div><dt className="text-xs text-slate-400">{label}</dt><dd className="text-slate-700 dark:text-slate-200">{children}</dd></div>;
}
function Wf({ onClick, label }: { onClick: () => void; label: string }) {
  return <button onClick={onClick} className="h-8 px-4 rounded-md bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold">{label}</button>;
}

function NewTicket({ onDone, onError }: { onDone: (id: number) => void; onError: (m: string) => void }) {
  const { t } = useI18n();
  const [customers, setCustomers] = useState<{ odoo_id: number; name: string }[]>([]);
  const [form, setForm] = useState<Record<string, string>>({ category: "delivery_issue", priority: "normal", source: "phone" });
  const [busy, setBusy] = useState(false);

  useEffect(() => { api.get<{ data: { odoo_id: number; name: string }[] }>("/crm/customers?per_page=100").then((r) => setCustomers(r.data)).catch(() => setCustomers([])); }, []);
  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true); onError("");
    try {
      const r = await api.post<{ data: { id: number } }>("/crm/tickets", {
        subject: form.subject, partner_id: form.partner_id ? Number(form.partner_id) : undefined,
        category: form.category, priority: form.priority, source: form.source,
        description: form.description || undefined, related_ref: form.related_ref || undefined,
      });
      onDone(r.data.id);
    } catch (err) { onError(err instanceof ApiError ? err.message : t("common.error")); setBusy(false); }
  }

  return (
    <div className="mb-5">
      <FormCard onSubmit={submit}>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="md:col-span-2"><Field label={t("tkt.subject")} required><TextInput value={form.subject ?? ""} onChange={set("subject")} /></Field></div>
          <Field label={t("tkt.customer")}>
            <Select value={form.partner_id ?? ""} onChange={set("partner_id")}>
              <option value="">—</option>
              {customers.map((c) => <option key={c.odoo_id} value={c.odoo_id}>{c.name}</option>)}
            </Select>
          </Field>
          <Field label={t("tkt.category")}>
            <Select value={form.category} onChange={set("category")}>
              {CATEGORIES.map((c) => <option key={c} value={c}>{t(`tkt.cat_${c}`)}</option>)}
            </Select>
          </Field>
          <Field label={t("tkt.priority")}>
            <Select value={form.priority} onChange={set("priority")}>
              {PRIORITIES.map((p) => <option key={p} value={p}>{t(`tkt.prio_${p}`)}</option>)}
            </Select>
          </Field>
          <Field label={t("tkt.source")}>
            <Select value={form.source} onChange={set("source")}>
              {SOURCES.map((s) => <option key={s} value={s}>{t(`tkt.src_${s}`)}</option>)}
            </Select>
          </Field>
          <Field label={t("tkt.related_ref")}><TextInput value={form.related_ref ?? ""} onChange={set("related_ref")} /></Field>
          <div className="md:col-span-3"><Field label={t("tkt.description")}><TextArea value={form.description ?? ""} onChange={set("description")} rows={2} /></Field></div>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}
