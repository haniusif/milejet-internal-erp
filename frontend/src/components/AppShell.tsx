"use client";

// Authenticated chrome: module switcher + module nav + user menu.
// Mirrors the Blade layout's role logic: module tabs are gated by
// abilities; HR nav collapses to "my stuff" for employee-only users.

import Image from "next/image";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { api, ApiError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { applyTheme, useI18n } from "@/lib/i18n";
import { Spinner } from "@/components/ui";

interface NavItem {
  href: string;
  label: string;
  exact?: boolean;
}

export default function AppShell({ children }: { children: React.ReactNode }) {
  const { user, loading, logout, can } = useAuth();
  const { t, locale, setLocale } = useI18n();
  const pathname = usePathname();
  const router = useRouter();
  const [menuOpen, setMenuOpen] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  async function runSync() {
    setSyncing(true);
    try {
      await api.post("/hr/sync", { model: "all" });
      router.refresh();
      window.location.reload();
    } catch (e) {
      alert(e instanceof ApiError ? e.message : "Sync failed");
    } finally {
      setSyncing(false);
    }
  }

  useEffect(() => {
    if (!loading && !user) router.replace("/login");
  }, [loading, user, router]);

  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) setMenuOpen(false);
    }
    document.addEventListener("click", onClick);
    return () => document.removeEventListener("click", onClick);
  }, []);

  if (loading || !user) {
    return (
      <div className="min-h-screen grid place-items-center">
        <Spinner />
      </div>
    );
  }

  const modules: NavItem[] = [
    { href: "/hr", label: t("nav.hr") },
    ...(can("crm.view") ? [{ href: "/crm", label: t("nav.crm") }] : []),
    ...(can("fleet.view") ? [{ href: "/fleet", label: t("nav.fleet") }] : []),
    ...(can("finance.view") ? [{ href: "/finance", label: t("nav.finance") }] : []),
  ];

  const hrStaff = can("hr.view_all");
  const navByModule: Record<string, NavItem[]> = {
    "/hr": hrStaff
      ? [
          { href: "/hr", label: t("nav.dashboard"), exact: true },
          { href: "/hr/employees", label: t("nav.employees") },
          { href: "/hr/leaves", label: t("nav.leaves") },
          { href: "/hr/attendance", label: t("nav.attendance") },
          ...(can("recruitment.view") ? [{ href: "/hr/recruitment", label: t("nav.recruitment") }] : []),
          ...(can("contracts.view") ? [{ href: "/hr/contracts", label: t("nav.contracts") }] : []),
          ...(can("payslips.view") ? [{ href: "/hr/payslips", label: t("nav.payslips") }] : []),
          ...(can("loans.view") ? [{ href: "/hr/loans", label: t("nav.loans") }] : []),
          // Departments / companies & branches / offices now live under Settings.
          { href: "/hr/settings", label: t("nav.settings") },
        ]
      : [
          {
            href: user.employee ? `/hr/employees/${user.employee.id}` : "/hr",
            label: t("nav.my_profile"),
          },
          { href: "/hr/leaves", label: t("nav.my_leaves") },
          { href: "/hr/attendance", label: t("nav.attendance") },
          { href: "/hr/payslips", label: t("nav.my_payslips") },
          { href: "/hr/loans", label: t("nav.my_loans") },
        ],
    "/crm": [
      { href: "/crm", label: t("nav.pipeline"), exact: true },
      { href: "/crm/customers", label: t("nav.customers") },
    ],
    "/fleet": [
      { href: "/fleet", label: t("nav.vehicles"), exact: true },
      { href: "/fleet/services", label: t("nav.services") },
      { href: "/fleet/inspections", label: t("fleet.inspections") },
      { href: "/fleet/usage", label: t("fleet.usage") },
      { href: "/fleet/help", label: t("fleet.help") },
    ],
    "/finance": [
      { href: "/finance", label: t("nav.invoices"), exact: true },
      { href: "/finance/bills", label: t("nav.bills") },
    ],
  };

  const currentModule =
    Object.keys(navByModule).find((m) => pathname === m || pathname.startsWith(m + "/")) ?? "/hr";
  const nav = navByModule[currentModule];

  const isActive = (item: NavItem) =>
    item.exact ? pathname === item.href : pathname === item.href || pathname.startsWith(item.href + "/");

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-20 bg-white/85 dark:bg-slate-900/85 backdrop-blur border-b border-slate-200 dark:border-slate-800">
        <div className="max-w-7xl mx-auto px-4">
          <div className="flex items-center justify-between h-16 gap-2">
            <div className="flex items-center gap-3 min-w-0">
              <Link href="/hr" className="shrink-0">
                <Image src="/milejet-logo.png" alt="MileJet" width={96} height={32} className="h-8 w-auto" />
              </Link>

              {/* Module switcher */}
              <nav className="flex items-center gap-0.5">
                {modules.map((m) => {
                  const active = currentModule === m.href;
                  return (
                    <Link
                      key={m.href}
                      href={m.href}
                      className={`px-2.5 h-9 inline-flex items-center rounded-md text-sm whitespace-nowrap transition ${
                        active
                          ? "bg-brand-700 text-white font-semibold"
                          : "text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                      }`}
                    >
                      {m.label}
                    </Link>
                  );
                })}
              </nav>
            </div>

            <div className="flex items-center gap-2 shrink-0">
              {can("sync.run") && (
                <button
                  onClick={runSync}
                  disabled={syncing}
                  title={t("common.sync")}
                  className="h-9 px-3 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 disabled:opacity-50"
                >
                  {syncing ? "…" : `⟳ ${t("common.sync")}`}
                </button>
              )}
              <button
                onClick={() => applyTheme(!document.documentElement.classList.contains("dark"))}
                title={t("common.dark")}
                className="h-9 w-9 rounded-md text-sm bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                ◐
              </button>
              <button
                onClick={() => setLocale(locale === "ar" ? "en" : "ar")}
                className="h-9 px-3 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                {locale === "ar" ? "EN" : "AR"}
              </button>

              <div className="relative" ref={menuRef}>
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    setMenuOpen((v) => !v);
                  }}
                  className="grid place-items-center w-9 h-9 rounded-full bg-brand-600 text-white text-xs font-bold"
                  aria-haspopup="true"
                  aria-expanded={menuOpen}
                >
                  {user.name.charAt(0).toUpperCase()}
                </button>
                {menuOpen && (
                  <div className="absolute end-0 mt-2 w-72 rounded-lg bg-white dark:bg-slate-900 shadow-soft ring-1 ring-slate-200 dark:ring-slate-800 z-30">
                    <div className="p-3 border-b border-slate-100">
                      <p className="text-sm font-semibold text-slate-900 dark:text-slate-100">{user.name}</p>
                      <p className="text-xs text-slate-500 truncate">{user.email}</p>
                    </div>
                    {user.roles.length > 0 && (
                      <div className="p-3 border-b border-slate-100">
                        <p className="text-[10px] uppercase tracking-wide text-slate-400 mb-1.5">
                          {t("auth.roles")}
                        </p>
                        <div className="flex flex-wrap gap-1">
                          {user.roles.map((r) => (
                            <span
                              key={r}
                              className="inline-flex items-center text-[11px] px-2 py-0.5 rounded-full ring-1 bg-slate-100 text-slate-600 ring-slate-200"
                            >
                              {r}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                    <div className="p-1">
                      <button
                        onClick={async () => {
                          await logout();
                          router.replace("/login");
                        }}
                        className="w-full text-start px-3 py-2 text-sm rounded text-rose-600 hover:bg-rose-50"
                      >
                        {t("auth.logout")}
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Module nav */}
          <nav className="flex gap-1 pb-3 overflow-x-auto">
            {nav.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className={`inline-flex items-center shrink-0 px-3 h-8 rounded-md text-sm transition ${
                  isActive(item)
                    ? "bg-brand-50 text-brand-700 font-semibold dark:bg-brand-900/40 dark:text-brand-300"
                    : "text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                }`}
              >
                {item.label}
              </Link>
            ))}
          </nav>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 py-6">{children}</main>
    </div>
  );
}
