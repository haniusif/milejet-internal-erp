"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { CompanyEntry } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, PageHeader, Spinner, StatCard, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

type CompaniesPayload = {
  data: CompanyEntry[];
  totals: { companies: number; branches: number; assigned: number; unassigned: number };
};

export default function CompaniesPage() {
  const { t } = useI18n();
  const { can } = useAuth();

  const [payload, setPayload] = useState<CompaniesPayload | null>(null);
  const [editing, setEditing] = useState<CompanyEntry | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const manager = can("companies.manage");

  const load = useCallback(() => {
    api.get<CompaniesPayload>("/hr/companies").then(setPayload).catch(() => setPayload(null));
  }, []);

  useEffect(load, [load]);

  async function setActive(c: CompanyEntry, active: boolean) {
    if (!active && !confirm(t("co.confirm_archive"))) return;
    setError(null);
    try {
      await api.post(`/hr/companies/${c.id}/${active ? "restore" : "archive"}`, {});
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  if (!payload) return <Spinner />;

  // Parents first, each followed by its branches (indented).
  const parents = payload.data.filter((c) => !c.parent_odoo_id);
  const ordered = parents.flatMap((p) => [
    p,
    ...payload.data.filter((c) => c.parent_odoo_id === p.odoo_id),
  ]);
  // Orphan branches whose parent isn't in the list (safety)
  const orphans = payload.data.filter((c) => c.parent_odoo_id && !parents.some((p) => p.odoo_id === c.parent_odoo_id));
  const rows = [...ordered, ...orphans];

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.companies")}>
        {manager && (
          <button
            onClick={() => {
              setEditing(null);
              setShowForm((v) => !v);
            }}
            className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
          >
            + {t("co.new")}
          </button>
        )}
      </PageHeader>

      {error && <ErrorBox message={error} />}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <StatCard label={t("co.companies")} value={payload.totals.companies} tone="brand" hint={t("co.hint_companies")} />
        <StatCard label={t("co.branches")} value={payload.totals.branches} hint={t("co.hint_branches")} />
        <StatCard label={t("office.assigned")} value={payload.totals.assigned} tone="green" hint={t("co.hint_assigned")} />
        <StatCard label={t("co.unassigned")} value={payload.totals.unassigned} tone="amber" hint={t("co.hint_unassigned")} />
      </div>

      {(showForm || editing) && manager && (
        <CompanyForm
          key={editing?.id ?? "new"}
          company={editing}
          parents={parents}
          onDone={() => {
            setShowForm(false);
            setEditing(null);
            load();
          }}
        />
      )}

      <Table
        head={
          <>
            <Th>{t("common.name")}</Th>
            <Th>{t("co.registry")}</Th>
            <Th>{t("co.vat")}</Th>
            <Th>{t("hr.contact")}</Th>
            <Th>{t("co.city")}</Th>
            <Th>{t("nav.employees")}</Th>
            <Th>{t("common.status")}</Th>
            {manager && <Th end>{t("common.actions")}</Th>}
          </>
        }
      >
        {rows.length === 0 && <EmptyRow colSpan={manager ? 8 : 7} />}
        {rows.map((c) => (
          <tr key={c.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
            <Td className="font-medium text-slate-900 dark:text-slate-100">
              <span className={c.parent_odoo_id ? "ps-6 inline-flex items-center gap-1.5" : ""}>
                {c.parent_odoo_id && <span className="text-slate-300 dark:text-slate-600">└</span>}
                {c.name}
                {c.parent_odoo_id && (
                  <Badge tone="indigo">{t("co.branch")}</Badge>
                )}
              </span>
            </Td>
            <Td className="font-mono text-xs">{c.company_registry ?? "—"}</Td>
            <Td className="font-mono text-xs">{c.vat ?? "—"}</Td>
            <Td className="text-xs">
              {c.phone ?? "—"}
              {c.email && <span className="block text-slate-400">{c.email}</span>}
            </Td>
            <Td className="text-xs">
              {c.city ?? "—"}
              {c.country && <span className="block text-slate-400">{c.country}</span>}
            </Td>
            <Td className="tabular-nums">{c.employees}</Td>
            <Td>
              <Badge tone={c.active ? "green" : "slate"}>
                {c.active ? t("hr.state_active") : t("hr.state_archived")}
              </Badge>
            </Td>
            {manager && (
              <Td end>
                <button onClick={() => setEditing(c)} className="text-brand-600 hover:underline text-xs me-3">
                  {t("common.edit")}
                </button>
                {c.active ? (
                  <button onClick={() => setActive(c, false)} className="text-rose-600 hover:underline text-xs">
                    {t("hr.archive")}
                  </button>
                ) : (
                  <button onClick={() => setActive(c, true)} className="text-emerald-600 hover:underline text-xs">
                    {t("hr.restore")}
                  </button>
                )}
              </Td>
            )}
          </tr>
        ))}
      </Table>
    </div>
  );
}

function CompanyForm({
  company,
  parents,
  onDone,
}: {
  company: CompanyEntry | null;
  parents: CompanyEntry[];
  onDone: () => void;
}) {
  const { t } = useI18n();
  const [form, setForm] = useState({
    name: company?.name ?? "",
    parent_id: company?.parent_odoo_id ? String(company.parent_odoo_id) : "",
    company_registry: company?.company_registry ?? "",
    vat: company?.vat ?? "",
    phone: company?.phone ?? "",
    email: company?.email ?? "",
    city: company?.city ?? "",
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    const body = {
      name: form.name,
      parent_id: form.parent_id ? Number(form.parent_id) : undefined,
      company_registry: form.company_registry || undefined,
      vat: form.vat || undefined,
      phone: form.phone || undefined,
      email: form.email || undefined,
      city: form.city || undefined,
    };
    try {
      if (company) {
        await api.put(`/hr/companies/${company.id}`, body);
      } else {
        await api.post("/hr/companies", body);
      }
      onDone();
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
          <Field label={t("common.name")} required>
            <TextInput required value={form.name} onChange={set("name")} />
          </Field>
          <Field label={t("co.parent")}>
            <Select value={form.parent_id} onChange={set("parent_id")}>
              <option value="">{t("co.is_company")}</option>
              {parents
                .filter((p) => p.odoo_id !== company?.odoo_id)
                .map((p) => (
                  <option key={p.odoo_id} value={p.odoo_id}>
                    {t("co.branch_of")} {p.name}
                  </option>
                ))}
            </Select>
          </Field>
          <Field label={t("co.registry")}>
            <TextInput value={form.company_registry} onChange={set("company_registry")} />
          </Field>
          <Field label={t("co.vat")}>
            <TextInput value={form.vat} onChange={set("vat")} />
          </Field>
          <Field label={t("hr.work_phone")}>
            <TextInput value={form.phone} onChange={set("phone")} />
          </Field>
          <Field label={t("auth.email")}>
            <TextInput type="email" value={form.email} onChange={set("email")} />
          </Field>
          <Field label={t("co.city")}>
            <TextInput value={form.city} onChange={set("city")} />
          </Field>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}
