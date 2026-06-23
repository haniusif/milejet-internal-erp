"use client";

// Employee documents section (مرفقات الموظف): categorized files — IDs, licences,
// bonds, warnings, permissions, sick-leave certificates, … Uploads go to Odoo
// (hr.employee.document via mj_hr_documents); files stream back on download.

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, Spinner, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

interface Doc {
  id: number;
  name: string | null;
  category: string;
  filename: string | null;
  mimetype: string | null;
  issue_date: string | null;
  expiry_date: string | null;
  note: string | null;
}

const CATEGORIES = [
  "iqama",
  "passport",
  "license",
  "contract",
  "bond",
  "warning",
  "permission",
  "sick_leave",
  "certificate",
  "other",
] as const;

// Soon-to-expire highlight (≤ 30 days) for ID/licence-style docs.
function expiryTone(date: string | null): string {
  if (!date) return "slate";
  const days = (new Date(date).getTime() - Date.now()) / 86400000;
  if (days < 0) return "rose";
  if (days <= 30) return "amber";
  return "green";
}

export default function EmployeeDocuments({ employeeOdooId }: { employeeOdooId: number }) {
  const { t } = useI18n();
  const { can } = useAuth();
  const manager = can("employees.write");

  const [docs, setDocs] = useState<Doc[] | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    api
      .get<{ data: Doc[] }>(`/hr/documents?employee_id=${employeeOdooId}`)
      .then((r) => setDocs(r.data))
      .catch(() => setDocs([]));
  }, [employeeOdooId]);

  useEffect(load, [load]);

  async function remove(id: number) {
    if (!confirm(t("common.confirm_delete"))) return;
    setError(null);
    try {
      await api.del(`/hr/documents/${id}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-2">
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("doc.title")}</h2>
        {manager && (
          <button
            onClick={() => setShowForm((v) => !v)}
            className="h-8 px-3 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-xs font-medium"
          >
            + {t("doc.add")}
          </button>
        )}
      </div>

      {error && <ErrorBox message={error} />}

      {showForm && manager && (
        <div className="mb-4">
          <DocumentForm
            employeeOdooId={employeeOdooId}
            onDone={() => {
              setShowForm(false);
              load();
            }}
          />
        </div>
      )}

      {!docs ? (
        <Spinner />
      ) : (
        <Table
          head={
            <>
              <Th>{t("doc.category")}</Th>
              <Th>{t("doc.name")}</Th>
              <Th>{t("doc.issue_date")}</Th>
              <Th>{t("doc.expiry_date")}</Th>
              <Th end>{t("common.actions")}</Th>
            </>
          }
        >
          {docs.length === 0 && <EmptyRow colSpan={5} />}
          {docs.map((d) => (
            <tr key={d.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
              <Td>
                <Badge tone="slate">{t(`doc.cat.${d.category}`)}</Badge>
              </Td>
              <Td className="font-medium text-slate-900 dark:text-slate-100">
                {d.name}
                {d.note && <span className="block text-xs text-slate-400">{d.note}</span>}
              </Td>
              <Td className="tabular-nums text-xs">{d.issue_date ?? "—"}</Td>
              <Td className="tabular-nums text-xs">
                {d.expiry_date ? <Badge tone={expiryTone(d.expiry_date)}>{d.expiry_date}</Badge> : "—"}
              </Td>
              <Td end>
                <a
                  href={`/api/v1/hr/documents/${d.id}/download`}
                  target="_blank"
                  rel="noreferrer"
                  className="text-brand-600 hover:underline text-xs me-3"
                >
                  {t("doc.download")}
                </a>
                {manager && (
                  <button onClick={() => remove(d.id)} className="text-rose-600 hover:underline text-xs">
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

function DocumentForm({ employeeOdooId, onDone }: { employeeOdooId: number; onDone: () => void }) {
  const { t } = useI18n();
  const [form, setForm] = useState({
    category: "iqama",
    name: "",
    issue_date: "",
    expiry_date: "",
    note: "",
    file: "",
    filename: "",
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  function onFile(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 15 * 1024 * 1024) {
      setError(t("doc.file_too_large"));
      e.target.value = "";
      return;
    }
    const reader = new FileReader();
    reader.onload = () =>
      setForm((f) => ({ ...f, file: String(reader.result), filename: file.name, name: f.name || file.name }));
    reader.readAsDataURL(file);
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!form.file) {
      setError(t("doc.file_required"));
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await api.post("/hr/documents", {
        employee_id: employeeOdooId,
        category: form.category,
        name: form.name,
        file: form.file,
        filename: form.filename || undefined,
        issue_date: form.issue_date || undefined,
        expiry_date: form.expiry_date || undefined,
        note: form.note || undefined,
      });
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
          <Field label={t("doc.category")} required>
            <Select required value={form.category} onChange={set("category")}>
              {CATEGORIES.map((c) => (
                <option key={c} value={c}>
                  {t(`doc.cat.${c}`)}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("doc.name")} required>
            <TextInput required value={form.name} onChange={set("name")} />
          </Field>
          <Field label={t("doc.issue_date")}>
            <TextInput type="date" value={form.issue_date} onChange={set("issue_date")} />
          </Field>
          <Field label={t("doc.expiry_date")}>
            <TextInput type="date" value={form.expiry_date} onChange={set("expiry_date")} />
          </Field>
          <div className="md:col-span-2">
            <Field label={t("doc.file")} required>
              <input
                type="file"
                accept="image/*,application/pdf"
                onChange={onFile}
                className="block w-full text-sm text-slate-600 dark:text-slate-300 file:me-3 file:h-9 file:px-4 file:rounded-md file:border file:border-slate-200 dark:file:border-slate-700 file:bg-slate-50 dark:file:bg-slate-800 file:text-sm file:font-medium"
              />
            </Field>
            <p className="text-[11px] text-slate-400 mt-1">{t("doc.file_hint")}</p>
          </div>
          <div className="md:col-span-2">
            <Field label={t("loan.note")}>
              <TextInput value={form.note} onChange={set("note")} />
            </Field>
          </div>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </>
  );
}
