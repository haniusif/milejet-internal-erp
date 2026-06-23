"use client";

import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { FleetService, Paginated } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, money, PageHeader, Pagination, Spinner, Table, Td, Th } from "@/components/ui";

export default function FleetServicesPage() {
  const { t } = useI18n();
  const [page, setPage] = useState<Paginated<FleetService> | null>(null);
  const [q, setQ] = useState("");
  const [pageNum, setPageNum] = useState(1);

  useEffect(() => {
    api
      .get<Paginated<FleetService>>(`/fleet/services${qs({ q, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [q, pageNum]);

  return (
    <div>
      <PageHeader kicker={t("nav.fleet")} title={t("nav.services")} />

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5">
        <input
          value={q}
          onChange={(e) => {
            setQ(e.target.value);
            setPageNum(1);
          }}
          placeholder={t("common.search")}
          className="h-9 px-3 border border-slate-200 rounded-md text-sm w-full md:w-80"
        />
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("fleet.vehicle")}</Th>
                <Th>{t("nav.services")}</Th>
                <Th>{t("common.date")}</Th>
                <Th>{t("common.amount")}</Th>
                <Th>{t("common.status")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={5} />}
            {page.data.map((s) => (
              <tr key={s.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-medium text-slate-900 dark:text-slate-100">{s.vehicle_name}</Td>
                <Td>
                  {s.service_type ?? "—"}
                  {s.description && <span className="text-xs text-slate-400 block">{s.description}</span>}
                </Td>
                <Td className="tabular-nums text-xs">{s.date}</Td>
                <Td className="tabular-nums">{s.amount != null ? money(s.amount) : "—"}</Td>
                <Td>
                  {s.state ? (
                    <Badge tone={s.state === "done" ? "green" : s.state === "running" ? "amber" : "slate"}>
                      {s.state}
                    </Badge>
                  ) : (
                    "—"
                  )}
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
