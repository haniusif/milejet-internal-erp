"use client";

// Fuel logs + accidents for one vehicle (mj_fleet_ops). Fuel entries compute
// cost/L server-side and push an odometer reading; accidents carry a claim workflow.

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, money, Table, Td, Th } from "@/components/ui";
import { Field, inputCls, TextInput } from "@/components/form";

interface FuelRow {
  id: number;
  date: string | null;
  driver: string | null;
  liters: number;
  amount: number;
  price_per_liter: number;
  odometer: number | null;
}
interface AccidentRow {
  id: number;
  name: string | null;
  date: string | null;
  severity: string;
  description: string | null;
  repair_cost: number;
  claim_state: string;
  state: string;
}

const SEV_TONE: Record<string, string> = { minor: "slate", moderate: "amber", major: "rose", total_loss: "rose" };
const CLAIM_TONE: Record<string, string> = { none: "slate", filed: "amber", approved: "brand", rejected: "rose", paid: "green" };

export default function VehicleFuelAccidents({
  vehicleId,
  vehicleOdooId,
  writable,
  onError,
}: {
  vehicleId: string;
  vehicleOdooId: number;
  writable: boolean;
  onError: (m: string) => void;
}) {
  const { t } = useI18n();
  const [fuel, setFuel] = useState<{ data: FuelRow[]; totals: { liters: number; spend: number; avg_price: number } } | null>(null);
  const [accidents, setAccidents] = useState<AccidentRow[] | null>(null);
  const [showFuel, setShowFuel] = useState(false);
  const [showAcc, setShowAcc] = useState(false);

  const load = useCallback(() => {
    api.get<{ data: FuelRow[]; totals: { liters: number; spend: number; avg_price: number } }>(`/fleet/fuel${qs({ vehicle_odoo_id: vehicleOdooId })}`).then(setFuel).catch(() => setFuel(null));
    api.get<{ data: AccidentRow[] }>(`/fleet/accidents${qs({ vehicle_odoo_id: vehicleOdooId })}`).then((r) => setAccidents(r.data)).catch(() => setAccidents([]));
  }, [vehicleOdooId]);
  useEffect(load, [load]);

  async function submit(path: string, body: unknown, reset: () => void) {
    try { await api.post(path, body); reset(); load(); }
    catch (e) { onError(e instanceof ApiError ? e.message : t("common.error")); }
  }
  async function claim(accId: number, action: string) {
    try { await api.post(`/fleet/accidents/${accId}/${action}`); load(); }
    catch (e) { onError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  return (
    <div className="space-y-8">
      {/* FUEL */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("fleet.fuel_logs")}</h2>
          {writable && (
            <button onClick={() => setShowFuel((v) => !v)} className="h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium">+ {t("fleet.add_fuel")}</button>
          )}
        </div>
        {fuel && (
          <div className="flex flex-wrap gap-4 text-xs text-slate-500">
            <span>{t("fleet.total_liters")}: <b className="text-slate-700 dark:text-slate-200">{fuel.totals.liters}</b></span>
            <span>{t("fleet.total_spend")}: <b className="text-slate-700 dark:text-slate-200">{money(fuel.totals.spend)}</b></span>
            <span>{t("fleet.avg_price")}: <b className="text-slate-700 dark:text-slate-200">{money(fuel.totals.avg_price)}</b></span>
          </div>
        )}
        {showFuel && writable && (
          <form
            onSubmit={(e) => { e.preventDefault(); const fd = new FormData(e.currentTarget); const el = e.currentTarget;
              submit(`/fleet/vehicles/${vehicleId}/fuel`, { liters: Number(fd.get("liters")), amount: Number(fd.get("amount")), odometer: fd.get("odometer") ? Number(fd.get("odometer")) : undefined, date: (fd.get("date") as string) || undefined }, () => el.reset()); }}
            className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
            <Field label={t("fleet.liters")} required><TextInput name="liters" type="number" min="0.01" step="0.01" required /></Field>
            <Field label={t("common.amount")} required><TextInput name="amount" type="number" min="0" step="0.01" required /></Field>
            <Field label={t("fleet.odometer")}><TextInput name="odometer" type="number" min="0" /></Field>
            <Field label={t("common.date")}><TextInput name="date" type="date" /></Field>
            <button className="h-9 px-4 rounded-md bg-emerald-600 text-white text-sm font-medium">{t("common.save")}</button>
          </form>
        )}
        <Table head={<><Th>{t("common.date")}</Th><Th>{t("fleet.liters")}</Th><Th>{t("common.amount")}</Th><Th>{t("fleet.price_per_liter")}</Th><Th>{t("fleet.odometer")}</Th></>}>
          {(!fuel || fuel.data.length === 0) && <EmptyRow colSpan={5} />}
          {fuel?.data.map((f) => (
            <tr key={f.id}>
              <Td className="text-xs tabular-nums">{f.date}</Td>
              <Td className="tabular-nums">{f.liters}</Td>
              <Td className="tabular-nums">{money(f.amount)}</Td>
              <Td className="tabular-nums">{money(f.price_per_liter)}</Td>
              <Td className="tabular-nums text-xs">{f.odometer != null ? Number(f.odometer).toLocaleString() : "—"}</Td>
            </tr>
          ))}
        </Table>
      </div>

      {/* ACCIDENTS */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("fleet.accidents")}</h2>
          {writable && (
            <button onClick={() => setShowAcc((v) => !v)} className="h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium">+ {t("fleet.report_accident")}</button>
          )}
        </div>
        {showAcc && writable && (
          <form
            onSubmit={(e) => { e.preventDefault(); const fd = new FormData(e.currentTarget); const el = e.currentTarget;
              submit(`/fleet/vehicles/${vehicleId}/accidents`, { severity: fd.get("severity"), date: (fd.get("date") as string) || undefined, location: (fd.get("location") as string) || undefined, description: (fd.get("description") as string) || undefined, third_party: (fd.get("third_party") as string) || undefined, repair_cost: fd.get("repair_cost") ? Number(fd.get("repair_cost")) : undefined, insurer: (fd.get("insurer") as string) || undefined }, () => el.reset()); }}
            className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 grid grid-cols-2 md:grid-cols-3 gap-3 items-end">
            <Field label={t("fleet.severity")} required>
              <select name="severity" required className={inputCls} defaultValue="minor">
                {["minor", "moderate", "major", "total_loss"].map((s) => <option key={s} value={s}>{t(`fleet.sev_${s}`)}</option>)}
              </select>
            </Field>
            <Field label={t("common.date")}><TextInput name="date" type="date" /></Field>
            <Field label={t("fleet.location")}><TextInput name="location" /></Field>
            <Field label={t("fleet.repair_cost")}><TextInput name="repair_cost" type="number" min="0" step="0.01" /></Field>
            <Field label={t("fleet.third_party")}><TextInput name="third_party" /></Field>
            <Field label={t("fleet.insurer")}><TextInput name="insurer" /></Field>
            <div className="md:col-span-3"><Field label={t("common.details")}><TextInput name="description" /></Field></div>
            <button className="h-9 px-4 rounded-md bg-emerald-600 text-white text-sm font-medium">{t("common.save")}</button>
          </form>
        )}
        <Table head={<><Th>{t("loan.reference")}</Th><Th>{t("common.date")}</Th><Th>{t("fleet.severity")}</Th><Th>{t("fleet.repair_cost")}</Th><Th>{t("fleet.claim")}</Th>{writable && <Th end>{t("common.actions")}</Th>}</>}>
          {(!accidents || accidents.length === 0) && <EmptyRow colSpan={writable ? 6 : 5} />}
          {accidents?.map((a) => (
            <tr key={a.id}>
              <Td className="font-mono text-xs text-slate-500">{a.name}</Td>
              <Td className="text-xs tabular-nums">{a.date?.slice(0, 10)}</Td>
              <Td><Badge tone={SEV_TONE[a.severity] ?? "slate"}>{t(`fleet.sev_${a.severity}`)}</Badge>{a.description && <span className="block text-xs text-slate-400">{a.description}</span>}</Td>
              <Td className="tabular-nums">{money(a.repair_cost)}</Td>
              <Td><Badge tone={CLAIM_TONE[a.claim_state] ?? "slate"}>{t(`fleet.claim_${a.claim_state}`)}</Badge></Td>
              {writable && (
                <Td end>
                  {a.claim_state === "none" && <button onClick={() => claim(a.id, "file")} className="text-brand-600 hover:underline text-xs me-2">{t("fleet.file_claim")}</button>}
                  {a.claim_state === "filed" && <><button onClick={() => claim(a.id, "approve")} className="text-emerald-600 hover:underline text-xs me-2">{t("fleet.approve")}</button><button onClick={() => claim(a.id, "reject")} className="text-rose-600 hover:underline text-xs me-2">{t("fleet.reject")}</button></>}
                  {a.claim_state === "approved" && <button onClick={() => claim(a.id, "paid")} className="text-green-600 hover:underline text-xs">{t("fleet.mark_paid")}</button>}
                </Td>
              )}
            </tr>
          ))}
        </Table>
      </div>
    </div>
  );
}
