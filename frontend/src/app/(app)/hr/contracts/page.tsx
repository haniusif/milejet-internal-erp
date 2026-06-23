"use client";

import { useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Contract, Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, ErrorBox, ExportExcelButton, money, PageHeader, Pagination, Spinner, StatCard, Table, Td, Th } from "@/components/ui";

type ContractPage = Paginated<Contract> & {
  totals: { count: number; open: number; expiring: number; expired: number; total_wage: number };
};

const STATE_TONES: Record<string, string> = { open: "green", draft: "amber", close: "slate", cancel: "rose" };

export default function ContractsPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const manager = can("employees.write");
  const [page, setPage] = useState<ContractPage | null>(null);
  const [q, setQ] = useState("");
  const [state, setState] = useState("");
  const [pageNum, setPageNum] = useState(1);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function load() {
    api
      .get<ContractPage>(`/hr/contracts${qs({ q, state, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }

  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(load, [q, state, pageNum]);

  async function renew(c: Contract) {
    const input = prompt(t("con.renew_months_prompt"), String(c.id && 12));
    if (input === null) return;
    const months = Number(input);
    if (!months) return;
    setBusy(true);
    setError(null);
    try {
      await api.post(`/hr/contracts/${c.id}/renew`, { months });
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.contracts")}>
        <ExportExcelButton path="/hr/contracts/export" params={{ q, state }} />
      </PageHeader>

      {error && <ErrorBox message={error} />}

      {page && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
          {(
            [
              ["", "common.total", page.totals.count, "default", "con.hint.total"],
              ["open", "con.open_count", page.totals.open, "green", "con.hint.open"],
              ["expiring", "con.expiring_count", page.totals.expiring, "amber", "con.hint.expiring"],
              ["expired", "con.expired_count", page.totals.expired, "rose", "con.hint.expired"],
            ] as const
          ).map(([filter, label, value, tone, hint]) => (
            <button
              key={label}
              type="button"
              onClick={() => {
                setState(filter);
                setPageNum(1);
              }}
              className={`text-start rounded-xl transition ${
                state === filter ? "ring-2 ring-brand-500" : "hover:ring-1 hover:ring-slate-300 dark:hover:ring-slate-700"
              }`}
            >
              <StatCard label={t(label)} value={value} tone={tone} hint={t(hint)} />
            </button>
          ))}
          <StatCard
            label={t("con.total_wage")}
            value={money(page.totals.total_wage)}
            tone="brand"
            hint={t("con.hint.total_wage")}
          />
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
          value={state}
          onChange={(e) => {
            setState(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        >
          <option value="">{t("common.all")}</option>
          {["open", "draft", "close", "cancel"].map((s) => (
            <option key={s} value={s}>
              {t(`con.state.${s}`)}
            </option>
          ))}
          <option value="expiring">{t("con.expiring_count")}</option>
          <option value="expired">{t("con.expired_count")}</option>
        </select>
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("hr.employee")}</Th>
                <Th>{t("common.name")}</Th>
                <Th>{t("con.wage")}</Th>
                <Th>{t("con.start")}</Th>
                <Th>{t("con.end")}</Th>
                <Th>{t("common.status")}</Th>
                <Th end>{t("common.actions")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={7} />}
            {page.data.map((c) => (
              <tr key={c.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-medium text-slate-900 dark:text-slate-100">{c.employee_name}</Td>
                <Td className="text-xs">{c.name ?? "—"}</Td>
                <Td className="tabular-nums font-medium">{money(c.wage)}</Td>
                <Td className="tabular-nums text-xs">{c.date_start}</Td>
                <Td className="tabular-nums text-xs">{c.date_end ?? "—"}</Td>
                <Td>
                  <Badge tone={STATE_TONES[c.state] ?? "slate"}>{t(`con.state.${c.state}`)}</Badge>
                  {c.signed && (
                    <span className="ms-1">
                      <Badge tone="green">{t("con.signed")}</Badge>
                    </span>
                  )}
                </Td>
                <Td end>
                  <a
                    href={`/api/v1/hr/contracts/${c.id}/pdf`}
                    target="_blank"
                    rel="noreferrer"
                    className="text-brand-600 hover:underline text-xs me-3"
                  >
                    {t("forms.pdf")}
                  </a>
                  {manager && c.state !== "cancel" && (
                    <button
                      onClick={() => renew(c)}
                      disabled={busy}
                      className="text-emerald-600 hover:underline text-xs disabled:opacity-50"
                    >
                      {t("con.renew")}
                    </button>
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
