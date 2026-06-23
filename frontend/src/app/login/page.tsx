"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { ErrorBox } from "@/components/ui";

export default function LoginPage() {
  const { user, loading, login } = useAuth();
  const { t, locale, setLocale } = useI18n();
  const router = useRouter();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!loading && user) router.replace("/hr");
  }, [loading, user, router]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      await login(email, password);
      router.replace("/hr");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  return (
    <div className="min-h-screen grid place-items-center bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 px-4">
      <div className="w-full max-w-sm">
        <div className="bg-white rounded-2xl shadow-soft p-8">
          <div className="flex items-center justify-between mb-6">
            <Image src="/milejet-logo.png" alt="MileJet" width={120} height={40} className="h-10 w-auto" />
            <button
              type="button"
              onClick={() => setLocale(locale === "ar" ? "en" : "ar")}
              className="h-8 px-2.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200"
            >
              {locale === "ar" ? "EN" : "AR"}
            </button>
          </div>

          <h1 className="text-xl font-bold text-slate-900 mb-4">{t("auth.sign_in")}</h1>

          {error && <ErrorBox message={error} />}

          <form onSubmit={submit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1 text-slate-700">
                {t("auth.email")}
              </label>
              <input
                type="email"
                required
                autoFocus
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1 text-slate-700">
                {t("auth.password")}
              </label>
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full border border-slate-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
              />
            </div>
            <button
              type="submit"
              disabled={busy}
              className="w-full h-10 rounded-md bg-accent-500 hover:bg-accent-600 disabled:opacity-60 text-white font-semibold text-sm transition"
            >
              {busy ? t("auth.signing_in") : t("auth.sign_in")}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
