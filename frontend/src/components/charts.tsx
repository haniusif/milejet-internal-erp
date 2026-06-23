"use client";

// Tiny dependency-free dashboard charts (CSS bars only — no chart lib,
// matching the project's no-extra-deps rule). All dark-mode & RTL aware.

export interface ChartPoint {
  label: string;
  value: number;
}

export function ChartCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5">
      <h3 className="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-4">{title}</h3>
      {children}
    </div>
  );
}

function Empty() {
  return <p className="text-sm text-slate-400 py-6 text-center">—</p>;
}

/** Horizontal bars — good for category breakdowns (departments, nationalities). */
export function HBarChart({
  data,
  barClass = "bg-brand-600",
  format = (v: number) => String(v),
}: {
  data: ChartPoint[];
  barClass?: string;
  format?: (v: number) => string;
}) {
  if (!data.length) return <Empty />;
  const max = Math.max(...data.map((d) => d.value), 1);
  return (
    <div className="space-y-2.5">
      {data.map((d) => (
        <div key={d.label} className="flex items-center gap-2">
          <span className="w-28 shrink-0 text-xs text-slate-500 dark:text-slate-400 truncate" title={d.label}>
            {d.label}
          </span>
          <div className="flex-1 h-4 rounded bg-slate-100 dark:bg-slate-800 overflow-hidden">
            <div
              className={`h-full rounded ${barClass}`}
              style={{ width: `${Math.max((d.value / max) * 100, 2)}%` }}
            />
          </div>
          <span className="w-14 shrink-0 text-xs font-semibold text-slate-700 dark:text-slate-300 tabular-nums text-end">
            {format(d.value)}
          </span>
        </div>
      ))}
    </div>
  );
}

/** Vertical columns — good for time series (per day / per month). */
export function ColumnChart({
  data,
  barClass = "bg-brand-500",
  format = (v: number) => String(v),
}: {
  data: ChartPoint[];
  barClass?: string;
  format?: (v: number) => string;
}) {
  if (!data.length) return <Empty />;
  const max = Math.max(...data.map((d) => d.value), 1);
  return (
    <div className="flex items-end gap-2 h-36">
      {data.map((d) => (
        <div key={d.label} className="flex-1 flex flex-col items-center justify-end gap-1 min-w-0 h-full">
          <span className="text-[10px] font-semibold text-slate-600 dark:text-slate-300 tabular-nums">
            {d.value ? format(d.value) : ""}
          </span>
          <div
            className={`w-full max-w-10 rounded-t ${d.value ? barClass : "bg-slate-100 dark:bg-slate-800"}`}
            style={{ height: `${Math.max((d.value / max) * 100, 2)}%` }}
            title={`${d.label}: ${format(d.value)}`}
          />
          <span className="text-[10px] text-slate-400 truncate max-w-full" title={d.label}>
            {d.label}
          </span>
        </div>
      ))}
    </div>
  );
}
