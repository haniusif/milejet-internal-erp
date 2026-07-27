"use client";

// Customer 360° — profile + KPIs + opportunities + invoices + payments + activities.
// mj_crm_core. Shipments KPI is a hook until milejet_shipment (P0) exists.

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { CrmCustomer360 } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, money, PageHeader, Spinner, StatCard, Table, Td, Th } from "@/components/ui";
import { Field, TextInput } from "@/components/form";

export default function Customer360Page() {
  const { t } = useI18n();
  const { can } = useAuth();
  const { id } = useParams<{ id: string }>();
  const writable = can("crm.write");

  const [c, setC] = useState<CrmCustomer360 | null>(null);
  const [tab, setTab] = useState<"opps" | "invoices" | "payments" | "activities">("opps");
  const [edit, setEdit] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    api.get<{ data: CrmCustomer360 }>(`/crm/customers/${id}`).then((r) => {
      setC(r.data);
      setForm({ email: r.data.email ?? "", phone: r.data.phone ?? "", city: r.data.city ?? "", vat: r.data.vat ?? "", credit_limit: String(r.data.credit_limit ?? "") });
    }).catch((e) => setError(e instanceof ApiError ? e.message : "error"));
  }, [id]);

  useEffect(load, [load]);

  async function save() {
    setError(null);
    try {
      await api.put(`/crm/customers/${id}`, {
        email: form.email || null, phone: form.phone || null, city: form.city || null,
        vat: form.vat || null, credit_limit: form.credit_limit === "" ? null : Number(form.credit_limit),
      });
      setEdit(false); load();
    } catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  if (!c) return error ? <ErrorBox message={error} /> : <Spinner />;
  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement>) => setForm((f) => ({ ...f, [k]: e.target.value }));

  return (
    <div>
      <PageHeader kicker={t("nav.crm")} title={c.name}>
        <Link href="/crm/customers" className="text-sm text-brand-600 hover:underline">← {t("nav.customers")}</Link>
      </PageHeader>
      {error && <ErrorBox message={error} />}

      <div className="flex flex-wrap items-center gap-2 mb-4 text-sm text-slate-500">
        {c.is_company && <Badge tone="slate">🏢 {t("crm.company")}</Badge>}
        <span>{t("crm.account_manager")}: <b className="text-slate-700 dark:text-slate-200">{c.account_manager ?? "—"}</b></span>
        {c.city && <span>· {c.city}{c.country ? `, ${c.country}` : ""}</span>}
        {writable && <button onClick={() => setEdit((v) => !v)} className="ms-auto text-brand-600 hover:underline text-xs">{t("common.edit")}</button>}
      </div>

      {edit && writable && (
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 mb-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
          <Field label={t("auth.email")}><TextInput value={form.email} onChange={set("email")} /></Field>
          <Field label={t("hr.work_phone")}><TextInput value={form.phone} onChange={set("phone")} /></Field>
          <Field label={t("crm.city")}><TextInput value={form.city} onChange={set("city")} /></Field>
          <Field label="VAT"><TextInput value={form.vat} onChange={set("vat")} /></Field>
          <Field label={t("crm.credit")}><TextInput type="number" min="0" value={form.credit_limit} onChange={set("credit_limit")} /></Field>
          <button onClick={save} className="h-9 px-4 rounded-md bg-brand-700 text-white text-sm">{t("common.save")}</button>
        </div>
      )}

      <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
        <StatCard label={t("crm.revenue")} value={money(c.kpis.invoiced)} tone="green" />
        <StatCard label={t("crm.outstanding")} value={money(c.kpis.due)} tone="rose" />
        <StatCard label={t("crm.credit")} value={money(c.credit_limit)} tone="brand" />
        <StatCard label={t("crm.opps")} value={c.kpis.opps} />
        <StatCard label={t("crm.shipments")} value={c.kpis.shipments == null ? "—" : c.kpis.shipments} hint={c.kpis.shipments == null ? t("crm.soon") : undefined} />
      </div>

      <div className="flex gap-1 border-b border-slate-200 dark:border-slate-800 mb-4">
        {(["opps", "invoices", "payments", "activities"] as const).map((k) => (
          <button key={k} onClick={() => setTab(k)} className={`px-4 h-10 text-sm font-medium border-b-2 -mb-px ${tab === k ? "border-brand-600 text-brand-700 dark:text-brand-300" : "border-transparent text-slate-500"}`}>
            {t(`crm.tab_${k}`)}
          </button>
        ))}
      </div>

      {tab === "opps" && (
        <Table head={<><Th>{t("common.name")}</Th><Th>{t("crm.expected_revenue")}</Th><Th>{t("common.status")}</Th><Th end /></>}>
          {c.opportunities.length === 0 && <EmptyRow colSpan={4} />}
          {c.opportunities.map((o) => (
            <tr key={o.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
              <Td className="font-medium text-slate-900 dark:text-slate-100">{o.name}</Td>
              <Td className="tabular-nums">{o.expected_revenue != null ? money(o.expected_revenue) : "—"}</Td>
              <Td><Badge tone={o.active ? "brand" : "rose"}>{o.active ? o.stage_name ?? t("crm.open") : t("crm.lost")}</Badge></Td>
              <Td end><Link href={`/crm/leads/${o.id}`} className="text-brand-600 hover:underline text-xs">{t("common.view")}</Link></Td>
            </tr>
          ))}
        </Table>
      )}
      {tab === "invoices" && (
        <Table head={<><Th>{t("pay.reference")}</Th><Th>{t("common.date")}</Th><Th>{t("common.amount")}</Th><Th>{t("crm.outstanding")}</Th><Th>{t("common.status")}</Th></>}>
          {c.invoices.length === 0 && <EmptyRow colSpan={5} />}
          {c.invoices.map((iv, i) => (
            <tr key={i}>
              <Td className="font-mono text-xs">{iv.name}</Td>
              <Td className="text-xs tabular-nums">{iv.date}</Td>
              <Td className="tabular-nums">{money(iv.total)}</Td>
              <Td className="tabular-nums text-rose-600">{money(iv.residual)}</Td>
              <Td><Badge tone={iv.payment_state === "paid" ? "green" : "amber"}>{iv.payment_state ?? "—"}</Badge></Td>
            </tr>
          ))}
        </Table>
      )}
      {tab === "payments" && (
        <Table head={<><Th>{t("pay.reference")}</Th><Th>{t("common.date")}</Th><Th>{t("common.amount")}</Th></>}>
          {c.payments.length === 0 && <EmptyRow colSpan={3} />}
          {c.payments.map((p, i) => (
            <tr key={i}><Td className="font-mono text-xs">{p.name}</Td><Td className="text-xs tabular-nums">{p.date}</Td><Td className="tabular-nums text-emerald-700 dark:text-emerald-400">{money(p.amount)}</Td></tr>
          ))}
        </Table>
      )}
      {tab === "activities" && (
        <Table head={<><Th>{t("crm.activity")}</Th><Th>{t("common.details")}</Th><Th>{t("crm.deadline")}</Th><Th>{t("hr.employee")}</Th></>}>
          {c.activities.length === 0 && <EmptyRow colSpan={4} />}
          {c.activities.map((a) => (
            <tr key={a.odoo_id}><Td><Badge tone="slate">{a.type}</Badge></Td><Td className="text-sm">{a.summary}</Td><Td className="text-xs tabular-nums">{a.deadline}</Td><Td className="text-xs">{a.user}</Td></tr>
          ))}
        </Table>
      )}
    </div>
  );
}
