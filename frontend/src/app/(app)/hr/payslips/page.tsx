"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { Paginated, Payslip } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import {
  Badge,
  EmptyRow,
  ExportExcelButton,
  money,
  PageHeader,
  Pagination,
  payslipStateTone,
  Spinner,
  StatCard,
  Table,
  Td,
  Th,
} from "@/components/ui";

type PayslipPage = Paginated<Payslip> & {
  totals: { count: number; verify: number; done: number; this_month: number; net_total: number };
};

const PAY_STATUS_TONES: Record<string, string> = { paid: "green", partial: "amber", unpaid: "rose" };

export default function PayslipsPage() {
  const { t } = useI18n();
  const { can } = useAuth();

  const [page, setPage] = useState<PayslipPage | null>(null);
  const [month, setMonth] = useState("");
  const [state, setState] = useState("");
  const [pageNum, setPageNum] = useState(1);

  const payroll = can("payslips.view");

  useEffect(() => {
    api
      .get<PayslipPage>(`/hr/payslips${qs({ month, state, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [month, state, pageNum]);

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={payroll ? t("nav.payslips") : t("nav.my_payslips")}>
        <ExportExcelButton path="/hr/payslips/export" params={{ month, state }} />
        {can("payslips.create") && (
          <Link
            href="/hr/payslips/new"
            className="h-9 px-4 inline-flex items-center rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium"
          >
            + {t("pay.create")}
          </Link>
        )}
      </PageHeader>

      {page && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
          {(
            [
              ["", "common.total", page.totals.count, "default", "pay.hint_total"],
              ["verify", "pay.state.verify", page.totals.verify, "amber", "pay.hint_verify"],
              ["done", "pay.state.done", page.totals.done, "green", "pay.hint_done"],
            ] as const
          ).map(([f, label, value, tone, hint]) => (
            <button
              key={label}
              type="button"
              onClick={() => {
                setState(f);
                setPageNum(1);
              }}
              className={`text-start rounded-xl transition ${
                state === f ? "ring-2 ring-brand-500" : "hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700"
              }`}
            >
              <StatCard label={t(label)} value={value} tone={tone} hint={t(hint)} />
            </button>
          ))}
          <button
            type="button"
            onClick={() => {
              setMonth(month ? "" : new Date().toISOString().slice(0, 7));
              setPageNum(1);
            }}
            className={`text-start rounded-xl transition ${
              month ? "ring-2 ring-brand-500" : "hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700"
            }`}
          >
            <StatCard label={t("pay.this_month")} value={page.totals.this_month} tone="brand" hint={t("pay.hint_this_month")} />
          </button>
          <StatCard
            label={t("pay.net_cumulative")}
            value={`${money(page.totals.net_total)}`}
            tone="green"
            hint={t("pay.hint_net")}
          />
        </div>
      )}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
        <input
          type="month"
          value={month}
          onChange={(e) => {
            setMonth(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
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
          {["draft", "verify", "done", "cancel"].map((s) => (
            <option key={s} value={s}>
              {t(`pay.state.${s}`)}
            </option>
          ))}
        </select>
        {(month || state) && (
          <button
            onClick={() => {
              setMonth("");
              setState("");
            }}
            className="text-xs text-slate-500 hover:text-slate-700"
          >
            {t("common.reset")}
          </button>
        )}
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("pay.reference")}</Th>
                <Th>{t("hr.employee")}</Th>
                <Th>{t("pay.period")}</Th>
                <Th>{t("pay.basic")}</Th>
                <Th>{t("pay.gross")}</Th>
                <Th>{t("pay.deductions")}</Th>
                <Th>{t("pay.net")}</Th>
                <Th>{t("common.status")}</Th>
                <Th>{t("pay.status")}</Th>
                <Th end />
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={10} />}
            {page.data.map((p) => (
              <tr key={p.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-mono text-xs text-slate-500">{p.number ?? "—"}</Td>
                <Td className="font-medium text-slate-900 dark:text-slate-100">{p.employee_name}</Td>
                <Td className="text-xs tabular-nums">
                  {p.date_from} → {p.date_to}
                </Td>
                <Td className="tabular-nums">{money(p.basic_total)}</Td>
                <Td className="tabular-nums font-medium">{money(p.gross_total)}</Td>
                <Td className="tabular-nums text-rose-600">{money(p.deduction_total)}</Td>
                <Td className="tabular-nums font-bold text-emerald-700">{money(p.net_total)}</Td>
                <Td>
                  <Badge tone={payslipStateTone(p.state)}>{t(`pay.state.${p.state}`)}</Badge>
                </Td>
                <Td>
                  {p.payment_status && (
                    <Badge tone={PAY_STATUS_TONES[p.payment_status] ?? "slate"}>
                      {t(`pay.pstatus.${p.payment_status}`)}
                    </Badge>
                  )}
                </Td>
                <Td end>
                  <Link href={`/hr/payslips/${p.id}`} className="text-brand-600 hover:underline text-xs">
                    {t("common.view")}
                  </Link>
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
