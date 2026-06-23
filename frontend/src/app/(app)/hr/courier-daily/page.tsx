"use client";

// Courier daily performance (mj.courier.daily): attendance + last-mile delivery
// stats per courier per day. Read-only; filter by month / city / search.

import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { Paginated } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, PageHeader, Pagination, Spinner, StatCard, Table, Td, Th } from "@/components/ui";

interface Row {
  id: number;
  date: string | null;
  courier_name: string | null;
  vehicle_plate: string | null;
  city: string | null;
  present: boolean;
  ofd: number;
  delivered: number;
  performance: number;
}

type Page = Paginated<Row> & {
  months: string[];
  totals: { rows: number; present: number; ofd: number; delivered: number };
};

function perfTone(p: number): string {
  if (p >= 80) return "green";
  if (p >= 50) return "amber";
  return "rose";
}

export default function CourierDailyPage() {
  const { t } = useI18n();
  const [page, setPage] = useState<Page | null>(null);
  const [month, setMonth] = useState("");
  const [q, setQ] = useState("");
  const [pageNum, setPageNum] = useState(1);

  useEffect(() => {
    api
      .get<Page>(`/hr/courier-daily${qs({ month, q, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [month, q, pageNum]);

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("cd.title")} />

      {page && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
          <StatCard label={t("cd.rows")} value={page.totals.rows} />
          <StatCard label={t("cd.present")} value={page.totals.present} tone="green" />
          <StatCard label={t("cd.ofd")} value={page.totals.ofd.toLocaleString()} tone="brand" />
          <StatCard label={t("cd.delivered")} value={page.totals.delivered.toLocaleString()} tone="amber" />
        </div>
      )}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap gap-2">
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
          value={month}
          onChange={(e) => {
            setMonth(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("cd.all_months")}</option>
          {(page?.months ?? []).map((m) => (
            <option key={m} value={m}>
              {m}
            </option>
          ))}
        </select>
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("common.date")}</Th>
                <Th>{t("hr.employee")}</Th>
                <Th>{t("fleet.plate")}</Th>
                <Th>{t("cd.city")}</Th>
                <Th>{t("cd.present_h")}</Th>
                <Th end>{t("cd.ofd")}</Th>
                <Th end>{t("cd.delivered")}</Th>
                <Th end>{t("cd.perf")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={8} />}
            {page.data.map((r) => (
              <tr key={r.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="tabular-nums text-xs">{r.date}</Td>
                <Td className="font-medium text-slate-900 dark:text-slate-100">{r.courier_name}</Td>
                <Td className="text-xs">{r.vehicle_plate ?? "—"}</Td>
                <Td className="text-xs">{r.city ?? "—"}</Td>
                <Td>{r.present ? <Badge tone="green">✓</Badge> : <Badge tone="slate">—</Badge>}</Td>
                <Td end className="tabular-nums">{r.ofd}</Td>
                <Td end className="tabular-nums">{r.delivered}</Td>
                <Td end>
                  {r.ofd > 0 ? <Badge tone={perfTone(r.performance)}>{Math.round(r.performance)}%</Badge> : "—"}
                </Td>
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}
