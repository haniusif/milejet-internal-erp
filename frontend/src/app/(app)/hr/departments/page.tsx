"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { DepartmentFull } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { EmptyRow, ErrorBox, PageHeader, Spinner, StatCard, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

export default function DepartmentsPage() {
  const { t } = useI18n();
  const { can } = useAuth();

  const [departments, setDepartments] = useState<DepartmentFull[] | null>(null);
  const [managers, setManagers] = useState<{ odoo_id: number; name: string }[]>([]);
  const [editing, setEditing] = useState<DepartmentFull | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [noManagerOnly, setNoManagerOnly] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const writable = can("departments.write");

  const load = useCallback(() => {
    api.get<{ data: DepartmentFull[] }>("/hr/departments/full").then((r) => setDepartments(r.data)).catch(() => {});
  }, []);

  useEffect(load, [load]);

  useEffect(() => {
    if (!writable) return;
    api
      .get<{ data: { odoo_id: number; name: string }[] }>("/hr/employees?per_page=100")
      .then((r) => setManagers(r.data))
      .catch(() => {});
  }, [writable]);

  async function destroy(d: DepartmentFull) {
    if (!confirm(t("common.confirm_delete"))) return;
    setError(null);
    try {
      await api.del(`/hr/departments/${d.id}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  if (!departments) return <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.departments")}>
        {writable && (
          <button
            onClick={() => {
              setEditing(null);
              setShowForm((v) => !v);
            }}
            className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
          >
            + {t("dept.new")}
          </button>
        )}
      </PageHeader>

      {error && <ErrorBox message={error} />}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <button
          type="button"
          onClick={() => setNoManagerOnly(false)}
          className={`text-start rounded-xl transition ${
            !noManagerOnly ? "ring-2 ring-brand-500" : "hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700"
          }`}
        >
          <StatCard label={t("nav.departments")} value={departments.length} tone="brand" hint={t("dept.hint_total")} />
        </button>
        <StatCard
          label={t("dept.with_manager")}
          value={departments.filter((d) => d.manager_name).length}
          tone="green"
          hint={t("dept.hint_with_manager")}
        />
        <button
          type="button"
          onClick={() => setNoManagerOnly(true)}
          className={`text-start rounded-xl transition ${
            noManagerOnly ? "ring-2 ring-brand-500" : "hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700"
          }`}
        >
          <StatCard
            label={t("dept.no_manager")}
            value={departments.filter((d) => !d.manager_name).length}
            tone="amber"
            hint={t("dept.hint_no_manager")}
          />
        </button>
        <StatCard
          label={t("nav.employees")}
          value={departments.reduce((s, d) => s + (d.total_employee ?? 0), 0)}
          hint={t("dept.hint_employees")}
        />
      </div>

      {(showForm || editing) && (
        <DeptForm
          key={editing?.id ?? "new"}
          dept={editing}
          managers={managers}
          parents={departments}
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
            <Th>{t("hr.parent_dept")}</Th>
            <Th>{t("hr.manager")}</Th>
            <Th>{t("nav.employees")}</Th>
            {writable && <Th end>{t("common.actions")}</Th>}
          </>
        }
      >
        {departments.filter((d) => !noManagerOnly || !d.manager_name).length === 0 && (
          <EmptyRow colSpan={writable ? 5 : 4} />
        )}
        {departments.filter((d) => !noManagerOnly || !d.manager_name).map((d) => (
          <tr key={d.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
            <Td className="font-medium text-slate-900 dark:text-slate-100">{d.name}</Td>
            <Td>{d.parent_name ?? "—"}</Td>
            <Td>{d.manager_name ?? "—"}</Td>
            <Td className="tabular-nums">{d.total_employee ?? "—"}</Td>
            {writable && (
              <Td end>
                <button onClick={() => setEditing(d)} className="text-brand-600 hover:underline text-xs me-3">
                  {t("common.edit")}
                </button>
                {can("departments.delete") && (
                  <button onClick={() => destroy(d)} className="text-rose-600 hover:underline text-xs">
                    {t("common.delete")}
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

function DeptForm({
  dept,
  managers,
  parents,
  onDone,
}: {
  dept: DepartmentFull | null;
  managers: { odoo_id: number; name: string }[];
  parents: DepartmentFull[];
  onDone: () => void;
}) {
  const { t } = useI18n();
  const [name, setName] = useState(dept?.name ?? "");
  const [managerId, setManagerId] = useState(dept?.manager_odoo_id ? String(dept.manager_odoo_id) : "");
  const [parentId, setParentId] = useState(dept?.parent_odoo_id ? String(dept.parent_odoo_id) : "");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    const body = {
      name,
      manager_id: managerId ? Number(managerId) : undefined,
      parent_id: parentId ? Number(parentId) : undefined,
    };
    try {
      if (dept) await api.put(`/hr/departments/${dept.id}`, body);
      else await api.post("/hr/departments", body);
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
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Field label={t("common.name")} required>
            <TextInput required value={name} onChange={(e) => setName(e.target.value)} />
          </Field>
          <Field label={t("hr.manager")}>
            <Select value={managerId} onChange={(e) => setManagerId(e.target.value)}>
              <option value="">{t("hr.no_manager")}</option>
              {managers.map((m) => (
                <option key={m.odoo_id} value={m.odoo_id}>
                  {m.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("hr.parent_dept")}>
            <Select value={parentId} onChange={(e) => setParentId(e.target.value)}>
              <option value="">{t("hr.none")}</option>
              {parents
                .filter((p) => p.odoo_id !== dept?.odoo_id)
                .map((p) => (
                  <option key={p.odoo_id} value={p.odoo_id}>
                    {p.name}
                  </option>
                ))}
            </Select>
          </Field>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}
