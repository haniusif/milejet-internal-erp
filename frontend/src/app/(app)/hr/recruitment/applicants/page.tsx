"use client";

import { useSearchParams } from "next/navigation";
import { Suspense, useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Applicant, Paginated, RecruitmentStage } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, money, PageHeader, Pagination, Spinner, Table, Td, Th } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

type ApplicantPage = Paginated<Applicant> & {
  stages: RecruitmentStage[];
  jobs: { odoo_id: number; name: string }[];
};

function ApplicantsInner() {
  const { t } = useI18n();
  const { can } = useAuth();
  const params = useSearchParams();

  const [page, setPage] = useState<ApplicantPage | null>(null);
  const [q, setQ] = useState("");
  const [jobId, setJobId] = useState(params.get("job_id") ?? "");
  const [status, setStatus] = useState(params.get("status") ?? "");
  const [pageNum, setPageNum] = useState(1);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const writable = can("recruitment.write");

  const load = useCallback(() => {
    api
      .get<ApplicantPage>(`/recruitment/applicants${qs({ q, job_id: jobId, status, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [q, jobId, status, pageNum]);

  useEffect(load, [load]);

  async function moveStage(a: Applicant, stageId: number) {
    setError(null);
    try {
      await api.post(`/recruitment/applicants/${a.id}/stage`, { stage_id: stageId });
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  async function act(a: Applicant, action: "refuse" | "restore") {
    setError(null);
    try {
      await api.post(`/recruitment/applicants/${a.id}/${action}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.recruitment")} title={t("nav.applicants")}>
        {writable && (
          <button
            onClick={() => setShowForm((v) => !v)}
            className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
          >
            + {t("rec.new_applicant")}
          </button>
        )}
      </PageHeader>

      {error && <ErrorBox message={error} />}

      {showForm && page && (
        <ApplicantForm
          jobs={page.jobs}
          onDone={() => {
            setShowForm(false);
            load();
          }}
        />
      )}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
        <input
          value={q}
          onChange={(e) => {
            setQ(e.target.value);
            setPageNum(1);
          }}
          placeholder={t("common.search")}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm flex-1 min-w-40 bg-white dark:bg-slate-900"
        />
        <select
          value={jobId}
          onChange={(e) => {
            setJobId(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("common.all")}</option>
          {(page?.jobs ?? []).map((j) => (
            <option key={j.odoo_id} value={j.odoo_id}>
              {j.name}
            </option>
          ))}
        </select>
        <select
          value={status}
          onChange={(e) => {
            setStatus(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("crm.open")}</option>
          <option value="hired">{t("rec.hired")}</option>
          <option value="refused">{t("rec.refused")}</option>
          <option value="all">{t("common.all")}</option>
        </select>
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("common.name")}</Th>
                <Th>{t("hr.job_title")}</Th>
                <Th>{t("rec.salary_expected")}</Th>
                <Th>{t("rec.stage")}</Th>
                {writable && <Th end>{t("common.actions")}</Th>}
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={writable ? 5 : 4} />}
            {page.data.map((a) => (
              <tr key={a.id} className={`hover:bg-slate-50/60 ${!a.active ? "opacity-60" : ""}`}>
                <Td className="font-medium text-slate-900 dark:text-slate-100">
                  {a.name}
                  {a.email && <span className="block text-xs text-slate-400">{a.email}</span>}
                </Td>
                <Td>{a.job_name ?? "—"}</Td>
                <Td className="tabular-nums">{a.salary_expected ? money(a.salary_expected) : "—"}</Td>
                <Td>
                  {writable && a.active ? (
                    <select
                      value={a.stage_id ?? ""}
                      onChange={(e) => e.target.value && moveStage(a, Number(e.target.value))}
                      className="h-8 px-2 border border-slate-200 dark:border-slate-700 rounded-md text-xs bg-white dark:bg-slate-900"
                    >
                      <option value="">—</option>
                      {page.stages.map((s) => (
                        <option key={s.odoo_id} value={s.odoo_id}>
                          {s.name}
                        </option>
                      ))}
                    </select>
                  ) : (
                    <Badge tone={a.active ? "brand" : "rose"}>{a.active ? (a.stage_name ?? "—") : t("rec.refused")}</Badge>
                  )}
                </Td>
                {writable && (
                  <Td end>
                    {a.active ? (
                      <button onClick={() => act(a, "refuse")} className="text-rose-600 hover:underline text-xs">
                        {t("rec.refuse")}
                      </button>
                    ) : (
                      <button onClick={() => act(a, "restore")} className="text-brand-600 hover:underline text-xs">
                        {t("rec.restore")}
                      </button>
                    )}
                  </Td>
                )}
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}

function ApplicantForm({
  jobs,
  onDone,
}: {
  jobs: { odoo_id: number; name: string }[];
  onDone: () => void;
}) {
  const { t } = useI18n();
  const [form, setForm] = useState({ partner_name: "", email_from: "", partner_phone: "", job_id: "", salary_expected: "" });
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.post("/recruitment/applicants", {
        partner_name: form.partner_name,
        email_from: form.email_from || undefined,
        partner_phone: form.partner_phone || undefined,
        job_id: Number(form.job_id),
        salary_expected: form.salary_expected ? Number(form.salary_expected) : undefined,
      });
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
            <TextInput required value={form.partner_name} onChange={set("partner_name")} />
          </Field>
          <Field label={t("hr.job_title")} required>
            <Select required value={form.job_id} onChange={set("job_id")}>
              <option value="">—</option>
              {jobs.map((j) => (
                <option key={j.odoo_id} value={j.odoo_id}>
                  {j.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label={t("auth.email")}>
            <TextInput type="email" value={form.email_from} onChange={set("email_from")} />
          </Field>
          <Field label={t("hr.work_phone")}>
            <TextInput value={form.partner_phone} onChange={set("partner_phone")} />
          </Field>
          <Field label={t("rec.salary_expected")}>
            <TextInput type="number" min="0" step="0.01" value={form.salary_expected} onChange={set("salary_expected")} />
          </Field>
        </div>
        <SubmitButton busy={busy} />
      </FormCard>
    </div>
  );
}

export default function ApplicantsPage() {
  return (
    <Suspense fallback={<Spinner />}>
      <ApplicantsInner />
    </Suspense>
  );
}
