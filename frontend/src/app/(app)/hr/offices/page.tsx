"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { WorkLocationFull } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, PageHeader, Spinner, StatCard, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

export default function OfficesPage() {
  const { t } = useI18n();
  const { can } = useAuth();

  const [locations, setLocations] = useState<WorkLocationFull[] | null>(null);
  const [totals, setTotals] = useState<{ assigned: number; unassigned: number } | null>(null);
  const [editing, setEditing] = useState<WorkLocationFull | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const writable = can("work_locations.write");

  const load = useCallback(() => {
    api
      .get<{ data: WorkLocationFull[]; totals?: { assigned: number; unassigned: number } }>("/hr/work-locations")
      .then((r) => {
        setLocations(r.data);
        setTotals(r.totals ?? null);
      })
      .catch(() => {});
  }, []);

  useEffect(load, [load]);

  async function destroy(w: WorkLocationFull) {
    if (!confirm(t("common.confirm_delete"))) return;
    setError(null);
    try {
      await api.del(`/hr/work-locations/${w.id}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  if (!locations) return <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.offices")}>
        {writable && (
          <button
            onClick={() => {
              setEditing(null);
              setShowForm((v) => !v);
            }}
            className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
          >
            + {t("office.new")}
          </button>
        )}
      </PageHeader>

      {error && <ErrorBox message={error} />}

      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <StatCard label={t("nav.offices")} value={locations.length} tone="brand" hint={t("office.hint_total")} />
        <StatCard
          label={t("office.active")}
          value={locations.filter((w) => w.active).length}
          tone="green"
          hint={t("office.hint_active")}
        />
        {totals && (
          <>
            <StatCard label={t("office.assigned")} value={totals.assigned} hint={t("office.hint_assigned")} />
            <StatCard
              label={t("office.unassigned")}
              value={totals.unassigned}
              tone="amber"
              hint={t("office.hint_unassigned")}
            />
          </>
        )}
      </div>

      {(showForm || editing) && (
        <OfficeForm
          key={editing?.id ?? "new"}
          office={editing}
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
            <Th>{t("office.type")}</Th>
            <Th>{t("office.geofence")}</Th>
            <Th>{t("nav.employees")}</Th>
            <Th>{t("common.status")}</Th>
            {writable && <Th end>{t("common.actions")}</Th>}
          </>
        }
      >
        {locations.length === 0 && <EmptyRow colSpan={writable ? 6 : 5} />}
        {locations.map((w) => (
          <tr key={w.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
            <Td className="font-medium text-slate-900 dark:text-slate-100">
              {w.name}
              {w.latitude != null && w.longitude != null && (
                <span className="block text-xs text-slate-400 tabular-nums">
                  {w.latitude}, {w.longitude}
                </span>
              )}
            </Td>
            <Td>{t(`office.type.${w.location_type}`)}</Td>
            <Td className="tabular-nums">{w.geofence_radius ?? "—"}</Td>
            <Td className="tabular-nums">{w.employees ?? 0}</Td>
            <Td>
              <Badge tone={w.active ? "green" : "slate"}>{w.active ? "✓" : "—"}</Badge>
            </Td>
            {writable && (
              <Td end>
                <button onClick={() => setEditing(w)} className="text-brand-600 hover:underline text-xs me-3">
                  {t("common.edit")}
                </button>
                {can("work_locations.delete") && (
                  <button onClick={() => destroy(w)} className="text-rose-600 hover:underline text-xs">
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

function OfficeForm({ office, onDone }: { office: WorkLocationFull | null; onDone: () => void }) {
  const { t } = useI18n();
  const [form, setForm] = useState({
    name: office?.name ?? "",
    location_type: office?.location_type ?? "office",
    latitude: office?.latitude != null ? String(office.latitude) : "",
    longitude: office?.longitude != null ? String(office.longitude) : "",
    geofence_radius: office?.geofence_radius != null ? String(office.geofence_radius) : "",
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
      location_type: form.location_type,
      latitude: form.latitude ? Number(form.latitude) : undefined,
      longitude: form.longitude ? Number(form.longitude) : undefined,
      geofence_radius: form.geofence_radius ? Number(form.geofence_radius) : undefined,
    };
    try {
      if (office) await api.put(`/hr/work-locations/${office.id}`, body);
      else await api.post("/hr/work-locations", body);
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
          <Field label={t("office.type")} required>
            <Select required value={form.location_type} onChange={set("location_type")}>
              {["office", "home", "other"].map((ty) => (
                <option key={ty} value={ty}>
                  {t(`office.type.${ty}`)}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("office.lat")}>
            <TextInput type="number" step="any" value={form.latitude} onChange={set("latitude")} />
          </Field>
          <Field label={t("office.lng")}>
            <TextInput type="number" step="any" value={form.longitude} onChange={set("longitude")} />
          </Field>
          <Field label={t("office.geofence")}>
            <TextInput type="number" min="10" max="100000" value={form.geofence_radius} onChange={set("geofence_radius")} />
          </Field>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}
