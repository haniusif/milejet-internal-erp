"use client";

// Small shared UI kit — keep pages lean.

import { useI18n } from "@/lib/i18n";
import type { Paginated } from "@/lib/types";

export function Spinner() {
  const { t } = useI18n();
  return (
    <div className="flex items-center justify-center gap-2 py-16 text-slate-400 text-sm">
      <span className="inline-block w-4 h-4 border-2 border-slate-300 border-t-brand-600 rounded-full animate-spin" />
      {t("common.loading")}
    </div>
  );
}

export function ErrorBox({ message }: { message: string }) {
  return (
    <div className="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm">
      {message}
    </div>
  );
}

export function SuccessBox({ message }: { message: string }) {
  return (
    <div className="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
      {message}
    </div>
  );
}

export function PageHeader({
  kicker,
  title,
  children,
}: {
  kicker?: string;
  title: string;
  children?: React.ReactNode;
}) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-3 mb-5">
      <div>
        {kicker && <p className="text-xs uppercase tracking-wider text-slate-400">{kicker}</p>}
        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100 mt-1">{title}</h1>
      </div>
      {children && <div className="flex items-center gap-2">{children}</div>}
    </div>
  );
}

/**
 * Excel download for list pages. `path` is the API export endpoint
 * (e.g. /hr/employees/export); `params` should mirror the list's current
 * filters so the file matches what's on screen. Plain <a> — Sanctum's
 * session cookie rides along, and the SPA locale is forwarded so the
 * backend renders headers/labels in the same language.
 */
export function ExportExcelButton({
  path,
  params = {},
}: {
  path: string;
  params?: Record<string, string | number | undefined | null>;
}) {
  const { t, locale } = useI18n();

  const all: Record<string, string | number | undefined | null> = { ...params, lang: locale };
  const pairs = Object.entries(all).filter(([, v]) => v !== undefined && v !== null && v !== "");
  const query = "?" + pairs.map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`).join("&");

  return (
    <a
      href={`/api/v1${path}${query}`}
      className="h-9 px-4 inline-flex items-center gap-2 rounded-md bg-emerald-50 hover:bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 text-sm font-medium"
    >
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
        className="w-4 h-4"
      >
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <polyline points="7 10 12 15 17 10" />
        <line x1="12" y1="15" x2="12" y2="3" />
      </svg>
      {t("common.export_excel")}
    </a>
  );
}

export function StatCard({
  label,
  value,
  tone = "default",
  hint,
}: {
  label: string;
  value: string | number;
  tone?: "default" | "brand" | "green" | "amber" | "rose";
  /** Short explanation shown as a tooltip behind a small ? icon next to the label. */
  hint?: string;
}) {
  const toneClass = {
    default: "text-slate-900 dark:text-slate-100",
    brand: "text-brand-700",
    green: "text-emerald-700",
    amber: "text-amber-600",
    rose: "text-rose-600",
  }[tone];

  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4">
      <p className="text-xs text-slate-500 mb-1 flex items-center gap-1">
        {label}
        {hint && (
          <span
            title={hint}
            aria-label={hint}
            className="grid place-items-center w-3.5 h-3.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 text-[9px] font-semibold cursor-help select-none"
          >
            ?
          </span>
        )}
      </p>
      <p className={`text-2xl font-bold tracking-tight tabular-nums ${toneClass}`}>{value}</p>
    </div>
  );
}

const BADGE_TONES: Record<string, string> = {
  green: "bg-emerald-50 text-emerald-700 ring-emerald-200",
  amber: "bg-amber-50 text-amber-700 ring-amber-200",
  rose: "bg-rose-50 text-rose-700 ring-rose-200",
  slate: "bg-slate-100 text-slate-600 ring-slate-200",
  brand: "bg-brand-50 text-brand-700 ring-brand-200",
  indigo: "bg-indigo-50 text-indigo-700 ring-indigo-200",
};

export function Badge({ tone = "slate", children }: { tone?: string; children: React.ReactNode }) {
  return (
    <span
      className={`inline-flex items-center text-[11px] font-medium px-2 py-0.5 rounded-full ring-1 ${
        BADGE_TONES[tone] ?? BADGE_TONES.slate
      }`}
    >
      {children}
    </span>
  );
}

export function leaveStateTone(state: string): string {
  return { draft: "slate", confirm: "amber", validate: "green", refuse: "rose", cancel: "slate" }[state] ?? "slate";
}

export function payslipStateTone(state: string): string {
  return { draft: "slate", verify: "amber", done: "green", cancel: "rose" }[state] ?? "slate";
}

export function Pagination<T>({
  page,
  onPage,
}: {
  page: Paginated<T>;
  onPage: (n: number) => void;
}) {
  const { t } = useI18n();
  if (page.last_page <= 1) return null;

  return (
    <div className="flex items-center justify-between mt-4 text-sm">
      <button
        disabled={page.current_page <= 1}
        onClick={() => onPage(page.current_page - 1)}
        className="px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 disabled:opacity-40 hover:bg-slate-50"
      >
        {t("common.prev")}
      </button>
      <span className="text-slate-500">
        {t("common.page_of", { page: page.current_page, last: page.last_page })} · {page.total}
      </span>
      <button
        disabled={page.current_page >= page.last_page}
        onClick={() => onPage(page.current_page + 1)}
        className="px-3 py-1.5 rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 disabled:opacity-40 hover:bg-slate-50"
      >
        {t("common.next")}
      </button>
    </div>
  );
}

export function Th({ children, end }: { children?: React.ReactNode; end?: boolean }) {
  return (
    <th className={`px-4 py-3 font-medium ${end ? "text-end" : "text-start"}`}>{children}</th>
  );
}

export function Td({
  children,
  end,
  className = "",
}: {
  children?: React.ReactNode;
  end?: boolean;
  className?: string;
}) {
  return (
    <td className={`px-4 py-3 ${end ? "text-end" : "text-start"} ${className}`}>{children}</td>
  );
}

export function Table({ head, children }: { head: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="bg-slate-50 dark:bg-slate-800/60 text-[11px] uppercase tracking-wider text-slate-500">
          <tr>{head}</tr>
        </thead>
        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">{children}</tbody>
      </table>
    </div>
  );
}

export function EmptyRow({ colSpan }: { colSpan: number }) {
  const { t } = useI18n();
  return (
    <tr>
      <td colSpan={colSpan} className="px-4 py-12 text-center text-slate-400">
        {t("common.empty")}
      </td>
    </tr>
  );
}

export function money(value: number | string | null | undefined): string {
  const n = Number(value ?? 0);
  return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
