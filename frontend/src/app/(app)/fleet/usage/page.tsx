"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { FleetUsage, Paginated } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, PageHeader, Pagination, Spinner, StatCard, Table, Td, Th } from "@/components/ui";

type UsageRow = FleetUsage & { vehicle_id: number | null; vehicle_name: string | null };

type UsagePage = Paginated<UsageRow> & {
  stats: { total: number; in_use: number; reserved: number; returned: number };
};

const STATE_TONE: Record<string, string> = {
  in_use: "brand",
  reserved: "amber",
  returned: "green",
  cancel: "rose",
  draft: "slate",
};

export default function FleetUsagePage() {
  const { t } = useI18n();
  const [page, setPage] = useState<UsagePage | null>(null);
  const [q, setQ] = useState("");
  const [state, setState] = useState("");
  const [pageNum, setPageNum] = useState(1);

  useEffect(() => {
    api
      .get<UsagePage>(`/fleet/usages${qs({ q, state, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [q, state, pageNum]);

  return (
    <div>
      <PageHeader kicker={t("nav.fleet")} title={t("fleet.usage")} />

      {page && (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
          {(
            [
              ["", "common.total", page.stats.total, "default"],
              ["in_use", "fleet.usage_in_use", page.stats.in_use, "brand"],
              ["reserved", "fleet.usage_reserved", page.stats.reserved, "amber"],
              ["returned", "fleet.usage_returned", page.stats.returned, "green"],
            ] as const
          ).map(([filter, label, value, tone]) => (
            <button
              key={label}
              type="button"
              onClick={() => filter !== "" && (setState(state === filter ? "" : filter), setPageNum(1))}
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
          {["draft", "reserved", "in_use", "returned", "cancel"].map((s) => (
            <option key={s} value={s}>
              {t(`fleet.usage_${s}`)}
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
                <Th>{t("fleet.driver")}</Th>
                <Th>{t("fleet.from")}</Th>
                <Th>{t("fleet.to")}</Th>
                <Th>{t("common.status")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={6} />}
            {page.data.map((u) => (
              <tr key={u.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-mono text-xs text-slate-500">{u.name ?? "—"}</Td>
                <Td className="font-medium text-slate-900 dark:text-slate-100">
                  {u.vehicle_id ? (
                    <Link href={`/fleet/vehicles/${u.vehicle_id}`} className="hover:underline text-brand-600">
                      {u.vehicle_name}
                    </Link>
                  ) : (
                    u.vehicle_name ?? "—"
                  )}
                </Td>
                <Td>{u.partner_name ?? "—"}</Td>
                <Td className="tabular-nums text-xs">{u.date_picking?.slice(0, 10) ?? "—"}</Td>
                <Td className="tabular-nums text-xs">{u.date_return?.slice(0, 10) ?? "—"}</Td>
                <Td>
                  <Badge tone={STATE_TONE[u.state] ?? "slate"}>{t(`fleet.usage_${u.state}`)}</Badge>
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
