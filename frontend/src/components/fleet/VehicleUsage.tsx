"use client";

// Vehicle usage / checkout log (fleet_vehicle_usage): who has the vehicle,
// from/to dates, and the pick → return workflow.

import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { FleetUsage } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, Table, Td, Th } from "@/components/ui";
import { Field, inputCls, TextInput } from "@/components/form";

interface Props {
  vehicleId: string;
  usages: FleetUsage[];
  drivers: { id: number; name: string }[];
  writable: boolean;
  onChange: () => void;
  onError: (m: string) => void;
}

const STATE_TONE: Record<string, string> = { in_use: "brand", reserved: "amber", returned: "green", cancel: "rose", draft: "slate" };

export default function VehicleUsage({ vehicleId, usages, drivers, writable, onChange, onError }: Props) {
  const { t } = useI18n();
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ employee_id: "", date_picking: "", date_return: "", notes: "" });
  const [busy, setBusy] = useState(false);

  async function call(fn: () => Promise<unknown>) {
    try {
      await fn();
      onChange();
    } catch (e) {
      onError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!form.employee_id) return;
    setBusy(true);
    try {
      await api.post(`/fleet/vehicles/${vehicleId}/usages`, {
        employee_id: Number(form.employee_id),
        date_picking: form.date_picking || undefined,
        date_return: form.date_return || undefined,
        notes: form.notes || undefined,
      });
      setForm({ employee_id: "", date_picking: "", date_return: "", notes: "" });
      setShowForm(false);
      onChange();
    } catch (err) {
      onError(err instanceof ApiError ? err.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("fleet.usage")}</h2>
        {writable && (
          <button
            onClick={() => setShowForm((v) => !v)}
            className="h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium"
          >
            + {t("fleet.add_usage")}
          </button>
        )}
      </div>

      {showForm && writable && (
        <form onSubmit={submit} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
          <Field label={t("fleet.driver")} required>
            <select required value={form.employee_id} onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))} className={inputCls}>
              <option value="">—</option>
              {drivers.map((d) => (
                <option key={d.id} value={d.id}>{d.name}</option>
              ))}
            </select>
          </Field>
          <Field label={t("fleet.from")}>
            <TextInput type="date" value={form.date_picking} onChange={(e) => setForm((f) => ({ ...f, date_picking: e.target.value }))} />
          </Field>
          <Field label={t("fleet.to")}>
            <TextInput type="date" value={form.date_return} onChange={(e) => setForm((f) => ({ ...f, date_return: e.target.value }))} />
          </Field>
          <Field label={t("loan.note")}>
            <TextInput value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} />
          </Field>
          <button disabled={busy} className="h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-sm font-medium">
            {busy ? "…" : t("common.create")}
          </button>
        </form>
      )}

      <Table
        head={
          <>
            <Th>{t("loan.reference")}</Th>
            <Th>{t("fleet.driver")}</Th>
            <Th>{t("fleet.from")}</Th>
            <Th>{t("fleet.to")}</Th>
            <Th>{t("common.status")}</Th>
            {writable && <Th end>{t("common.actions")}</Th>}
          </>
        }
      >
        {usages.length === 0 && <EmptyRow colSpan={writable ? 6 : 5} />}
        {usages.map((u) => (
          <tr key={u.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
            <Td className="font-mono text-xs text-slate-500">{u.name ?? "—"}</Td>
            <Td>{u.partner_name ?? "—"}</Td>
            <Td className="tabular-nums text-xs">{u.date_picking?.slice(0, 10) ?? "—"}</Td>
            <Td className="tabular-nums text-xs">{u.date_return?.slice(0, 10) ?? "—"}</Td>
            <Td><Badge tone={STATE_TONE[u.state] ?? "slate"}>{t(`fleet.usage_${u.state}`)}</Badge></Td>
            {writable && (
              <Td end>
                {(u.state === "draft" || u.state === "reserved") && (
                  <button onClick={() => call(() => api.post(`/fleet/usages/${u.id}/pick`))} className="text-brand-600 hover:underline text-xs me-3">
                    {t("fleet.pick")}
                  </button>
                )}
                {u.state === "in_use" && (
                  <button onClick={() => call(() => api.post(`/fleet/usages/${u.id}/return`))} className="text-emerald-600 hover:underline text-xs me-3">
                    {t("fleet.return")}
                  </button>
                )}
                {u.state !== "returned" && u.state !== "cancel" && (
                  <button onClick={() => call(() => api.post(`/fleet/usages/${u.id}/cancel`))} className="text-rose-600 hover:underline text-xs">
                    {t("common.cancel")}
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
