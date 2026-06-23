"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useI18n } from "@/lib/i18n";
import { ErrorBox, PageHeader } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

export default function NewVehiclePage() {
  const { t } = useI18n();
  const router = useRouter();
  const [models, setModels] = useState<{ odoo_id: number; name: string }[]>([]);
  const [categories, setCategories] = useState<{ odoo_id: number; name: string }[]>([]);
  const [drivers, setDrivers] = useState<{ id: number; name: string }[]>([]);
  const [form, setForm] = useState({
    model_id: "",
    category_id: "",
    license_plate: "",
    vin_sn: "",
    model_year: "",
    fuel_type: "",
    fuel_capacity: "",
    employee_id: "",
  });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get<{ data: { odoo_id: number; name: string }[] }>("/fleet/models").then((r) => setModels(r.data)).catch(() => {});
    api.get<{ data: { odoo_id: number; name: string }[] }>("/fleet/categories").then((r) => setCategories(r.data)).catch(() => {});
    api.get<{ data: { id: number; name: string }[] }>("/fleet/drivers").then((r) => setDrivers(r.data)).catch(() => {});
  }, []);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.post("/fleet/vehicles", {
        model_id: Number(form.model_id),
        license_plate: form.license_plate || undefined,
        vin_sn: form.vin_sn || undefined,
        model_year: form.model_year || undefined,
        fuel_type: form.fuel_type || undefined,
        fuel_capacity: form.fuel_capacity ? Number(form.fuel_capacity) : undefined,
        category_id: form.category_id ? Number(form.category_id) : undefined,
        employee_id: form.employee_id ? Number(form.employee_id) : undefined,
      });
      router.push("/fleet");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.fleet")} title={t("fleet.new_vehicle")} />
      {error && <ErrorBox message={error} />}
      <FormCard onSubmit={submit}>
        <Field label={t("fleet.model")} required>
          <Select required value={form.model_id} onChange={set("model_id")}>
            <option value="">—</option>
            {models.map((m) => (
              <option key={m.odoo_id} value={m.odoo_id}>
                {m.name}
              </option>
            ))}
          </Select>
        </Field>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label={t("fleet.category")}>
            <Select value={form.category_id} onChange={set("category_id")}>
              <option value="">{t("hr.none")}</option>
              {categories.map((c) => (
                <option key={c.odoo_id} value={c.odoo_id}>
                  {c.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("fleet.plate")}>
            <TextInput value={form.license_plate} onChange={set("license_plate")} />
          </Field>
          <Field label={t("fleet.vin")}>
            <TextInput value={form.vin_sn} onChange={set("vin_sn")} />
          </Field>
          <Field label={t("fleet.year")}>
            <TextInput value={form.model_year} onChange={set("model_year")} />
          </Field>
          <Field label={t("fleet.fuel")}>
            <Select value={form.fuel_type} onChange={set("fuel_type")}>
              <option value="">—</option>
              {["gasoline", "diesel", "electric", "full_hybrid", "lpg"].map((f) => (
                <option key={f} value={f}>
                  {f}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("fleet.fuel_capacity")}>
            <TextInput type="number" min="0" step="0.01" value={form.fuel_capacity} onChange={set("fuel_capacity")} />
          </Field>
          <Field label={t("fleet.driver")}>
            <Select value={form.employee_id} onChange={set("employee_id")}>
              <option value="">{t("hr.none")}</option>
              {drivers.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.name}
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
