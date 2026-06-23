"use client";

import EmployeeForm from "@/components/EmployeeForm";
import { useI18n } from "@/lib/i18n";
import { PageHeader } from "@/components/ui";

export default function NewEmployeePage() {
  const { t } = useI18n();
  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={t("hr.new_employee")} />
      <EmployeeForm />
    </div>
  );
}
