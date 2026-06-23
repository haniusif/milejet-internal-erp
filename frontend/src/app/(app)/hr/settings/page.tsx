"use client";

// Company settings hub — merges the org-structure admin (departments,
// companies & branches, offices/locations) with the HR Configuration page
// (penalties / deductions / rewards / salary rules). One place for everything
// under "إعدادات الشركة".

import Link from "next/link";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { PageHeader } from "@/components/ui";

interface Tile {
  href: string;
  title: string;
  desc: string;
  show: boolean;
}

export default function HrSettings() {
  const { t } = useI18n();
  const { can } = useAuth();

  const tiles: Tile[] = [
    {
      href: "/hr/settings/configuration",
      title: t("settings.configuration"),
      desc: t("settings.configuration_desc"),
      show: can("hr.view_all"),
    },
    {
      href: "/hr/departments",
      title: t("nav.departments"),
      desc: t("settings.departments_desc"),
      show: true,
    },
    {
      href: "/hr/companies",
      title: t("nav.companies"),
      desc: t("settings.companies_desc"),
      show: true,
    },
    {
      href: "/hr/offices",
      title: t("nav.offices"),
      desc: t("settings.offices_desc"),
      show: true,
    },
  ];

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("nav.settings")} />
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        {tiles
          .filter((tile) => tile.show)
          .map((tile) => (
            <Link
              key={tile.href}
              href={tile.href}
              className="rounded-xl border border-slate-200 dark:border-slate-800 p-5 transition hover:ring-1 hover:ring-brand-300 hover:border-brand-300 dark:hover:ring-brand-700"
            >
              <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">{tile.title}</h2>
              <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">{tile.desc}</p>
            </Link>
          ))}
      </div>
    </div>
  );
}
