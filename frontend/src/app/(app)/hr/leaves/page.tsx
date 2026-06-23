"use client";

import { useCallback, useEffect, useState } from "react";
import { api, ApiError, qs } from "@/lib/api";
import type { Leave, LeaveAttachment, LeaveType, Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import {
  Badge,
  EmptyRow,
  ErrorBox,
  ExportExcelButton,
  leaveStateTone,
  PageHeader,
  Pagination,
  Spinner,
  StatCard,
  SuccessBox,
  Table,
  Td,
  Th,
} from "@/components/ui";

const STATES = ["", "draft", "confirm", "validate", "refuse", "cancel", "today"];

type LeavePage = Paginated<Leave> & {
  totals?: { total: number; pending: number; approved: number; refused: number; today: number };
};

export default function LeavesPage() {
  const { t } = useI18n();
  const { user, can } = useAuth();

  const [page, setPage] = useState<LeavePage | null>(null);
  const [state, setState] = useState("");
  const [pageNum, setPageNum] = useState(1);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const hrStaff = can("hr.view_all");
  const approver = can("leaves.approve");
  const deleter = can("leaves.delete");
  const [attachments, setAttachments] = useState<Record<number, LeaveAttachment[]>>({});

  const load = useCallback(() => {
    api
      .get<LeavePage>(`/hr/leaves${qs({ state, page: pageNum })}`)
      .then((p) => {
        setPage(p);
        const ids = p.data.map((l) => l.id).join(",");
        if (ids) {
          api
            .get<{ data: Record<number, LeaveAttachment[]> }>(`/hr/leaves/attachments?ids=${ids}`)
            .then((r) => setAttachments(r.data))
            .catch(() => {});
        }
      })
      .catch(() => setPage(null));
  }, [state, pageNum]);

  useEffect(load, [load]);

  async function act(leave: Leave, action: "approve" | "refuse") {
    setError(null);
    try {
      await api.post(`/hr/leaves/${leave.id}/${action}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  async function destroy(leave: Leave) {
    if (!confirm(t("common.confirm_delete"))) return;
    setError(null);
    try {
      await api.del(`/hr/leaves/${leave.id}`);
      load();
    } catch (e) {
      setError(e instanceof ApiError ? e.message : t("common.error"));
    }
  }

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={hrStaff ? t("nav.leaves") : t("nav.my_leaves")}>
        <ExportExcelButton path="/hr/leaves/export" params={{ state }} />
        <button
          onClick={() => setShowForm((v) => !v)}
          className="h-9 px-4 rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium"
        >
          + {t("leave.new_request")}
        </button>
      </PageHeader>

      {error && <ErrorBox message={error} />}
      {success && <SuccessBox message={success} />}

      {showForm && (
        <LeaveForm
          onDone={(msg) => {
            setShowForm(false);
            setSuccess(msg);
            load();
          }}
          selfOdooId={!hrStaff ? user?.employee?.odoo_id : undefined}
        />
      )}

      {page?.totals && (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5">
          {(
            [
              ["", "common.total", page.totals.total, "default", "leave.hint_total"],
              ["confirm", "leave.state.confirm", page.totals.pending, "amber", "leave.hint_pending"],
              ["validate", "leave.state.validate", page.totals.approved, "green", "leave.hint_approved"],
              ["refuse", "leave.state.refuse", page.totals.refused, "rose", "leave.hint_refused"],
              ["today", "leave.on_leave_today", page.totals.today, "brand", "leave.hint_today"],
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
        </div>
      )}

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5 flex flex-wrap gap-2 text-sm">
        {STATES.map((s) => (
          <button
            key={s}
            onClick={() => {
              setState(s);
              setPageNum(1);
            }}
            className={`px-3 py-1 rounded-md ${
              state === s ? "bg-brand-700 text-white" : "bg-slate-100 text-slate-600 hover:bg-slate-200"
            }`}
          >
            {s === "" ? t("common.all") : s === "today" ? t("leave.on_leave_today") : t(`leave.state.${s}`)}
          </button>
        ))}
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("hr.employee")}</Th>
                <Th>{t("leave.type")}</Th>
                <Th>{t("leave.from")}</Th>
                <Th>{t("leave.to")}</Th>
                <Th>{t("leave.days")}</Th>
                <Th>{t("common.status")}</Th>
                <Th>{t("common.attachments")}</Th>
                {(approver || deleter) && <Th end>{t("common.actions")}</Th>}
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={approver || deleter ? 8 : 7} />}
            {page.data.map((l) => (
              <tr key={l.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-medium text-slate-900 dark:text-slate-100">{l.employee_name}</Td>
                <Td>{l.leave_type ?? "—"}</Td>
                <Td>{l.date_from}</Td>
                <Td>{l.date_to}</Td>
                <Td>{l.number_of_days}</Td>
                <Td>
                  <Badge tone={leaveStateTone(l.state)}>{t(`leave.state.${l.state}`)}</Badge>
                </Td>
                <Td>
                  {(attachments[l.id] ?? []).map((att) => (
                    <a
                      key={att.id}
                      href={`/api/v1/hr/leaves/attachments/${att.id}`}
                      target="_blank"
                      rel="noreferrer"
                      title={att.name}
                      className="block max-w-40 truncate text-xs text-brand-600 hover:underline"
                    >
                      📎 {att.name}
                    </a>
                  ))}
                  {(attachments[l.id] ?? []).length === 0 && <span className="text-slate-300">—</span>}
                </Td>
                {(approver || deleter) && (
                  <Td end>
                    <span className="whitespace-nowrap">
                      {approver && ["draft", "confirm"].includes(l.state) && (
                        <>
                          <button
                            onClick={() => act(l, "approve")}
                            className="text-emerald-600 hover:underline text-xs me-3"
                          >
                            {t("leave.approve")}
                          </button>
                          <button
                            onClick={() => act(l, "refuse")}
                            className="text-rose-600 hover:underline text-xs me-3"
                          >
                            {t("leave.refuse")}
                          </button>
                        </>
                      )}
                      {deleter && (
                        <button onClick={() => destroy(l)} className="text-rose-600 hover:underline text-xs">
                          {t("common.delete")}
                        </button>
                      )}
                    </span>
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

function LeaveForm({
  onDone,
  selfOdooId,
}: {
  onDone: (msg: string) => void;
  selfOdooId?: number;
}) {
  const { t } = useI18n();
  const { can } = useAuth();

  const [types, setTypes] = useState<LeaveType[]>([]);
  const [employees, setEmployees] = useState<{ odoo_id: number; name: string }[]>([]);
  const [employeeId, setEmployeeId] = useState<string>(selfOdooId ? String(selfOdooId) : "");
  const [typeId, setTypeId] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [reason, setReason] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.get<{ data: LeaveType[] }>("/hr/leave-types").then((r) => setTypes(r.data)).catch(() => {});
    if (can("hr.view_all")) {
      api
        .get<{ data: { odoo_id: number; name: string }[] }>("/hr/employees?per_page=100")
        .then((r) => setEmployees(r.data))
        .catch(() => {});
    }
  }, [can]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await api.post("/hr/leaves", {
        employee_id: Number(employeeId || selfOdooId),
        holiday_status_id: Number(typeId),
        date_from: from,
        date_to: to,
        name: reason || undefined,
      });
      onDone(t("leave.created"));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  return (
    <form onSubmit={submit} className="bg-white border border-slate-200 rounded-xl p-5 mb-5 space-y-4">
      {error && <ErrorBox message={error} />}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {can("hr.view_all") && (
          <div>
            <label className="block text-sm font-medium mb-1">{t("hr.employee")} *</label>
            <select
              required
              value={employeeId}
              onChange={(e) => setEmployeeId(e.target.value)}
              className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm bg-white"
            >
              <option value="">—</option>
              {employees.map((emp) => (
                <option key={emp.odoo_id} value={emp.odoo_id}>
                  {emp.name}
                </option>
              ))}
            </select>
          </div>
        )}
        <div>
          <label className="block text-sm font-medium mb-1">{t("leave.type")} *</label>
          <select
            required
            value={typeId}
            onChange={(e) => setTypeId(e.target.value)}
            className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm bg-white"
          >
            <option value="">—</option>
            {types.map((ty) => (
              <option key={ty.odoo_id} value={ty.odoo_id}>
                {ty.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium mb-1">{t("leave.from")} *</label>
          <input
            type="date"
            required
            value={from}
            onChange={(e) => setFrom(e.target.value)}
            className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="block text-sm font-medium mb-1">{t("leave.to")} *</label>
          <input
            type="date"
            required
            value={to}
            onChange={(e) => setTo(e.target.value)}
            className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm"
          />
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium mb-1">{t("leave.reason")}</label>
        <textarea
          rows={2}
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          className="w-full border border-slate-200 rounded-md px-3 py-2 text-sm"
        />
      </div>

      <button
        disabled={busy}
        className="h-9 px-5 rounded-md bg-brand-700 hover:bg-brand-800 disabled:opacity-60 text-white text-sm font-medium"
      >
        {t("common.submit")}
      </button>
    </form>
  );
}
