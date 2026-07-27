"use client";

import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { FleetVehicleDetail } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, ErrorBox, money, PageHeader, Spinner, SuccessBox, Table, Td, Th } from "@/components/ui";
import { Field, inputCls, Select, TextInput } from "@/components/form";
import VehicleInspections from "@/components/fleet/VehicleInspections";
import VehicleUsage from "@/components/fleet/VehicleUsage";
import VehicleFuelAccidents from "@/components/fleet/VehicleFuelAccidents";

export default function VehicleDetail() {
  const { t } = useI18n();
  const { can } = useAuth();
  const { id } = useParams<{ id: string }>();

  const [vehicle, setVehicle] = useState<FleetVehicleDetail | null>(null);
  const [states, setStates] = useState<{ odoo_id: number; name: string }[]>([]);
  const [drivers, setDrivers] = useState<{ id: number; name: string }[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [subIds, setSubIds] = useState<number[]>([]);

  const writable = can("fleet.write");

  const load = useCallback(() => {
    api.get<{ data: FleetVehicleDetail }>(`/fleet/vehicles/${id}`).then((r) => setVehicle(r.data))
      .catch((e) => setError(e instanceof ApiError ? e.message : "error"));
  }, [id]);

  useEffect(load, [load]);

  useEffect(() => {
    if (!writable) return;
    api.get<{ states: { odoo_id: number; name: string }[] }>(`/fleet/vehicles?per_page=1`)
      .then((r) => setStates(r.states)).catch(() => {});
    api.get<{ data: { id: number; name: string }[] }>(`/fleet/drivers`)
      .then((r) => setDrivers(r.data)).catch(() => {});
  }, [writable]);

  async function action(path: string, body: unknown, msg: string) {
    setError(null);
    setSuccess(null);
    try {
      await api.post(path, body);
      setSuccess(msg);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  if (!vehicle) return error ? <ErrorBox message={error} /> : <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.fleet")} title={vehicle.name}>
        {vehicle.state_name && <Badge tone="brand">{vehicle.state_name}</Badge>}
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {success && <SuccessBox message={success} />}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div className="space-y-5">
          <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 text-sm space-y-3">
            {(
              [
                [t("fleet.model"), vehicle.model_name],
                [t("fleet.category"), vehicle.category_name],
                [t("fleet.plate"), vehicle.license_plate],
                [t("fleet.vin"), vehicle.vin_sn],
                [t("fleet.year"), vehicle.model_year],
                [t("fleet.fuel"), vehicle.fuel_type],
                [t("fleet.fuel_capacity"), vehicle.fuel_capacity ? `${vehicle.fuel_capacity} L` : null],
                [t("fleet.driver"), vehicle.driver_name],
                [t("fleet.odometer"), `${Number(vehicle.odometer).toLocaleString()} ${vehicle.odometer_unit ?? "km"}`],
                [t("fleet.acquired"), vehicle.acquisition_date],
                [t("fleet.value"), vehicle.car_value ? money(vehicle.car_value) : null],
              ] as [string, string | null][]
            ).map(([label, value]) => (
              <div key={label}>
                <p className="text-xs text-slate-400">{label}</p>
                <p className="text-slate-900 dark:text-slate-100">{value ?? "—"}</p>
              </div>
            ))}
          </div>

          {writable && (
            <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-4">
              <Field label={t("fleet.state")}>
                <Select
                  value={vehicle.state_id ?? ""}
                  onChange={(e) =>
                    e.target.value &&
                    action(`/fleet/vehicles/${id}/state`, { state_id: Number(e.target.value) }, t("common.saved"))
                  }
                >
                  <option value="">—</option>
                  {states.map((s) => (
                    <option key={s.odoo_id} value={s.odoo_id}>
                      {s.name}
                    </option>
                  ))}
                </Select>
              </Field>

              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  const fd = new FormData(e.currentTarget);
                  action(`/fleet/vehicles/${id}/odometer`, { odometer: Number(fd.get("odometer")) }, t("common.saved"));
                }}
                className="space-y-2"
              >
                <Field label={t("fleet.update_odometer")}>
                  <div className="flex gap-2">
                    <TextInput name="odometer" type="number" min={vehicle.odometer} required placeholder={String(vehicle.odometer)} />
                    <button className="h-9 px-3 rounded-md bg-brand-700 text-white text-sm shrink-0">{t("common.save")}</button>
                  </div>
                </Field>
              </form>

              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  const fd = new FormData(e.currentTarget);
                  const val = fd.get("employee_id") as string;
                  action(`/fleet/vehicles/${id}/driver`, { employee_id: val ? Number(val) : null }, t("common.saved"));
                }}
                className="space-y-2"
              >
                <Field label={t("fleet.assign_driver")}>
                  <div className="flex gap-2">
                    <select name="employee_id" className={inputCls}>
                      <option value="">{t("fleet.unassign")}</option>
                      {drivers.map((d) => (
                        <option key={d.id} value={d.id}>
                          {d.name}
                        </option>
                      ))}
                    </select>
                    <button className="h-9 px-3 rounded-md bg-brand-700 text-white text-sm shrink-0">{t("common.save")}</button>
                  </div>
                </Field>
              </form>
            </div>
          )}
        </div>

        <div className="lg:col-span-2 space-y-5">
          {writable && (
            <form
              onSubmit={(e) => {
                e.preventDefault();
                const fd = new FormData(e.currentTarget);
                action(
                  `/fleet/vehicles/${id}/services`,
                  {
                    service_type_id: Number(fd.get("service_type_id")),
                    description: (fd.get("description") as string) || undefined,
                    amount: fd.get("amount") ? Number(fd.get("amount")) : undefined,
                    date: (fd.get("date") as string) || undefined,
                    service_ids: subIds.length ? subIds : undefined,
                  },
                  t("common.created")
                );
                e.currentTarget.reset();
                setSubIds([]);
              }}
              className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-3"
            >
              <div className="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <Field label={t("fleet.service_type")} required>
                  <select name="service_type_id" required className={inputCls}>
                    <option value="">—</option>
                    {vehicle.service_types.map((st) => (
                      <option key={st.odoo_id} value={st.odoo_id}>
                        {st.name}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label={t("leave.reason")}>
                  <TextInput name="description" />
                </Field>
                <Field label={t("common.amount")}>
                  <TextInput name="amount" type="number" min="0" step="0.01" />
                </Field>
                <Field label={t("common.date")}>
                  <TextInput name="date" type="date" />
                </Field>
                <button className="h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">
                  + {t("fleet.add_service")}
                </button>
              </div>
              <div>
                <p className="text-xs text-slate-400 mb-1">{t("fleet.included_services")}</p>
                <div className="flex flex-wrap gap-1.5">
                  {vehicle.service_types.map((st) => {
                    const on = subIds.includes(st.odoo_id);
                    return (
                      <button
                        key={st.odoo_id}
                        type="button"
                        onClick={() => setSubIds((s) => (on ? s.filter((x) => x !== st.odoo_id) : [...s, st.odoo_id]))}
                        className={`h-7 px-2.5 rounded-full text-xs ${on ? "bg-brand-600 text-white" : "bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"}`}
                      >
                        {st.name}
                      </button>
                    );
                  })}
                </div>
              </div>
            </form>
          )}

          <Table
            head={
              <>
                <Th>{t("fleet.service_type")}</Th>
                <Th>{t("common.date")}</Th>
                <Th>{t("common.amount")}</Th>
                <Th>{t("fleet.vendor")}</Th>
                <Th>{t("common.status")}</Th>
              </>
            }
          >
            {vehicle.services.length === 0 && (
              <tr>
                <td colSpan={5} className="px-4 py-8 text-center text-slate-400 text-sm">
                  {t("common.empty")}
                </td>
              </tr>
            )}
            {vehicle.services.map((s) => (
              <tr key={s.id}>
                <Td>
                  {s.service_type ?? "—"}
                  {s.description && <span className="block text-xs text-slate-400">{s.description}</span>}
                  {s.included_services && <span className="block text-xs text-brand-500">+ {s.included_services}</span>}
                </Td>
                <Td className="tabular-nums text-xs">{s.date}</Td>
                <Td className="tabular-nums">{s.amount != null ? money(s.amount) : "—"}</Td>
                <Td>{s.vendor ?? "—"}</Td>
                <Td>{s.state ?? "—"}</Td>
              </tr>
            ))}
          </Table>

          {/* Inspections (checklist) */}
          <VehicleInspections
            vehicleId={id}
            inspections={vehicle.inspections ?? []}
            templates={vehicle.inspection_templates ?? []}
            items={vehicle.inspection_items ?? []}
            writable={writable}
            onChange={load}
            onError={setError}
          />

          {/* Usage / checkout */}
          <VehicleUsage
            vehicleId={id}
            usages={vehicle.usages ?? []}
            drivers={drivers}
            writable={writable}
            onChange={load}
            onError={setError}
          />

          {/* Fuel + accidents (mj_fleet_ops) */}
          <VehicleFuelAccidents
            vehicleId={id}
            vehicleOdooId={vehicle.odoo_id}
            writable={writable}
            onError={setError}
          />
        </div>
      </div>
    </div>
  );
}
