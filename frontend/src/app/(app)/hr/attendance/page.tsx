"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Attendance, Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import {
  EmptyRow,
  ErrorBox,
  ExportExcelButton,
  PageHeader,
  Pagination,
  Spinner,
  StatCard,
  Table,
  Td,
  Th,
} from "@/components/ui";

type AttendancePage = Paginated<Attendance> & {
  today: { present: number; checked_in: number };
};

export default function AttendancePage() {
  const { t } = useI18n();
  const { user, can } = useAuth();

  const [page, setPage] = useState<AttendancePage | null>(null);
  const [date, setDate] = useState("");
  const [pageNum, setPageNum] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    api
      .get<AttendancePage>(`/hr/attendances${qs({ date, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [date, pageNum]);

  useEffect(load, [load]);

  async function checkIn() {
    if (!user?.employee) return;
    setBusy(true);
    setError(null);
    try {
      await api.post("/hr/attendances/check-in", { employee_id: user.employee.odoo_id });
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  }

  async function checkOut(a: Attendance) {
    setBusy(true);
    setError(null);
    try {
      await api.post(`/hr/attendances/${a.id}/check-out`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  }

  const canDelete = (user?.roles ?? []).some((r) => ["admin", "hr_manager"].includes(r));

  async function destroy(a: Attendance) {
    if (!confirm(t("common.confirm_delete"))) return;
    setBusy(true);
    setError(null);
    try {
      await api.del(`/hr/attendances/${a.id}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  }

  const fmt = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString(undefined, { dateStyle: "short", timeStyle: "short" }) : null;

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.attendance")}>
        <ExportExcelButton path="/hr/attendances/export" params={{ date }} />
        {user?.employee && (
          <button
            onClick={checkIn}
            disabled={busy}
            className="h-9 px-4 rounded-md bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-medium"
          >
            ⏰ {t("att.check_in")}
          </button>
        )}
      </PageHeader>

      {error && <ErrorBox message={error} />}

      {page && (
        <div className="grid grid-cols-2 gap-3 mb-5">
          <StatCard label={t("att.present_today")} value={page.today.present} tone="brand" />
          <StatCard label={t("att.currently_in")} value={page.today.checked_in} tone="green" />
        </div>
      )}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap items-center gap-2">
        <input
          type="date"
          value={date}
          onChange={(e) => {
            setDate(e.target.value);
            setPageNum(1);
          }}
          className="h-9 px-3 border border-slate-200 dark:border-slate-700 rounded-md text-sm bg-white dark:bg-slate-900"
        />
        {date && (
          <button onClick={() => setDate("")} className="text-xs text-slate-500 hover:text-slate-700">
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
                <Th>{t("hr.employee")}</Th>
                <Th>{t("att.check_in")}</Th>
                <Th>{t("att.check_out")}</Th>
                <Th>{t("att.worked_hours")}</Th>
                <Th end>{t("common.actions")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={5} />}
            {page.data.map((a) => {
              const own =
                user?.employee && a.employee_name === user.employee.name; // display hint only — API enforces
              return (
                <tr key={a.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                  <Td className="font-medium text-slate-900 dark:text-slate-100">{a.employee_name}</Td>
                  <Td className="text-xs tabular-nums">{fmt(a.check_in)}</Td>
                  <Td className="text-xs tabular-nums">
                    {a.check_out ? (
                      fmt(a.check_out)
                    ) : (
                      <span className="text-emerald-600 font-semibold">⏰ {t("att.at_work")}</span>
                    )}
                  </Td>
                  <Td className="tabular-nums">{Number(a.worked_hours).toFixed(2)}</Td>
                  <Td end>
                    <span className="whitespace-nowrap">
                      {!a.check_out && (can("hr.view_all") || own) && (
                        <button
                          onClick={() => checkOut(a)}
                          disabled={busy}
                          className="text-accent-600 hover:underline text-xs me-3"
                        >
                          {t("att.check_out")}
                        </button>
                      )}
                      {canDelete && (
                        <button
                          onClick={() => destroy(a)}
                          disabled={busy}
                          className="text-rose-600 hover:underline text-xs"
                        >
                          {t("common.delete")}
                        </button>
                      )}
                    </span>
                  </Td>
                </tr>
              );
            })}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}
