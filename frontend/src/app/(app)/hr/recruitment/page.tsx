"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { JobPosition, Paginated } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { EmptyRow, PageHeader, Pagination, Spinner, StatCard, Table, Td, Th } from "@/components/ui";

type JobsPage = Paginated<JobPosition> & {
  totals?: { jobs: number; openings: number; applicants: number; hired: number; refused: number };
};

export default function RecruitmentJobsPage() {
  const { t } = useI18n();
  const [page, setPage] = useState<JobsPage | null>(null);
  const [q, setQ] = useState("");
  const [pageNum, setPageNum] = useState(1);

  useEffect(() => {
    api
      .get<JobsPage>(`/recruitment/jobs${qs({ q, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [q, pageNum]);

  return (
    <div>
      <PageHeader kicker={t("nav.recruitment")} title={t("rec.jobs")}>
        <Link
          href="/hr/recruitment/applicants"
          className="h-9 px-4 inline-flex items-center rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-medium"
        >
          {t("nav.applicants")}
        </Link>
      </PageHeader>

      {page?.totals && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
          <StatCard label={t("rec.jobs")} value={page.totals.jobs} tone="brand" hint={t("rec.hint_jobs")} />
          <StatCard label={t("rec.openings")} value={page.totals.openings} hint={t("rec.hint_openings")} />
          {(
            [
              ["", "rec.applicants", page.totals.applicants, "amber", "rec.hint_applicants"],
              ["hired", "rec.hired", page.totals.hired, "green", "rec.hint_hired"],
              ["refused", "rec.refused_plural", page.totals.refused, "rose", "rec.hint_refused"],
            ] as const
          ).map(([status, label, value, tone, hint]) => (
            <Link
              key={label}
              href={`/hr/recruitment/applicants${status ? `?status=${status}` : ""}`}
              className="rounded-xl transition hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700"
            >
              <StatCard label={t(label)} value={value} tone={tone} hint={t(hint)} />
            </Link>
          ))}
        </div>
      )}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5">
        <input
          value={q}
          onChange={(e) => {
            setQ(e.target.value);
            setPageNum(1);
          }}
          placeholder={t("common.search")}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm w-full md:w-80 bg-white dark:bg-slate-900"
        />
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("hr.job_title")}</Th>
                <Th>{t("hr.department")}</Th>
                <Th>{t("rec.openings")}</Th>
                <Th>{t("rec.applicants")}</Th>
                <Th>{t("rec.recruiter")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={5} />}
            {page.data.map((j) => (
              <tr key={j.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-medium text-slate-900 dark:text-slate-100">{j.name}</Td>
                <Td>{j.department ?? "—"}</Td>
                <Td className="tabular-nums">{j.openings}</Td>
                <Td className="tabular-nums">
                  <Link
                    href={`/hr/recruitment/applicants?job_id=${j.odoo_id}`}
                    className="text-brand-600 hover:underline"
                  >
                    {j.applicants}
                  </Link>
                </Td>
                <Td>{j.recruiter ?? "—"}</Td>
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}
