"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useI18n } from "@/lib/i18n";
import { ErrorBox, PageHeader } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextArea, TextInput } from "@/components/form";

export default function NewLeadPage() {
  const { t } = useI18n();
  const router = useRouter();
  const [customers, setCustomers] = useState<{ odoo_id: number; name: string }[]>([]);
  const [form, setForm] = useState({
    name: "",
    contact_name: "",
    partner_id: "",
    email_from: "",
    phone: "",
    expected_revenue: "",
    date_deadline: "",
    description: "",
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: { odoo_id: number; name: string }[] }>("/crm/customers?per_page=100")
      .then((r) => setCustomers(r.data))
      .catch(() => {});
  }, []);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.post("/crm/leads", {
        name: form.name,
        contact_name: form.contact_name || undefined,
        partner_id: form.partner_id ? Number(form.partner_id) : undefined,
        email_from: form.email_from || undefined,
        phone: form.phone || undefined,
        expected_revenue: form.expected_revenue ? Number(form.expected_revenue) : undefined,
        date_deadline: form.date_deadline || undefined,
        description: form.description || undefined,
      });
      router.push("/crm");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.crm")} title={t("crm.new_lead")} />
      {error && <ErrorBox message={error} />}
      <FormCard onSubmit={submit}>
        <Field label={t("crm.title")} required>
          <TextInput required value={form.name} onChange={set("name")} />
        </Field>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label={t("crm.contact")}>
            <TextInput value={form.contact_name} onChange={set("contact_name")} />
          </Field>
          <Field label={t("crm.customer")}>
            <Select value={form.partner_id} onChange={set("partner_id")}>
              <option value="">{t("hr.none")}</option>
              {customers.map((c) => (
                <option key={c.odoo_id} value={c.odoo_id}>
                  {c.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("auth.email")}>
            <TextInput type="email" value={form.email_from} onChange={set("email_from")} />
          </Field>
          <Field label={t("hr.work_phone")}>
            <TextInput value={form.phone} onChange={set("phone")} />
          </Field>
          <Field label={t("crm.expected_revenue")}>
            <TextInput type="number" min="0" step="0.01" value={form.expected_revenue} onChange={set("expected_revenue")} />
          </Field>
          <Field label={t("crm.deadline")}>
            <TextInput type="date" value={form.date_deadline} onChange={set("date_deadline")} />
          </Field>
        </div>
        <Field label={t("leave.reason")}>
          <TextArea rows={3} value={form.description} onChange={set("description")} />
        </Field>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}
