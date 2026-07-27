"use client";

// Training & development (mj_hr_training): course catalog, scheduled sessions with
// enrollment/attendance, completion → skill + certificate, recert tracking, and
// training needs. Employees see "My training" + available sessions to enroll;
// HR (hr.view_all) manages courses, sessions (attendance grid) and needs.

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, PageHeader, Spinner, SuccessBox, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput, TextArea } from "@/components/form";

interface Course { id: number; odoo_id: number; name: string; code: string | null; category: string; description: string | null; duration_hours: number; is_mandatory: boolean; validity_months: number; pass_mark: number; skill_names: string | null; session_count: number }
interface Session { id: number; odoo_id: number; name: string | null; odoo_course_id: number; course_name: string | null; trainer_name: string | null; mode: string; location: string | null; date_start: string | null; date_end: string | null; capacity: number; seats_taken: number; seats_left: number; state: string }
interface Enrollment { id: number; odoo_id: number; session_name: string | null; odoo_session_id: number; course_name: string | null; odoo_employee_id: number; employee_name: string | null; state: string; score: number | null; completion_date: string | null; expiry_date: string | null; has_certificate: boolean; feedback_rating: string | null }
interface Need { id: number; odoo_id: number; employee_name: string | null; odoo_employee_id: number; skill_name: string | null; source: string; state: string; note: string | null }

const SSTATE_TONE: Record<string, string> = { draft: "slate", confirmed: "brand", in_progress: "amber", done: "green", cancelled: "rose" };
const ESTATE_TONE: Record<string, string> = { enrolled: "brand", attended: "amber", completed: "green", failed: "rose", no_show: "rose", cancelled: "slate" };
const CATEGORIES = ["onboarding", "safety", "compliance", "skills", "soft"] as const;

export default function TrainingPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const isHr = can("hr.view_all");
  const [tab, setTab] = useState<"mine" | "sessions" | "courses" | "needs">("mine");
  const [error, setError] = useState<string | null>(null);
  const [ok, setOk] = useState<string | null>(null);

  const notify = { setError, setOk: (m: string) => setOk(m) };

  return (
    <div>
      <PageHeader kicker={isHr ? t("nav.hr") : t("tr.nav_my")} title={t("tr.title")} />
      {error && <ErrorBox message={error} />}
      {ok && <SuccessBox message={ok} />}

      {isHr && (
        <div className="flex gap-1 border-b border-slate-200 dark:border-slate-800 mb-4 overflow-x-auto">
          {(["mine", "sessions", "courses", "needs"] as const).map((k) => (
            <button key={k} onClick={() => setTab(k)}
              className={`px-4 h-10 text-sm font-medium border-b-2 -mb-px whitespace-nowrap ${tab === k ? "border-brand-600 text-brand-700 dark:text-brand-300" : "border-transparent text-slate-500"}`}>
              {t(`tr.tab_${k}`)}
            </button>
          ))}
        </div>
      )}

      {(!isHr || tab === "mine") && <MyTraining {...notify} />}
      {isHr && tab === "sessions" && <SessionsAdmin {...notify} />}
      {isHr && tab === "courses" && <CoursesAdmin {...notify} />}
      {isHr && tab === "needs" && <NeedsAdmin {...notify} />}
    </div>
  );
}

type Notify = { setError: (m: string | null) => void; setOk: (m: string) => void };

function useCourses() {
  const [courses, setCourses] = useState<Course[]>([]);
  const load = useCallback(() => { api.get<{ data: Course[] }>("/hr/training/courses").then((r) => setCourses(r.data)).catch(() => setCourses([])); }, []);
  useEffect(load, [load]);
  return { courses, reload: load };
}

// --- Employee + shared: my training + available sessions ---
function MyTraining({ setError, setOk }: Notify) {
  const { t } = useI18n();
  const { can } = useAuth();
  const isHr = can("hr.view_all");
  const [mine, setMine] = useState<{ enrollments: Enrollment[]; expiring: Enrollment[] } | null>(null);
  const [sessions, setSessions] = useState<Session[] | null>(null);

  const load = useCallback(() => {
    api.get<{ data: { enrollments: Enrollment[]; expiring: Enrollment[] } }>("/hr/training/mine").then((r) => setMine(r.data)).catch(() => setMine({ enrollments: [], expiring: [] }));
    api.get<{ data: Session[] }>(`/hr/training/sessions${qs({ open: 1 })}`).then((r) => setSessions(r.data.filter((s) => ["confirmed", "in_progress"].includes(s.state)))).catch(() => setSessions([]));
  }, []);
  useEffect(load, [load]);

  async function enroll(s: Session) {
    setError(null);
    try { await api.post("/hr/training/enroll", { session_id: s.odoo_id }); setOk(t("common.saved")); load(); }
    catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  if (!mine) return <Spinner />;
  const enrolledSessionIds = new Set(mine.enrollments.map((e) => e.odoo_session_id));

  return (
    <div className="space-y-6">
      {mine.expiring.length > 0 && (
        <div className="rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800 p-3">
          <p className="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-1">⚠ {t("tr.expiring")}</p>
          <ul className="text-sm text-amber-700 dark:text-amber-300 list-disc ms-5">
            {mine.expiring.map((e) => <li key={e.id}>{e.course_name} — {t("tr.expires")} {e.expiry_date}</li>)}
          </ul>
        </div>
      )}

      <div>
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("tr.tab_mine")}</h2>
        <Table head={<><Th>{t("tr.course")}</Th><Th>{t("tr.session")}</Th><Th>{t("common.status")}</Th><Th>{t("tr.score")}</Th><Th>{t("tr.completed_on")}</Th><Th>{t("tr.expires")}</Th><Th end>{t("tr.certificate")}</Th></>}>
          {mine.enrollments.length === 0 && <EmptyRow colSpan={7} />}
          {mine.enrollments.map((e) => (
            <tr key={e.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
              <Td className="font-medium">{e.course_name}</Td>
              <Td className="font-mono text-xs text-slate-500">{e.session_name}</Td>
              <Td><Badge tone={ESTATE_TONE[e.state] ?? "slate"}>{t(`tr.estate_${e.state}`)}</Badge></Td>
              <Td className="tabular-nums">{e.score ?? "—"}</Td>
              <Td className="text-xs tabular-nums">{e.completion_date ?? "—"}</Td>
              <Td className="text-xs tabular-nums">{e.expiry_date ?? "—"}</Td>
              <Td end>{e.has_certificate ? <a href={`/api/v1/hr/training/enrollments/${e.id}/certificate`} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline text-xs">{t("tr.certificate")}</a> : "—"}</Td>
            </tr>
          ))}
        </Table>
      </div>

      <div>
        <h2 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("tr.available")}</h2>
        {!sessions ? <Spinner /> : sessions.length === 0 ? <p className="text-sm text-slate-400">{t("tr.no_sessions")}</p> : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {sessions.map((s) => {
              const full = s.capacity > 0 && s.seats_left <= 0;
              const already = enrolledSessionIds.has(s.odoo_id);
              return (
                <div key={s.id} className="rounded-lg border border-slate-200 dark:border-slate-800 p-4 bg-white dark:bg-slate-900">
                  <div className="flex items-center justify-between mb-1">
                    <span className="font-medium text-slate-900 dark:text-slate-100">{s.course_name}</span>
                    <Badge tone={SSTATE_TONE[s.state] ?? "slate"}>{t(`tr.sstate_${s.state}`)}</Badge>
                  </div>
                  <p className="text-xs text-slate-500">{s.name} · {t(`tr.mode_${s.mode}`)}{s.location ? ` · ${s.location}` : ""}</p>
                  <p className="text-xs text-slate-500 mt-1">{s.date_start ? new Date(s.date_start).toLocaleString() : "—"}{s.capacity > 0 && <span> · {t("tr.seats")}: {s.seats_taken}/{s.capacity}</span>}</p>
                  <button disabled={already || full} onClick={() => enroll(s)}
                    className="mt-3 h-8 px-3 rounded-md text-xs font-semibold bg-brand-600 text-white hover:bg-brand-700 disabled:opacity-40">
                    {already ? t("tr.estate_enrolled") : full ? t("tr.seats") : t("tr.enroll")}
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}

// --- HR: sessions admin with attendance grid ---
function SessionsAdmin({ setError, setOk }: Notify) {
  const { t } = useI18n();
  const { courses } = useCourses();
  const [sessions, setSessions] = useState<Session[] | null>(null);
  const [sel, setSel] = useState<(Session & { enrollments: Enrollment[] }) | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({ mode: "in_person" });

  const load = useCallback(() => { api.get<{ data: Session[] }>("/hr/training/sessions").then((r) => setSessions(r.data)).catch(() => setSessions([])); }, []);
  useEffect(load, [load]);

  const openSession = (id: number) => api.get<{ data: Session & { enrollments: Enrollment[] } }>(`/hr/training/sessions/${id}`).then((r) => setSel(r.data)).catch(() => setSel(null));

  async function schedule(e: React.FormEvent) {
    e.preventDefault(); setError(null);
    try {
      await api.post("/hr/training/sessions", {
        course_id: Number(form.course_id), mode: form.mode, location: form.location || undefined,
        trainer_external: form.trainer_external || undefined, date_start: form.date_start || undefined,
        date_end: form.date_end || undefined, capacity: form.capacity ? Number(form.capacity) : undefined,
      });
      setShowForm(false); setForm({ mode: "in_person" }); setOk(t("common.saved")); load();
    } catch (err) { setError(err instanceof ApiError ? err.message : t("common.error")); }
  }

  async function sAct(s: Session, action: string) {
    setError(null);
    try { await api.post(`/hr/training/sessions/${s.id}/${action}`, {}); setOk(t("common.saved")); load(); if (sel?.id === s.id) openSession(s.id); }
    catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  if (!sessions) return <Spinner />;
  return (
    <div>
      <div className="flex justify-end mb-3">
        <button onClick={() => setShowForm((v) => !v)} className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">+ {t("tr.new_session")}</button>
      </div>
      {showForm && (
        <FormCard onSubmit={schedule}>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Field label={t("tr.course")} required>
              <Select value={form.course_id ?? ""} onChange={(e) => setForm((f) => ({ ...f, course_id: e.target.value }))}>
                <option value="">—</option>
                {courses.map((c) => <option key={c.odoo_id} value={c.odoo_id}>{c.code ? `${c.code} · ` : ""}{c.name}</option>)}
              </Select>
            </Field>
            <Field label={t("tr.mode")}>
              <Select value={form.mode} onChange={(e) => setForm((f) => ({ ...f, mode: e.target.value }))}>
                <option value="in_person">{t("tr.mode_in_person")}</option>
                <option value="online">{t("tr.mode_online")}</option>
                <option value="self">{t("tr.mode_self")}</option>
              </Select>
            </Field>
            <Field label={t("tr.trainer")}><TextInput value={form.trainer_external ?? ""} onChange={(e) => setForm((f) => ({ ...f, trainer_external: e.target.value }))} /></Field>
            <Field label={t("tr.location")}><TextInput value={form.location ?? ""} onChange={(e) => setForm((f) => ({ ...f, location: e.target.value }))} /></Field>
            <Field label={t("tr.date_start")}><TextInput type="datetime-local" value={form.date_start ?? ""} onChange={(e) => setForm((f) => ({ ...f, date_start: e.target.value }))} /></Field>
            <Field label={t("tr.capacity")}><TextInput type="number" min="0" value={form.capacity ?? ""} onChange={(e) => setForm((f) => ({ ...f, capacity: e.target.value }))} /></Field>
          </div>
          <SubmitButton busy={false} />
        </FormCard>
      )}

      <Table head={<><Th>{t("loan.reference")}</Th><Th>{t("tr.course")}</Th><Th>{t("tr.date_start")}</Th><Th>{t("tr.seats")}</Th><Th>{t("common.status")}</Th><Th end>{t("common.actions")}</Th></>}>
        {sessions.length === 0 && <EmptyRow colSpan={6} />}
        {sessions.map((s) => (
          <tr key={s.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 cursor-pointer" onClick={() => openSession(s.id)}>
            <Td className="font-mono text-xs text-slate-500">{s.name}</Td>
            <Td className="font-medium">{s.course_name}</Td>
            <Td className="text-xs tabular-nums">{s.date_start ? new Date(s.date_start).toLocaleString() : "—"}</Td>
            <Td className="tabular-nums text-xs">{s.capacity ? `${s.seats_taken}/${s.capacity}` : s.seats_taken}</Td>
            <Td><Badge tone={SSTATE_TONE[s.state] ?? "slate"}>{t(`tr.sstate_${s.state}`)}</Badge></Td>
            <Td end>
              <span onClick={(e) => e.stopPropagation()}>
                {s.state === "draft" && <button onClick={() => sAct(s, "confirm")} className="text-brand-600 hover:underline text-xs me-2">{t("tr.confirm")}</button>}
                {s.state === "confirmed" && <button onClick={() => sAct(s, "start")} className="text-amber-600 hover:underline text-xs me-2">{t("tr.start")}</button>}
                {["confirmed", "in_progress"].includes(s.state) && <button onClick={() => sAct(s, "close")} className="text-emerald-600 hover:underline text-xs me-2">{t("tr.close")}</button>}
                {!["done", "cancelled"].includes(s.state) && <button onClick={() => sAct(s, "cancel")} className="text-slate-500 hover:underline text-xs">{t("common.cancel")}</button>}
              </span>
            </Td>
          </tr>
        ))}
      </Table>

      {sel && <AttendanceDrawer s={sel} onClose={() => setSel(null)} onChanged={() => { openSession(sel.id); load(); }} setError={setError} setOk={setOk} />}
    </div>
  );
}

function AttendanceDrawer({ s, onClose, onChanged, setError, setOk }: { s: Session & { enrollments: Enrollment[] }; onClose: () => void; onChanged: () => void; setError: (m: string | null) => void; setOk: (m: string) => void }) {
  const { t } = useI18n();
  const [scores, setScores] = useState<Record<number, string>>({});

  async function eAct(e: Enrollment, action: string) {
    setError(null);
    const body: Record<string, unknown> = {};
    if (action === "complete" && scores[e.id] !== undefined) body.score = Number(scores[e.id]);
    try { await api.post(`/hr/training/enrollments/${e.id}/${action}`, body); setOk(t("common.saved")); onChanged(); }
    catch (err) { setError(err instanceof ApiError ? err.message : t("common.error")); }
  }

  return (
    <div className="fixed inset-0 z-40 flex justify-end bg-black/30" onClick={onClose}>
      <div className="w-full max-w-3xl h-full overflow-y-auto bg-white dark:bg-slate-900 shadow-2xl p-6" onClick={(e) => e.stopPropagation()}>
        <div className="flex items-start justify-between mb-4">
          <div>
            <p className="font-mono text-xs text-slate-400">{s.name}</p>
            <h2 className="text-lg font-semibold">{s.course_name}</h2>
            <p className="text-sm text-slate-500">{s.date_start ? new Date(s.date_start).toLocaleString() : "—"} · {t(`tr.mode_${s.mode}`)}</p>
          </div>
          <Badge tone={SSTATE_TONE[s.state] ?? "slate"}>{t(`tr.sstate_${s.state}`)}</Badge>
        </div>
        <h3 className="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-2">{t("tr.attendance")}</h3>
        <Table head={<><Th>{t("hr.employee")}</Th><Th>{t("common.status")}</Th><Th>{t("tr.score")}</Th><Th end>{t("common.actions")}</Th></>}>
          {s.enrollments.length === 0 && <EmptyRow colSpan={4} />}
          {s.enrollments.map((e) => (
            <tr key={e.id}>
              <Td className="font-medium">{e.employee_name}</Td>
              <Td><Badge tone={ESTATE_TONE[e.state] ?? "slate"}>{t(`tr.estate_${e.state}`)}</Badge></Td>
              <Td>
                {["enrolled", "attended"].includes(e.state)
                  ? <input type="number" min="0" max="100" value={scores[e.id] ?? (e.score ?? "")} onChange={(ev) => setScores((sc) => ({ ...sc, [e.id]: ev.target.value }))} className="h-8 w-16 px-2 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900" />
                  : (e.score ?? "—")}
              </Td>
              <Td end>
                {e.state === "enrolled" && <button onClick={() => eAct(e, "attend")} className="text-brand-600 hover:underline text-xs me-2">{t("tr.attend")}</button>}
                {["enrolled", "attended"].includes(e.state) && <button onClick={() => eAct(e, "complete")} className="text-emerald-600 hover:underline text-xs me-2">{t("tr.complete")}</button>}
                {["enrolled", "attended"].includes(e.state) && <button onClick={() => eAct(e, "no_show")} className="text-rose-600 hover:underline text-xs">{t("tr.no_show")}</button>}
                {e.has_certificate && <a href={`/api/v1/hr/training/enrollments/${e.id}/certificate`} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline text-xs ms-2">{t("tr.certificate")}</a>}
              </Td>
            </tr>
          ))}
        </Table>
      </div>
    </div>
  );
}

// --- HR: courses admin ---
function CoursesAdmin({ setError, setOk }: Notify) {
  const { t } = useI18n();
  const { courses, reload } = useCourses();
  const [skills, setSkills] = useState<{ id: number; name: string; skill_type: string | null }[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState<Record<string, string>>({ category: "skills" });
  const [skillIds, setSkillIds] = useState<number[]>([]);

  useEffect(() => { api.get<{ data: typeof skills }>("/hr/training/skills").then((r) => setSkills(r.data)).catch(() => setSkills([])); }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault(); setError(null);
    try {
      await api.post("/hr/training/courses", {
        name: form.name, code: form.code || undefined, category: form.category,
        description: form.description || undefined,
        duration_hours: form.duration_hours ? Number(form.duration_hours) : undefined,
        is_mandatory: form.is_mandatory === "1",
        validity_months: form.validity_months ? Number(form.validity_months) : undefined,
        pass_mark: form.pass_mark ? Number(form.pass_mark) : undefined,
        skill_ids: skillIds.length ? skillIds : undefined,
      });
      setShowForm(false); setForm({ category: "skills" }); setSkillIds([]); setOk(t("common.saved")); reload();
    } catch (err) { setError(err instanceof ApiError ? err.message : t("common.error")); }
  }

  return (
    <div>
      <div className="flex justify-end mb-3">
        <button onClick={() => setShowForm((v) => !v)} className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">+ {t("tr.new_course")}</button>
      </div>
      {showForm && (
        <FormCard onSubmit={create}>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Field label={t("tr.course")} required><TextInput value={form.name ?? ""} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} /></Field>
            <Field label={t("tr.code")}><TextInput value={form.code ?? ""} onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} /></Field>
            <Field label={t("tr.category")}>
              <Select value={form.category} onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}>
                {CATEGORIES.map((c) => <option key={c} value={c}>{t(`tr.cat_${c}`)}</option>)}
              </Select>
            </Field>
            <Field label={t("tr.duration")}><TextInput type="number" min="0" step="0.5" value={form.duration_hours ?? ""} onChange={(e) => setForm((f) => ({ ...f, duration_hours: e.target.value }))} /></Field>
            <Field label={t("tr.validity")}><TextInput type="number" min="0" value={form.validity_months ?? ""} onChange={(e) => setForm((f) => ({ ...f, validity_months: e.target.value }))} /></Field>
            <Field label={t("tr.pass_mark")}><TextInput type="number" min="0" max="100" value={form.pass_mark ?? ""} onChange={(e) => setForm((f) => ({ ...f, pass_mark: e.target.value }))} /></Field>
            <Field label={t("tr.mandatory")}>
              <Select value={form.is_mandatory ?? "0"} onChange={(e) => setForm((f) => ({ ...f, is_mandatory: e.target.value }))}>
                <option value="0">—</option><option value="1">{t("tr.mandatory")}</option>
              </Select>
            </Field>
            <div className="md:col-span-2">
              <Field label={t("tr.skills")}>
                <select multiple value={skillIds.map(String)} onChange={(e) => setSkillIds(Array.from(e.target.selectedOptions).map((o) => Number(o.value)))}
                  className="w-full min-h-20 px-2 py-1 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900">
                  {skills.map((s) => <option key={s.id} value={s.id}>{s.name}{s.skill_type ? ` (${s.skill_type})` : ""}</option>)}
                </select>
              </Field>
            </div>
            <div className="md:col-span-3"><Field label={t("tr.description")}><TextArea value={form.description ?? ""} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} rows={2} /></Field></div>
          </div>
          <SubmitButton busy={false} />
        </FormCard>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        {courses.length === 0 && <p className="text-sm text-slate-400">{t("tr.no_training")}</p>}
        {courses.map((c) => (
          <div key={c.id} className="rounded-lg border border-slate-200 dark:border-slate-800 p-4 bg-white dark:bg-slate-900">
            <div className="flex items-center justify-between mb-1">
              <span className="font-medium text-slate-900 dark:text-slate-100">{c.code ? `${c.code} · ` : ""}{c.name}</span>
              {c.is_mandatory && <Badge tone="rose">{t("tr.mandatory")}</Badge>}
            </div>
            <p className="text-xs text-slate-500">{t(`tr.cat_${c.category}`)} · {c.duration_hours}h{c.validity_months ? ` · ${t("tr.validity")}: ${c.validity_months}` : ""}{c.pass_mark ? ` · ${t("tr.pass_mark")}: ${c.pass_mark}` : ""}</p>
            {c.skill_names && <p className="mt-1 text-xs text-sky-600 dark:text-sky-400">{t("tr.skills")}: {c.skill_names}</p>}
            <p className="mt-2 text-xs text-slate-400">{c.session_count} {t("tr.tab_sessions").toLowerCase()}</p>
          </div>
        ))}
      </div>
    </div>
  );
}

// --- HR: needs admin ---
function NeedsAdmin({ setError, setOk }: Notify) {
  const { t } = useI18n();
  const [needs, setNeeds] = useState<Need[] | null>(null);
  const load = useCallback(() => { api.get<{ data: Need[] }>("/hr/training/needs").then((r) => setNeeds(r.data)).catch(() => setNeeds([])); }, []);
  useEffect(load, [load]);

  async function nAct(n: Need, action: string) {
    setError(null);
    try { await api.post(`/hr/training/needs/${n.id}/${action}`, {}); setOk(t("common.saved")); load(); }
    catch (e) { setError(e instanceof ApiError ? e.message : t("common.error")); }
  }

  if (!needs) return <Spinner />;
  return (
    <Table head={<><Th>{t("hr.employee")}</Th><Th>{t("tr.skill")}</Th><Th>{t("tr.source")}</Th><Th>{t("common.status")}</Th><Th end>{t("common.actions")}</Th></>}>
      {needs.length === 0 && <EmptyRow colSpan={5} />}
      {needs.map((n) => (
        <tr key={n.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
          <Td className="font-medium">{n.employee_name}</Td>
          <Td>{n.skill_name}</Td>
          <Td className="text-xs">{t(`tr.src_${n.source}`)}</Td>
          <Td><Badge tone={n.state === "closed" ? "slate" : n.state === "planned" ? "brand" : "amber"}>{t(`tr.nstate_${n.state}`)}</Badge></Td>
          <Td end>
            {n.state === "open" && <button onClick={() => nAct(n, "plan")} className="text-brand-600 hover:underline text-xs me-2">{t("tr.plan")}</button>}
            {n.state !== "closed" && <button onClick={() => nAct(n, "close")} className="text-slate-500 hover:underline text-xs">{t("tr.close")}</button>}
          </Td>
        </tr>
      ))}
    </Table>
  );
}
