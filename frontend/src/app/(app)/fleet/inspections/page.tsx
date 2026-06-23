"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { Paginated } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, PageHeader, Pagination, Spinner, StatCard, Table, Td, Th } from "@/components/ui";

interface InspectionRow {
  id: number;
  name: string | null;
  vehicle_id: number | null;
  vehicle_name: string | null;
  direction: string | null;
  state: string;
  result: string | null;
  date_inspected: string | null;
  lines_count: number;
}

type InspPage = Paginated<InspectionRow> & {
  stats: { total: number; draft: number; confirmed: number; failed: number };
};

const STATE_TONE: Record<string, string> = { confirmed: "green", cancel: "rose", draft: "amber" };
const RESULT_TONE: Record<string, string> = { success: "green", failure: "rose", todo: "amber" };

export default function FleetInspectionsPage() {
  const { t } = useI18n();
  const [page, setPage] = useState<InspPage | null>(null);
  const [q, setQ] = useState("");
  const [state, setState] = useState("");
  const [pageNum, setPageNum] = useState(1);

  useEffect(() => {
    api
      .get<InspPage>(`/fleet/inspections${qs({ q, state, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [q, state, pageNum]);

  return (
    <div>
      <PageHeader kicker={t("nav.fleet")} title={t("fleet.inspections")} />

      {page && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
          {(
            [
              ["", "common.total", page.stats.total, "default"],
              ["draft", "fleet.usage_draft", page.stats.draft, "amber"],
              ["confirmed", "fleet.res_success", page.stats.confirmed, "green"],
              ["", "fleet.res_failure", page.stats.failed, "rose"],
            ] as const
          ).map(([filter, label, value, tone], i) => (
            <button
              key={label}
              type="button"
              onClick={() => filter !== undefined && i !== 3 && (setState(state === filter ? "" : filter), setPageNum(1))}
              className={`text-start rounded-xl transition ${state === filter && filter !== "" ? "ring-2 ring-brand-500" : ""}`}
            >
              <StatCard label={t(label)} value={value} tone={tone} />
            </button>
          ))}
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
          value={state}
          onChange={(e) => {
            setState(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("common.all")}</option>
          {["draft", "confirmed", "cancel"].map((s) => (
            <option key={s} value={s}>
              {s}
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
                <Th>{t("loan.reference")}</Th>
                <Th>{t("fleet.vehicle")}</Th>
                <Th>{t("fleet.direction")}</Th>
                <Th>{t("common.date")}</Th>
                <Th>{t("fleet.result")}</Th>
                <Th>{t("common.status")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={6} />}
            {page.data.map((i) => (
              <tr key={i.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-mono text-xs text-slate-500">{i.name ?? "—"}</Td>
                <Td className="font-medium text-slate-900 dark:text-slate-100">
                  {i.vehicle_id ? (
                    <Link href={`/fleet/vehicles/${i.vehicle_id}`} className="hover:underline text-brand-600">
                      {i.vehicle_name}
                    </Link>
                  ) : (
                    i.vehicle_name ?? "—"
                  )}
                </Td>
                <Td>{i.direction ? t(`fleet.dir_${i.direction}`) : "—"}</Td>
                <Td className="tabular-nums text-xs">{i.date_inspected?.slice(0, 16).replace("T", " ") ?? "—"}</Td>
                <Td>{i.result ? <Badge tone={RESULT_TONE[i.result] ?? "slate"}>{t(`fleet.res_${i.result}`)}</Badge> : "—"}</Td>
                <Td>
                  <Badge tone={STATE_TONE[i.state] ?? "slate"}>{i.state}</Badge>
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
