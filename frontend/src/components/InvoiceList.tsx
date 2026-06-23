"use client";

// Shared list for Finance invoices & bills — same API shape, different scope.

import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { FinanceInvoice, Paginated } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import {
  Badge,
  EmptyRow,
  money,
  PageHeader,
  Pagination,
  Spinner,
  StatCard,
  Table,
  Td,
  Th,
} from "@/components/ui";

type InvoicePage = Paginated<FinanceInvoice> & {
  stats: { count: number; total: number; due: number; overdue: number };
  currency: string;
};

export default function InvoiceList({ scope }: { scope: "invoices" | "bills" }) {
  const { t } = useI18n();
  const [page, setPage] = useState<InvoicePage | null>(null);
  const [q, setQ] = useState("");
  const [status, setStatus] = useState("");
  const [pageNum, setPageNum] = useState(1);

  useEffect(() => {
    api
      .get<InvoicePage>(`/finance/${scope}${qs({ q, status, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [scope, q, status, pageNum]);

  const payTone = (s: string | null) =>
    s === "paid" ? "green" : s === "partial" ? "amber" : "rose";

  return (
    <div>
      <PageHeader
        kicker={t("nav.finance")}
        title={scope === "invoices" ? t("nav.invoices") : t("nav.bills")}
      />

      {page && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
          <StatCard label={t("fin.posted")} value={page.stats.count} tone="brand" />
          <StatCard label={t("common.total")} value={money(page.stats.total)} />
          <StatCard label={t("fin.residual")} value={money(page.stats.due)} tone="amber" />
          <StatCard label={t("fin.overdue")} value={page.stats.overdue} tone="rose" />
        </div>
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
          value={status}
          onChange={(e) => {
            setStatus(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("common.all")}</option>
          <option value="draft">{t("fin.draft")}</option>
          <option value="posted">{t("fin.posted")}</option>
          <option value="unpaid">{t("fin.unpaid")}</option>
          <option value="overdue">{t("fin.overdue")}</option>
        </select>
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("fin.invoice")}</Th>
                <Th>{t("fin.partner")}</Th>
                <Th>{t("common.date")}</Th>
                <Th>{t("fin.due_date")}</Th>
                <Th end>{t("common.amount")}</Th>
                <Th end>{t("fin.residual")}</Th>
                <Th>{t("common.status")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={7} />}
            {page.data.map((inv) => (
              <tr key={inv.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-mono text-xs text-slate-700">
                  <a href={`/finance/${inv.id}`} className="hover:text-brand-600 hover:underline">
                    {inv.name}
                  </a>
                </Td>
                <Td className="font-medium text-slate-900">{inv.partner_name ?? "—"}</Td>
                <Td className="tabular-nums text-xs">{inv.invoice_date}</Td>
                <Td className="tabular-nums text-xs">{inv.invoice_date_due}</Td>
                <Td end className="tabular-nums font-medium">
                  {money(inv.amount_total)}
                </Td>
                <Td end className="tabular-nums text-rose-600">
                  {Number(inv.amount_residual) > 0 ? money(inv.amount_residual) : "—"}
                </Td>
                <Td>
                  {inv.state === "posted" ? (
                    <Badge tone={payTone(inv.payment_state)}>
                      {inv.payment_state === "paid"
                        ? t("fin.paid")
                        : inv.payment_state === "partial"
                          ? t("fin.partial")
                          : t("fin.not_paid")}
                    </Badge>
                  ) : (
                    <Badge tone="slate">{t("fin.draft")}</Badge>
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
