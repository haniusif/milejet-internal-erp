"use client";

// Tiny form kit shared by the create/edit screens.

import { useI18n } from "@/lib/i18n";

export function Field({
  label,
  required,
  children,
}: {
  label: string;
  required?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div>
      <label className="block text-sm font-medium mb-1 text-slate-700 dark:text-slate-300">
        {label} {required && "*"}
      </label>
      {children}
    </div>
  );
}

export const inputCls =
  "w-full border border-slate-200 dark:border-slate-700 rounded-md px-3 py-2 text-sm bg-white dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-brand-500";

export function TextInput(props: React.InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={inputCls} />;
}

export function Select(props: React.SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={inputCls} />;
}

export function TextArea(props: React.TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea {...props} className={inputCls} />;
}

export function SubmitButton({ busy, label }: { busy: boolean; label?: string }) {
  const { t } = useI18n();
  return (
    <button
      type="submit"
      disabled={busy}
      className="h-9 px-5 rounded-md bg-brand-700 hover:bg-brand-800 disabled:opacity-60 text-white text-sm font-medium"
    >
      {label ?? t("common.save")}
    </button>
  );
}

export function FormCard({
  onSubmit,
  wide,
  children,
}: {
  onSubmit: (e: React.FormEvent) => void;
  /** Span the full content width instead of the default max-w-2xl column. */
  wide?: boolean;
  children: React.ReactNode;
}) {
  return (
    <form
      onSubmit={onSubmit}
      className={`bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-4 ${
        wide ? "w-full" : "max-w-2xl"
      }`}
    >
      {children}
    </form>
  );
}
