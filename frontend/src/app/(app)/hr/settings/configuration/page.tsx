"use client";

// HR Configuration — the "Actions" catalog that feeds payroll: penalties,
// deductions, rewards and allowances. Each tab manages one adjustment kind
// (Odoo hr.salary.adjustment via mj_hr_actions); approved records flow into
// the payslip computation automatically.

import { useState } from "react";
import { useI18n } from "@/lib/i18n";
import { PageHeader } from "@/components/ui";
import AdjustmentsManager from "@/components/AdjustmentsManager";
import type { SalaryAdjustment } from "@/lib/types";

const TABS: SalaryAdjustment["kind"][] = ["penalty", "deduction", "reward", "allowance"];

export default function HrConfiguration() {
  const { t } = useI18n();
  const [tab, setTab] = useState<SalaryAdjustment["kind"]>("penalty");

  return (
    <div>
      <PageHeader kicker={t("nav.settings")} title={t("settings.configuration")} />

      <div className="flex gap-1 border-b border-slate-200 dark:border-slate-800 mb-5 overflow-x-auto">
        {TABS.map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={`shrink-0 px-4 h-10 text-sm font-medium border-b-2 -mb-px transition ${
              tab === key
                ? "border-brand-600 text-brand-700 dark:text-brand-300"
                : "border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400"
            }`}
          >
            {t(`adj.kind_plural.${key}`)}
          </button>
        ))}
      </div>

      <AdjustmentsManager kind={tab} />
    </div>
  );
}
