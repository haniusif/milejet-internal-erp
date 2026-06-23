"use client";

// Authenticated temporary file upload. Files go to the server's private
// storage/app/temp directory; the response shows where they landed.

import { useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useI18n } from "@/lib/i18n";
import { ErrorBox, PageHeader, SuccessBox } from "@/components/ui";

interface UploadResult {
  path: string;
  original_name: string;
  size: number;
  mime: string;
  uploaded_at: string;
  full_path: string;
}

function humanSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function UploadFilePage() {
  const { t } = useI18n();
  const [file, setFile] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<UploadResult | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!file) return;
    setBusy(true);
    setError(null);
    setResult(null);
    try {
      const fd = new FormData();
      fd.append("file", file);
      const r = await api.post<{ data: UploadResult }>("/uploadfile", fd);
      setResult(r.data);
      setFile(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="max-w-2xl">
      <PageHeader kicker={t("nav.tools")} title={t("upload.title")} />

      {error && <ErrorBox message={error} />}
      {result && <SuccessBox message={t("upload.done")} />}

      <form
        onSubmit={submit}
        className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 space-y-4"
      >
        <input
          type="file"
          onChange={(e) => setFile(e.target.files?.[0] ?? null)}
          className="block w-full text-sm text-slate-600 dark:text-slate-300 file:me-3 file:h-9 file:px-4 file:rounded-md file:border file:border-slate-200 dark:file:border-slate-700 file:bg-slate-50 dark:file:bg-slate-800 file:text-sm file:font-medium"
        />
        <p className="text-[11px] text-slate-400">{t("upload.hint")}</p>
        <button
          type="submit"
          disabled={!file || busy}
          className="h-9 px-5 rounded-md bg-brand-700 hover:bg-brand-800 disabled:opacity-50 text-white text-sm font-medium"
        >
          {busy ? "…" : t("upload.button")}
        </button>
      </form>

      {result && (
        <div className="mt-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-5 text-sm space-y-2">
          {(
            [
              [t("upload.name"), result.original_name],
              [t("upload.size"), humanSize(result.size)],
              [t("upload.type"), result.mime],
              [t("upload.path"), result.path],
              [t("upload.full_path"), result.full_path],
            ] as [string, string][]
          ).map(([label, value]) => (
            <div key={label} className="flex flex-wrap gap-2">
              <span className="text-slate-400 w-28 shrink-0">{label}</span>
              <span className="font-mono text-xs text-slate-700 dark:text-slate-200 break-all">{value}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
