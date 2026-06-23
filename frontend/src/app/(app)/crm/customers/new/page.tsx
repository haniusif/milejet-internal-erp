"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useI18n } from "@/lib/i18n";
import { ErrorBox, PageHeader } from "@/components/ui";
import { Field, FormCard, SubmitButton, TextInput } from "@/components/form";

export default function NewCustomerPage() {
  const { t } = useI18n();
  const router = useRouter();
  const [form, setForm] = useState({ name: "", email: "", phone: "", city: "", vat: "" });
  const [isCompany, setIsCompany] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.post("/crm/customers", {
        name: form.name,
        is_company: isCompany,
        email: form.email || undefined,
        phone: form.phone || undefined,
        city: form.city || undefined,
        vat: form.vat || undefined,
      });
      router.push("/crm/customers");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.crm")} title={t("crm.new_customer")} />
      {error && <ErrorBox message={error} />}
      <FormCard onSubmit={submit}>
        <Field label={t("common.name")} required>
          <TextInput required value={form.name} onChange={set("name")} />
        </Field>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={isCompany} onChange={(e) => setIsCompany(e.target.checked)} />
          {t("crm.is_company")}
        </label>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label={t("auth.email")}>
            <TextInput type="email" value={form.email} onChange={set("email")} />
          </Field>
          <Field label={t("hr.work_phone")}>
            <TextInput value={form.phone} onChange={set("phone")} />
          </Field>
          <Field label={t("crm.city")}>
            <TextInput value={form.city} onChange={set("city")} />
          </Field>
          <Field label="VAT">
            <TextInput value={form.vat} onChange={set("vat")} />
          </Field>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}
