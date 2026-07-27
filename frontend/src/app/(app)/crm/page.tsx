"use client";

// CRM pipeline — kanban with native HTML5 drag & drop onto stage columns.
// Stage moves POST immediately; optimistic update with rollback on error.

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { CrmLead, CrmStage, Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { ErrorBox, money, PageHeader, Spinner, StatCard } from "@/components/ui";

type PipelinePage = Paginated<CrmLead> & {
  stages: CrmStage[];
  stats: { open: number; won: number; lost: number; expected_revenue: number };
};

export default function CrmPipeline() {
  const { t } = useI18n();
  const { can } = useAuth();

  const [data, setData] = useState<PipelinePage | null>(null);
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [dragId, setDragId] = useState<number | null>(null);
  const [overStage, setOverStage] = useState<number | null>(null);

  const writable = can("crm.write");

  const load = useCallback(() => {
    api
      .get<PipelinePage>(`/crm/pipeline${qs({ q, status, per_page: 200 })}`)
      .then(setData)
      .catch(() => setData(null));
  }, [q, status]);

  useEffect(load, [load]);

  async function moveTo(lead: CrmLead, stageId: number) {
    if (!data || lead.stage_id === stageId) return;
    const prev = data;
    // optimistic
    setData({
      ...data,
      data: data.data.map((l) =>
        l.id === lead.id
          ? { ...l, stage_id: stageId, stage_name: data.stages.find((s) => s.odoo_id === stageId)?.name ?? l.stage_name }
          : l
      ),
    });
    try {
      await api.post(`/crm/leads/${lead.id}/stage`, { stage_id: stageId });
    } catch (e) {
      setData(prev); // rollback
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  async function act(lead: CrmLead, action: "won" | "lost" | "restore") {
    setError(null);
    try {
      await api.post(`/crm/leads/${lead.id}/${action}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  if (!data) return <Spinner />;

  const leadsByStage = (stageId: number) => data.data.filter((l) => l.stage_id === stageId);

  return (
    <div>
      <PageHeader kicker={t("nav.crm")} title={t("crm.pipeline")}>
        {writable && (
          <a href="/crm/leads/new" className="h-9 px-4 inline-flex items-center rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">
            + {t("crm.new_lead")}
          </a>
        )}
      </PageHeader>

      {error && <ErrorBox message={error} />}

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <StatCard label={t("crm.open")} value={data.stats.open} tone="brand" />
        <StatCard label={t("crm.won")} value={data.stats.won} tone="green" />
        <StatCard label={t("crm.lost")} value={data.stats.lost} tone="rose" />
        <StatCard label={t("crm.expected_revenue")} value={money(data.stats.expected_revenue)} tone="amber" />
      </div>

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder={t("common.search")}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm flex-1 min-w-40 bg-white dark:bg-slate-900"
        />
        <select
          value={status}
          onChange={(e) => setStatus(e.target.value)}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("crm.open")}</option>
          <option value="won">{t("crm.won")}</option>
          <option value="lost">{t("crm.lost")}</option>
          <option value="all">{t("common.all")}</option>
        </select>
      </div>

      {/* Kanban */}
      <div className="flex gap-3 overflow-x-auto pb-4 items-start">
        {data.stages.map((stage) => {
          const leads = leadsByStage(stage.odoo_id);
          const total = leads.reduce((sum, l) => sum + Number(l.expected_revenue ?? 0), 0);
          return (
            <div
              key={stage.odoo_id}
              onDragOver={(e) => {
                if (!writable) return;
                e.preventDefault();
                setOverStage(stage.odoo_id);
              }}
              onDragLeave={() => setOverStage(null)}
              onDrop={(e) => {
                e.preventDefault();
                setOverStage(null);
                const lead = data.data.find((l) => l.id === dragId);
                if (lead) moveTo(lead, stage.odoo_id);
              }}
              className={`w-72 shrink-0 rounded-xl border ${
                overStage === stage.odoo_id
                  ? "border-brand-400 bg-brand-50/50"
                  : "border-slate-200 bg-slate-100/50"
              }`}
            >
              <div className="px-3 py-2.5 flex items-center justify-between">
                <span className="text-sm font-semibold text-slate-700">
                  {stage.name}
                  {stage.is_won && " 🏆"}
                </span>
                <span className="text-xs text-slate-400 tabular-nums">
                  {leads.length} · {money(total)}
                </span>
              </div>
              <div className="px-2 pb-2 space-y-2 min-h-16">
                {leads.map((lead) => (
                  <div
                    key={lead.id}
                    draggable={writable}
                    onDragStart={() => setDragId(lead.id)}
                    onDragEnd={() => setDragId(null)}
                    className={`bg-white rounded-lg border border-slate-200 p-3 shadow-card ${
                      writable ? "cursor-grab active:cursor-grabbing" : ""
                    } ${dragId === lead.id ? "opacity-50" : ""} ${!lead.active ? "opacity-60" : ""}`}
                  >
                    <Link href={`/crm/leads/${lead.id}`} className="text-sm font-medium text-slate-900 mb-1 block hover:text-brand-600 hover:underline">{lead.name}</Link>
                    <p className="text-xs text-slate-500 mb-2">
                      {lead.partner_name ?? lead.contact_name ?? "—"}
                    </p>
                    <div className="flex items-center justify-between">
                      <span className="text-xs font-semibold text-emerald-700 tabular-nums">
                        {lead.expected_revenue ? money(lead.expected_revenue) : ""}
                      </span>
                      {writable && (
                        <span className="flex gap-2">
                          {lead.active ? (
                            <>
                              {!stage.is_won && (
                                <button
                                  onClick={() => act(lead, "won")}
                                  title={t("crm.mark_won")}
                                  className="text-xs text-emerald-600 hover:underline"
                                >
                                  ✓
                                </button>
                              )}
                              <button
                                onClick={() => act(lead, "lost")}
                                title={t("crm.mark_lost")}
                                className="text-xs text-rose-500 hover:underline"
                              >
                                ✗
                              </button>
                            </>
                          ) : (
                            <button
                              onClick={() => act(lead, "restore")}
                              className="text-xs text-brand-600 hover:underline"
                            >
                              {t("crm.restore")}
                            </button>
                          )}
                        </span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
