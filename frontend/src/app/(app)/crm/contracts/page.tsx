"use client";

// Customer service contracts (mj_crm_contract): rate cards, recurring invoice
// generation (account.move → Finance), renewals/expiry, SLA terms.

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, money, PageHeader, Pagination, Spinner, SuccessBox, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

interface Row { id: number; name: string | null; partner_name: string | null; odoo_partner_id: number; user_name: string | null; date_start: string | null; date_end: string | null; recurrence: string; auto_renew: boolean; currency: string | null; amount_recurring: number; next_invoice_date: string | null; state: string; invoice_count: number }
interface Line { id: number; odoo_id: number; odoo_product_id: number | null; product_name: string | null; name: string | null; basis: string; quantity: number; price_unit: number; price_subtotal: number }
interface Invoice { name: string; invoice_date: string | null; amount_total: number; state: string | null; payment_state: string | null }
interface Detail extends Row { odoo_lead_id: number | null; payment_term: string | null; delivery_sla_hours: number | null; on_time_target: number | null; lines: Line[]; invoices: Invoice[] }
interface Product { id: number; name: string; price: number }

const STATE_TONE: Record<string, string> = { draft: "slate", running: "green", expired: "amber", closed: "slate", cancelled: "rose" };

export default function ContractsPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const canWrite = can("crm.write");
  const canInvoice = can("finance.view") || canWrite;

  const [page, setPage] = useState<Paginated<Row> | null>(null);
  const [pageNum, setPageNum] = useState(1);
  const [sel, setSel] = useState<Detail | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const load = useCallback(() => {
    api.get<Paginated<Row>>(`/crm/contracts${qs({ page: pageNum })}`).then(setPage).catch(() => setPage(null));
  }, [pageNum]);
  useEffect(load, [load]);

  const openDetail = useCallback((id: number) => {
    api.get<{ data: Detail }>(`/crm/contracts/${id}`).then((r) => setSel(r.data)).catch(() => setSel(null));
  }, []);

  async function act(id: number, action: string) {
    setError(null); setOk(null);
    try {
      const r = await api.post<{ data: Detail }>(`/crm/contracts/${id}/${action}`, {});
      setSel(r.data); setOk(t("common.saved")); load();
    } catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.crm")} title={t("con.title")}>
        {canWrite && <button onClick={() => setShowNew((v) => !v)} className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">+ {t("con.new")}</button>}
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {ok && <SuccessBox message={ok} />}

      {showNew && <NewContract onDone={(id) => { setShowNew(false); setOk(t("common.saved")); load(); openDetail(id); }} onError={setError} />}

      {!page ? <Spinner /> : (
        <>
          <Table head={<><Th>{t("loan.reference")}</Th><Th>{t("con.customer")}</Th><Th>{t("con.recurrence")}</Th><Th>{t("con.amount")}</Th><Th>{t("con.next_invoice")}</Th><Th>{t("con.invoices")}</Th><Th>{t("common.status")}</Th></>}>
            {page.data.length === 0 && <EmptyRow colSpan={7} />}
            {page.data.map((r) => (
              <tr key={r.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 cursor-pointer" onClick={() => openDetail(r.id)}>
                <Td className="font-mono text-xs text-slate-500">{r.name}</Td>
                <Td className="font-medium text-slate-900 dark:text-slate-100">{r.partner_name}</Td>
                <Td className="text-xs">{t(`con.rec_${r.recurrence}`)}{r.auto_renew && <span className="ms-1 text-emerald-500" title={t("con.auto_renew")}>↻</span>}</Td>
                <Td className="tabular-nums">{money(r.amount_recurring)}</Td>
                <Td className="text-xs tabular-nums">{r.state === "running" ? r.next_invoice_date : "—"}</Td>
                <Td className="tabular-nums text-xs">{r.invoice_count || "—"}</Td>
                <Td><Badge tone={STATE_TONE[r.state] ?? "slate"}>{t(`con.state_${r.state}`)}</Badge></Td>
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}

      {sel && (
        <DetailDrawer d={sel} canWrite={canWrite} canInvoice={canInvoice}
          onClose={() => setSel(null)} onAct={(a) => act(sel.id, a)}
          onInvoice={async () => { setError(null); try { const r = await api.post<{ data: Detail }>(`/crm/contracts/${sel.id}/invoice`, {}); setSel(r.data); setOk(t("common.saved")); load(); } catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); } }}
        />
      )}
    </div>
  );
}

function DetailDrawer({ d, canWrite, canInvoice, onClose, onAct, onInvoice }: {
  d: Detail; canWrite: boolean; canInvoice: boolean; onClose: () => void; onAct: (a: string) => void; onInvoice: () => void;
}) {
  const { t } = useI18n();
  return (
    <div className="fixed inset-0 z-40 flex justify-end bg-black/30" onClick={onClose}>
      <div className="w-full max-w-3xl h-full overflow-y-auto bg-white dark:bg-slate-900 shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-start justify-between mb-4">
          <div>
            <p className="font-mono text-xs text-slate-400">{d.name}</p>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{d.partner_name}</h2>
            <p className="text-sm text-slate-500">{t(`con.rec_${d.recurrence}`)}{d.auto_renew ? ` · ↻ ${t("con.auto_renew")}` : ""} · {d.date_start ?? "—"} → {d.date_end ?? "—"}</p>
          </div>
          <div className="text-end">
            <Badge tone={STATE_TONE[d.state] ?? "slate"}>{t(`con.state_${d.state}`)}</Badge>
            <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{money(d.amount_recurring)}</p>
            {d.state === "running" && <p className="text-xs text-slate-500">{t("con.next_invoice")}: {d.next_invoice_date}</p>}
          </div>
        </div>

        <div className="flex flex-wrap gap-2 mb-5">
          {canWrite && d.state === "draft" && <Wf onClick={() => onAct("confirm")} label={t("con.confirm")} />}
          {canInvoice && d.state === "running" && <button onClick={onInvoice} className="h-8 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">{t("con.generate_invoice")}</button>}
          {canWrite && ["running", "expired"].includes(d.state) && <button onClick={() => onAct("renew")} className="h-8 px-4 rounded-md bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold">{t("con.renew")}</button>}
          {canWrite && ["running", "expired"].includes(d.state) && <button onClick={() => onAct("close")} className="h-8 px-3 rounded-md text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs">{t("con.close")}</button>}
          {canWrite && !["closed", "cancelled"].includes(d.state) && <button onClick={() => onAct("cancel")} className="h-8 px-3 rounded-md text-rose-600 hover:bg-rose-50 text-xs">{t("common.cancel")}</button>}
        </div>

        <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("con.rate_card")}</h3>
        {d.lines.length === 0 ? <p className="text-sm text-slate-400 mb-4">{t("con.no_lines")}</p> : (
          <Table head={<><Th>{t("con.product")}</Th><Th>{t("con.basis")}</Th><Th>{t("con.qty")}</Th><Th>{t("con.price")}</Th><Th>{t("con.subtotal")}</Th></>}>
            {d.lines.map((l) => (
              <tr key={l.odoo_id}>
                <Td className="font-medium">{l.name || l.product_name}</Td>
                <Td className="text-xs">{t(`con.basis_${l.basis}`)}</Td>
                <Td className="tabular-nums">{l.quantity}</Td>
                <Td className="tabular-nums">{money(l.price_unit)}</Td>
                <Td className="tabular-nums font-semibold">{money(l.price_subtotal)}</Td>
              </tr>
            ))}
          </Table>
        )}

        <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mt-5 mb-2">{t("con.invoices")}</h3>
        {d.invoices.length === 0 ? <p className="text-sm text-slate-400">{t("con.no_invoices")}</p> : (
          <Table head={<><Th>{t("loan.reference")}</Th><Th>{t("common.date")}</Th><Th>{t("con.subtotal")}</Th><Th>{t("common.status")}</Th></>}>
            {d.invoices.map((iv, i) => (
              <tr key={i}>
                <Td className="font-mono text-xs">{iv.name}</Td>
                <Td className="text-xs tabular-nums">{iv.invoice_date ?? "—"}</Td>
                <Td className="tabular-nums">{money(iv.amount_total)}</Td>
                <Td><Badge tone={iv.state === "posted" ? "green" : "slate"}>{iv.state}</Badge></Td>
              </tr>
            ))}
          </Table>
        )}

        {(d.delivery_sla_hours || d.on_time_target) && (
          <div className="mt-5">
            <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("con.sla")}</h3>
            <p className="text-sm text-slate-600 dark:text-slate-300">{d.delivery_sla_hours ? `${t("con.sla_hours")}: ${d.delivery_sla_hours}` : ""}{d.delivery_sla_hours && d.on_time_target ? " · " : ""}{d.on_time_target ? `${t("con.on_time")}: ${d.on_time_target}%` : ""}</p>
          </div>
        )}
      </div>
    </div>
  );
}

function Wf({ onClick, label }: { onClick: () => void; label: string }) {
  return <button onClick={onClick} className="h-8 px-4 rounded-md bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold">{label}</button>;
}

interface NewLine { product_id: string; name: string; basis: string; quantity: string; price_unit: string }

function NewContract({ onDone, onError }: { onDone: (id: number) => void; onError: (m: string) => void }) {
  const { t } = useI18n();
  const [customers, setCustomers] = useState<{ odoo_id: number; name: string }[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [form, setForm] = useState<Record<string, string>>({ recurrence: "monthly" });
  const [lines, setLines] = useState<NewLine[]>([{ product_id: "", name: "", basis: "fixed", quantity: "1", price_unit: "" }]);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.get<{ data: { odoo_id: number; name: string }[] }>("/crm/customers?per_page=100").then((r) => setCustomers(r.data)).catch(() => setCustomers([]));
    api.get<{ data: Product[] }>("/crm/contracts/products").then((r) => setProducts(r.data)).catch(() => setProducts([]));
  }, []);

  const setLine = (i: number, k: keyof NewLine, v: string) => setLines((ls) => ls.map((l, j) => {
    if (j !== i) return l;
    const nl = { ...l, [k]: v };
    if (k === "product_id") { const p = products.find((x) => x.id === Number(v)); if (p) { nl.name = p.name; if (!nl.price_unit) nl.price_unit = String(p.price); } }
    return nl;
  }));

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true); onError("");
    try {
      const r = await api.post<{ data: { id: number } }>("/crm/contracts", {
        partner_id: Number(form.partner_id), recurrence: form.recurrence,
        date_start: form.date_start || undefined, date_end: form.date_end || undefined,
        auto_renew: form.auto_renew === "1",
        lines: lines.filter((l) => l.product_id).map((l) => ({
          product_id: Number(l.product_id), name: l.name || undefined, basis: l.basis,
          quantity: Number(l.quantity) || 1, price_unit: Number(l.price_unit) || 0,
        })),
      });
      onDone(r.data.id);
    } catch (err) { onError(err instanceof ApiError ? err.message : t("common.error")); setBusy(false); }
  }

  return (
    <div className="mb-5">
      <FormCard onSubmit={submit}>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Field label={t("con.customer")} required>
            <Select value={form.partner_id ?? ""} onChange={(e) => setForm((f) => ({ ...f, partner_id: e.target.value }))}>
              <option value="">—</option>
              {customers.map((c) => <option key={c.odoo_id} value={c.odoo_id}>{c.name}</option>)}
            </Select>
          </Field>
          <Field label={t("con.recurrence")}>
            <Select value={form.recurrence} onChange={(e) => setForm((f) => ({ ...f, recurrence: e.target.value }))}>
              <option value="monthly">{t("con.rec_monthly")}</option>
              <option value="quarterly">{t("con.rec_quarterly")}</option>
              <option value="yearly">{t("con.rec_yearly")}</option>
            </Select>
          </Field>
          <Field label={t("con.start")}><TextInput type="date" value={form.date_start ?? ""} onChange={(e) => setForm((f) => ({ ...f, date_start: e.target.value }))} /></Field>
          <Field label={t("con.end")}><TextInput type="date" value={form.date_end ?? ""} onChange={(e) => setForm((f) => ({ ...f, date_end: e.target.value }))} /></Field>
          <Field label={t("con.auto_renew")}>
            <Select value={form.auto_renew ?? "0"} onChange={(e) => setForm((f) => ({ ...f, auto_renew: e.target.value }))}>
              <option value="0">—</option><option value="1">{t("con.auto_renew")}</option>
            </Select>
          </Field>
        </div>

        <div className="mt-4">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("con.rate_card")}</span>
            <button type="button" onClick={() => setLines((ls) => [...ls, { product_id: "", name: "", basis: "fixed", quantity: "1", price_unit: "" }])} className="text-brand-600 hover:underline text-xs">+ {t("con.add_line")}</button>
          </div>
          <Table head={<><Th>{t("con.product")}</Th><Th>{t("con.basis")}</Th><Th>{t("con.qty")}</Th><Th>{t("con.price")}</Th><Th></Th></>}>
            {lines.map((l, i) => (
              <tr key={i}>
                <Td>
                  <Select value={l.product_id} onChange={(e) => setLine(i, "product_id", e.target.value)}>
                    <option value="">—</option>
                    {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </Select>
                </Td>
                <Td>
                  <Select value={l.basis} onChange={(e) => setLine(i, "basis", e.target.value)}>
                    <option value="fixed">{t("con.basis_fixed")}</option>
                    <option value="per_unit">{t("con.basis_per_unit")}</option>
                  </Select>
                </Td>
                <Td><TextInput type="number" min="0" value={l.quantity} onChange={(e) => setLine(i, "quantity", e.target.value)} /></Td>
                <Td><TextInput type="number" min="0" step="0.01" value={l.price_unit} onChange={(e) => setLine(i, "price_unit", e.target.value)} /></Td>
                <Td end>{lines.length > 1 && <button type="button" onClick={() => setLines((ls) => ls.filter((_, j) => j !== i))} className="text-rose-500 hover:underline text-xs">×</button>}</Td>
              </tr>
            ))}
          </Table>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}
