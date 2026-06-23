"use client";

// Vehicle inspections with an editable checklist (fleet_vehicle_inspection).
// Create from a template or pick items; set each line pass/fail; confirm once
// every line has a result. Confirmed inspections are read-only.

import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { FleetInspection } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, Table, Td, Th } from "@/components/ui";
import { Field, inputCls, TextInput } from "@/components/form";

interface Props {
  vehicleId: string;
  inspections: FleetInspection[];
  templates: { odoo_id: number; name: string }[];
  items: { odoo_id: number; name: string }[];
  writable: boolean;
  onChange: () => void;
  onError: (m: string) => void;
}

const RESULT_TONE: Record<string, string> = { success: "green", failure: "rose", todo: "amber" };
const STATE_TONE: Record<string, string> = { confirmed: "green", cancel: "rose", draft: "amber" };

export default function VehicleInspections({ vehicleId, inspections, templates, items, writable, onChange, onError }: Props) {
  const { t } = useI18n();
  const [open, setOpen] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);

  async function call(fn: () => Promise<unknown>) {
    try {
      await fn();
      onChange();
    } catch (e) {
      onError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  const setLine = (lineId: number, result: string) =>
    call(() => api.post(`/fleet/inspection-lines/${lineId}`, { result }));
  const action = (id: number, act: string) => call(() => api.post(`/fleet/inspections/${id}/${act}`));

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("fleet.inspections")}</h2>
        {writable && (
          <button
            onClick={() => setShowForm((v) => !v)}
            className="h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium"
          >
            + {t("fleet.add_inspection")}
          </button>
        )}
      </div>

      {showForm && writable && (
        <NewInspection
          vehicleId={vehicleId}
          templates={templates}
          items={items}
          onDone={() => {
            setShowForm(false);
            onChange();
          }}
          onError={onError}
        />
      )}

      <Table
        head={
          <>
            <Th>{t("loan.reference")}</Th>
            <Th>{t("fleet.direction")}</Th>
            <Th>{t("common.date")}</Th>
            <Th>{t("fleet.result")}</Th>
            <Th>{t("common.status")}</Th>
            <Th end>{t("common.actions")}</Th>
          </>
        }
      >
        {inspections.length === 0 && <EmptyRow colSpan={6} />}
        {inspections.map((ins) => (
          <>
            <tr key={ins.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 cursor-pointer" onClick={() => setOpen(open === ins.id ? null : ins.id)}>
              <Td className="font-mono text-xs text-slate-500">{ins.name ?? "—"}</Td>
              <Td>{ins.direction ? t(`fleet.dir_${ins.direction}`) : "—"}</Td>
              <Td className="tabular-nums text-xs">{ins.date_inspected?.slice(0, 16).replace("T", " ") ?? "—"}</Td>
              <Td>{ins.result ? <Badge tone={RESULT_TONE[ins.result] ?? "slate"}>{t(`fleet.res_${ins.result}`)}</Badge> : "—"}</Td>
              <Td><Badge tone={STATE_TONE[ins.state] ?? "slate"}>{ins.state}</Badge></Td>
              <Td end>
                <span className="text-xs text-slate-400">{open === ins.id ? "▲" : "▼"} {ins.lines.length}</span>
              </Td>
            </tr>
            {open === ins.id && (
              <tr className="bg-slate-50/60 dark:bg-slate-800/30">
                <td colSpan={6} className="px-6 py-3">
                  {ins.lines.length === 0 ? (
                    <p className="text-xs text-slate-400">{t("fleet.no_items")}</p>
                  ) : (
                    <div className="space-y-1.5">
                      {ins.lines.map((l) => (
                        <div key={l.id} className="flex items-center justify-between gap-3 text-sm">
                          <span className="text-slate-700 dark:text-slate-200">{l.item_name}</span>
                          {writable && ins.state === "draft" ? (
                            <div className="flex gap-1 shrink-0">
                              {(["success", "failure", "todo"] as const).map((r) => (
                                <button
                                  key={r}
                                  onClick={() => setLine(l.id, r)}
                                  className={`h-7 px-2 rounded text-xs font-medium ${
                                    l.result === r
                                      ? r === "success"
                                        ? "bg-emerald-600 text-white"
                                        : r === "failure"
                                          ? "bg-rose-600 text-white"
                                          : "bg-amber-500 text-white"
                                      : "bg-slate-100 dark:bg-slate-700 text-slate-500"
                                  }`}
                                >
                                  {t(`fleet.res_${r}`)}
                                </button>
                              ))}
                            </div>
                          ) : (
                            <Badge tone={RESULT_TONE[l.result] ?? "slate"}>{t(`fleet.res_${l.result}`)}</Badge>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                  {writable && (
                    <div className="flex gap-3 mt-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                      {ins.state === "draft" && (
                        <button onClick={() => action(ins.id, "confirm")} className="text-emerald-600 hover:underline text-xs">
                          {t("fleet.confirm")}
                        </button>
                      )}
                      {ins.state === "confirmed" && (
                        <button onClick={() => action(ins.id, "draft")} className="text-amber-600 hover:underline text-xs">
                          {t("fleet.reopen")}
                        </button>
                      )}
                      {ins.state !== "cancel" && (
                        <button onClick={() => action(ins.id, "cancel")} className="text-slate-500 hover:underline text-xs">
                          {t("common.cancel")}
                        </button>
                      )}
                      <button onClick={() => confirm(t("common.confirm_delete")) && action(ins.id, "delete")} className="text-rose-600 hover:underline text-xs">
                        {t("common.delete")}
                      </button>
                    </div>
                  )}
                </td>
              </tr>
            )}
          </>
        ))}
      </Table>
    </div>
  );
}

function NewInspection({
  vehicleId,
  templates,
  items,
  onDone,
  onError,
}: {
  vehicleId: string;
  templates: { odoo_id: number; name: string }[];
  items: { odoo_id: number; name: string }[];
  onDone: () => void;
  onError: (m: string) => void;
}) {
  const { t } = useI18n();
  const [direction, setDirection] = useState("out");
  const [templateId, setTemplateId] = useState("");
  const [itemIds, setItemIds] = useState<number[]>([]);
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api.post(`/fleet/vehicles/${vehicleId}/inspections`, {
        direction,
        note: note || undefined,
        template_id: templateId ? Number(templateId) : undefined,
        item_ids: itemIds.length ? itemIds : undefined,
      });
      onDone();
    } catch (err) {
      onError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-3">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
        <Field label={t("fleet.direction")} required>
          <select value={direction} onChange={(e) => setDirection(e.target.value)} className={inputCls}>
            <option value="out">{t("fleet.dir_out")}</option>
            <option value="in">{t("fleet.dir_in")}</option>
          </select>
        </Field>
        <Field label={t("fleet.template")}>
          <select value={templateId} onChange={(e) => setTemplateId(e.target.value)} className={inputCls}>
            <option value="">{t("hr.none")}</option>
            {templates.map((tp) => (
              <option key={tp.odoo_id} value={tp.odoo_id}>{tp.name}</option>
            ))}
          </select>
        </Field>
        <Field label={t("loan.note")}>
          <TextInput value={note} onChange={(e) => setNote(e.target.value)} />
        </Field>
      </div>
      {!templateId && items.length > 0 && (
        <div>
          <p className="text-xs text-slate-400 mb-1">{t("fleet.pick_items")}</p>
          <div className="flex flex-wrap gap-1.5">
            {items.map((it) => {
              const on = itemIds.includes(it.odoo_id);
              return (
                <button
                  key={it.odoo_id}
                  type="button"
                  onClick={() => setItemIds((s) => (on ? s.filter((x) => x !== it.odoo_id) : [...s, it.odoo_id]))}
                  className={`h-7 px-2.5 rounded-full text-xs ${on ? "bg-brand-600 text-white" : "bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300"}`}
                >
                  {it.name}
                </button>
              );
            })}
          </div>
        </div>
      )}
      <button disabled={busy} className="h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-sm font-medium">
        {busy ? "…" : t("common.create")}
      </button>
    </form>
  );
}
