"use client";

// Performance appraisals (mj_hr_performance): objective/KPI reviews with a
// draft → self-assessment → manager-review → done flow, courier auto-KPI,
// weighted overall rating, reward-to-payroll, and a recognition wall.

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, PageHeader, Pagination, Spinner, SuccessBox, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput, TextArea } from "@/components/form";

interface Row {
  id: number; name: string | null; employee_name: string | null; odoo_employee_id: number;
  period: string | null; state: string; date_from: string | null; date_to: string | null;
  reviewer_name: string | null; overall_rating: number; has_reward: boolean;
}
interface Line {
  id: number; odoo_id: number; name: string; category: string; skill_name: string | null;
  weight: number; target: string | null; auto_value: number; is_auto: boolean;
  self_rating: string | null; manager_rating: string | null; score: number;
}
interface Detail extends Row { summary_text: string | null; reward_odoo_id: number | null; lines: Line[] }

const STATE_TONE: Record<string, string> = {
  draft: "slate", self_assessment: "amber", manager_review: "brand", done: "green", cancelled: "rose",
};
const RATINGS = ["1", "2", "3", "4", "5"] as const;

function overallTone(v: number): string {
  if (v >= 4) return "green"; if (v >= 3) return "brand"; if (v > 0) return "amber"; return "slate";
}

export default function AppraisalsPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const isHr = can("hr.view_all");
  const canReward = can("payslips.create");

  const [page, setPage] = useState<Paginated<Row> | null>(null);
  const [pageNum, setPageNum] = useState(1);
  const [sel, setSel] = useState<Detail | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const load = useCallback(() => {
    api.get<Paginated<Row>>(`/hr/appraisals${qs({ page: pageNum })}`).then(setPage).catch(() => setPage(null));
  }, [pageNum]);
  useEffect(load, [load]);

  const openDetail = useCallback((id: number) => {
    api.get<{ data: Detail }>(`/hr/appraisals/${id}`).then((r) => setSel(r.data)).catch(() => setSel(null));
  }, []);

  async function act(id: number, action: string) {
    setError(null); setOk(null);
    try {
      const r = await api.post<{ data: Detail }>(`/hr/appraisals/${id}/${action}`, {});
      setSel(r.data); setOk(t("common.saved")); load();
    } catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  return (
    <div>
      <PageHeader kicker={isHr ? t("nav.hr") : t("perf.nav_my")} title={t("perf.title")}>
        {isHr && <button onClick={() => setShowNew((v) => !v)} className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">+ {t("perf.new")}</button>}
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {ok && <SuccessBox message={ok} />}

      {showNew && <NewAppraisal onDone={() => { setShowNew(false); setOk(t("common.saved")); load(); }} onError={setError} />}

      {!page ? <Spinner /> : (
        <>
          <Table head={<><Th>{t("loan.reference")}</Th>{isHr && <Th>{t("hr.employee")}</Th>}<Th>{t("perf.period")}</Th><Th>{t("perf.window")}</Th><Th>{t("perf.overall")}</Th><Th>{t("common.status")}</Th><Th end>{t("common.actions")}</Th></>}>
            {page.data.length === 0 && <EmptyRow colSpan={isHr ? 7 : 6} />}
            {page.data.map((r) => (
              <tr key={r.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 cursor-pointer" onClick={() => openDetail(r.id)}>
                <Td className="font-mono text-xs text-slate-500">{r.name ?? "—"}</Td>
                {isHr && <Td className="font-medium text-slate-900 dark:text-slate-100">{r.employee_name}</Td>}
                <Td>{r.period ?? "—"}</Td>
                <Td className="text-xs tabular-nums text-slate-500">{r.date_from ? `${r.date_from} → ${r.date_to ?? ""}` : "—"}</Td>
                <Td>{r.overall_rating ? <Badge tone={overallTone(r.overall_rating)}>{r.overall_rating.toFixed(1)}</Badge> : <span className="text-slate-400 text-xs">—</span>}</Td>
                <Td><Badge tone={STATE_TONE[r.state] ?? "slate"}>{t(`perf.state_${r.state}`)}</Badge>{r.has_reward && <span className="ms-1 text-emerald-500" title={t("perf.rewarded")}>★</span>}</Td>
                <Td end><button onClick={(e) => { e.stopPropagation(); openDetail(r.id); }} className="text-brand-600 hover:underline text-xs">{t("common.details")}</button></Td>
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}

      {sel && (
        <DetailDrawer
          d={sel} isHr={isHr} canReward={canReward}
          onClose={() => setSel(null)}
          onAct={(a) => act(sel.id, a)}
          onSaved={(d) => { setSel(d); setOk(t("common.saved")); load(); }}
          onError={setError}
        />
      )}

      <RecognitionWall isHr={isHr} onError={setError} onOk={() => setOk(t("common.saved"))} />
    </div>
  );
}

function DetailDrawer({ d, isHr, canReward, onClose, onAct, onSaved, onError }: {
  d: Detail; isHr: boolean; canReward: boolean; onClose: () => void;
  onAct: (a: string) => void; onSaved: (d: Detail) => void; onError: (m: string) => void;
}) {
  const { t } = useI18n();
  const editSelf = !isHr && d.state === "self_assessment";
  const editMgr = isHr && d.state === "manager_review";
  const [ratings, setRatings] = useState<Record<number, string>>({});
  const [summary, setSummary] = useState(d.summary_text ?? "");
  const [busy, setBusy] = useState(false);
  const [rewardAmt, setRewardAmt] = useState("500");

  useEffect(() => {
    const init: Record<number, string> = {};
    d.lines.forEach((l) => { init[l.odoo_id] = (isHr ? l.manager_rating : l.self_rating) ?? ""; });
    setRatings(init); setSummary(d.summary_text ?? "");
  }, [d, isHr]);

  async function saveRatings() {
    setBusy(true); onError("");
    try {
      const body: Record<string, unknown> = {
        lines: d.lines.map((l) => ({ odoo_id: l.odoo_id, rating: ratings[l.odoo_id] || null })),
      };
      if (editMgr) body.summary = summary;
      const r = await api.put<{ data: Detail }>(`/hr/appraisals/${d.id}/lines`, body);
      onSaved(r.data);
    } catch (e) { onError(e instanceof ApiError ? e.message : t("common.error")); }
    finally { setBusy(false); }
  }

  async function grant() {
    setBusy(true); onError("");
    try {
      const r = await api.post<{ data: Detail }>(`/hr/appraisals/${d.id}/reward`, { amount: Number(rewardAmt) });
      onSaved(r.data);
    } catch (e) { onError(e instanceof ApiError ? e.message : t("common.error")); }
    finally { setBusy(false); }
  }

  const canEdit = editSelf || editMgr;

  return (
    <div className="fixed inset-0 z-40 flex justify-end bg-black/30" onClick={onClose}>
      <div className="w-full max-w-3xl h-full overflow-y-auto bg-white dark:bg-slate-900 shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-start justify-between mb-4">
          <div>
            <p className="font-mono text-xs text-slate-400">{d.name}</p>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{d.employee_name}</h2>
            <p className="text-sm text-slate-500">{d.period} · {d.date_from ? `${d.date_from} → ${d.date_to ?? ""}` : "—"} · {d.reviewer_name}</p>
          </div>
          <div className="text-end">
            <Badge tone={STATE_TONE[d.state] ?? "slate"}>{t(`perf.state_${d.state}`)}</Badge>
            {d.overall_rating > 0 && <p className="mt-1 text-2xl font-bold text-slate-900 dark:text-slate-100">{d.overall_rating.toFixed(1)}<span className="text-sm text-slate-400">/5</span></p>}
          </div>
        </div>

        {/* Workflow buttons */}
        <div className="flex flex-wrap gap-2 mb-5">
          {d.state === "draft" && isHr && <Wf onClick={() => onAct("open")} label={t("perf.open")} />}
          {d.state === "self_assessment" && <Wf onClick={() => onAct("submit")} label={t("perf.submit")} />}
          {d.state === "manager_review" && isHr && <Wf onClick={() => onAct("finalize")} label={t("perf.finalize")} />}
          {d.state === "done" && isHr && canReward && !d.reward_odoo_id && (
            <div className="flex items-center gap-2">
              <input type="number" min="1" value={rewardAmt} onChange={(e) => setRewardAmt(e.target.value)}
                className="h-8 w-24 px-2 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900" />
              <button disabled={busy} onClick={grant} className="h-8 px-3 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold disabled:opacity-50">{t("perf.grant_reward")}</button>
            </div>
          )}
          {d.reward_odoo_id && <span className="inline-flex items-center h-8 px-3 rounded-md bg-emerald-50 text-emerald-700 text-xs font-semibold dark:bg-emerald-900/30 dark:text-emerald-300">★ {t("perf.rewarded")}</span>}
          {["draft", "self_assessment", "manager_review"].includes(d.state) && isHr && <button onClick={() => onAct("cancel")} className="h-8 px-3 rounded-md text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 text-xs">{t("common.cancel")}</button>}
        </div>

        {/* Lines grid */}
        <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("perf.objectives")}</h3>
        {d.lines.length === 0 ? <p className="text-sm text-slate-400 mb-4">{t("perf.no_lines")}</p> : (
          <Table head={<><Th>{t("perf.objective")}</Th><Th>{t("perf.category")}</Th><Th>{t("perf.weight")}</Th><Th>{t("perf.target")}</Th><Th>{t("perf.actual")}</Th><Th>{t("perf.self")}</Th><Th>{t("perf.manager")}</Th><Th>{t("perf.score")}</Th></>}>
            {d.lines.map((l) => (
              <tr key={l.odoo_id}>
                <Td className="font-medium">{l.name}{l.is_auto && <span className="ms-1 text-[10px] px-1 rounded bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">{t("perf.auto")}</span>}{l.skill_name && <span className="block text-xs text-slate-400">{l.skill_name}</span>}</Td>
                <Td className="text-xs">{t(`perf.cat_${l.category}`)}</Td>
                <Td className="tabular-nums">{l.weight || "—"}</Td>
                <Td className="text-xs">{l.target ?? "—"}</Td>
                <Td className="tabular-nums text-xs">{l.is_auto ? `${l.auto_value.toFixed(1)}%` : "—"}</Td>
                <Td>{editSelf && !l.is_auto ? <RatingSelect value={ratings[l.odoo_id] ?? ""} onChange={(v) => setRatings((s) => ({ ...s, [l.odoo_id]: v }))} /> : (l.self_rating ?? "—")}</Td>
                <Td>{editMgr ? <RatingSelect value={ratings[l.odoo_id] ?? ""} onChange={(v) => setRatings((s) => ({ ...s, [l.odoo_id]: v }))} /> : (l.manager_rating ?? "—")}</Td>
                <Td className="tabular-nums font-semibold">{l.score ? l.score.toFixed(2) : "—"}</Td>
              </tr>
            ))}
          </Table>
        )}

        {/* Summary */}
        <div className="mt-4">
          <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("perf.summary")}</h3>
          {editMgr ? <TextArea value={summary} onChange={(e) => setSummary(e.target.value)} rows={3} /> : <p className="text-sm text-slate-600 dark:text-slate-300 whitespace-pre-wrap">{d.summary_text || "—"}</p>}
        </div>

        {canEdit && (
          <div className="mt-5 flex justify-end">
            <button disabled={busy} onClick={saveRatings} className="h-9 px-5 rounded-md bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold disabled:opacity-50">{t("perf.save_ratings")}</button>
          </div>
        )}
      </div>
    </div>
  );
}

function Wf({ onClick, label }: { onClick: () => void; label: string }) {
  return <button onClick={onClick} className="h-8 px-4 rounded-md bg-brand-600 hover:bg-brand-700 text-white text-xs font-semibold">{label}</button>;
}

function RatingSelect({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    <select value={value} onChange={(e) => onChange(e.target.value)}
      className="h-8 px-1 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900">
      <option value="">—</option>
      {RATINGS.map((r) => <option key={r} value={r}>{r}</option>)}
    </select>
  );
}

function NewAppraisal({ onDone, onError }: { onDone: () => void; onError: (m: string) => void }) {
  const { t } = useI18n();
  const [form, setForm] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement>) => setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true);
    try {
      await api.post("/hr/appraisals", {
        employee_id: form.employee_id ? Number(form.employee_id) : undefined,
        period: form.period || undefined,
        date_from: form.date_from || undefined,
        date_to: form.date_to || undefined,
      });
      onDone();
    } catch (err) { onError(err instanceof ApiError ? err.message : t("common.error")); setBusy(false); }
  }

  return (
    <div className="mb-5">
      <FormCard onSubmit={submit}>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Field label={t("perf.employee_id")} required><TextInput type="number" value={form.employee_id ?? ""} onChange={set("employee_id")} /></Field>
          <Field label={t("perf.period")}><TextInput value={form.period ?? ""} onChange={set("period")} placeholder="2026-Q3" /></Field>
          <Field label={t("common.from") || "From"}><TextInput type="date" value={form.date_from ?? ""} onChange={set("date_from")} /></Field>
          <Field label={t("common.to") || "To"}><TextInput type="date" value={form.date_to ?? ""} onChange={set("date_to")} /></Field>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}

interface Recog { id: number; employee_name: string | null; from_name: string | null; badge: string; message: string; date: string | null }
const BADGE_TONE: Record<string, string> = { kudos: "brand", star: "amber", team: "green" };

function RecognitionWall({ isHr, onError, onOk }: { isHr: boolean; onError: (m: string) => void; onOk: () => void }) {
  const { t } = useI18n();
  const [rows, setRows] = useState<Recog[] | null>(null);
  const [show, setShow] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({ badge: "kudos" });
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api.get<{ data: Recog[] }>("/hr/recognition").then((r) => setRows(r.data)).catch(() => setRows([]));
  }, []);
  useEffect(load, [load]);

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true);
    try {
      await api.post("/hr/recognition", { employee_id: Number(form.employee_id), badge: form.badge, message: form.message });
      setShow(false); setForm({ badge: "kudos" }); onOk(); load();
    } catch (err) { onError(err instanceof ApiError ? err.message : t("common.error")); }
    finally { setBusy(false); }
  }

  return (
    <div className="mt-8">
      <div className="flex items-center justify-between mb-3">
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider">{t("perf.recognition_wall")}</h2>
        {isHr && <button onClick={() => setShow((v) => !v)} className="h-8 px-3 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-xs font-medium">+ {t("perf.give_kudos")}</button>}
      </div>

      {show && (
        <FormCard onSubmit={submit}>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Field label={t("perf.employee_id")} required><TextInput type="number" value={form.employee_id ?? ""} onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))} /></Field>
            <Field label={t("perf.badge_kudos")}>
              <Select value={form.badge} onChange={(e) => setForm((f) => ({ ...f, badge: e.target.value }))}>
                <option value="kudos">{t("perf.badge_kudos")}</option>
                <option value="star">{t("perf.badge_star")}</option>
                <option value="team">{t("perf.badge_team")}</option>
              </Select>
            </Field>
            <Field label={t("perf.message")} required><TextInput value={form.message ?? ""} onChange={(e) => setForm((f) => ({ ...f, message: e.target.value }))} /></Field>
          </div>
          <SubmitButton busy={busy} />
        </FormCard>
      )}

      {!rows ? <Spinner /> : rows.length === 0 ? <p className="text-sm text-slate-400">—</p> : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          {rows.map((r) => (
            <div key={r.id} className="rounded-lg border border-slate-200 dark:border-slate-800 p-4 bg-white dark:bg-slate-900">
              <div className="flex items-center justify-between mb-1">
                <span className="font-medium text-slate-900 dark:text-slate-100">{r.employee_name}</span>
                <Badge tone={BADGE_TONE[r.badge] ?? "slate"}>{t(`perf.badge_${r.badge}`)}</Badge>
              </div>
              <p className="text-sm text-slate-600 dark:text-slate-300">{r.message}</p>
              <p className="mt-2 text-xs text-slate-400">{r.from_name} · {r.date ? new Date(r.date).toLocaleDateString() : ""}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
