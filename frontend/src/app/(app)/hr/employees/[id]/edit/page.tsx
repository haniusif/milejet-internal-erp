"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { EmployeeDetail } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import EmployeeForm from "@/components/EmployeeForm";
import { ErrorBox, PageHeader, Spinner } from "@/components/ui";

export default function EditEmployeePage() {
  const { t } = useI18n();
  const { id } = useParams<{ id: string }>();
  const [employee, setEmployee] = useState<EmployeeDetail | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: EmployeeDetail }>(`/hr/employees/${id}`)
      .then((r) => setEmployee(r.data))
      .catch((e) => setError(e instanceof ApiError ? e.message : t("common.error")));
  }, [id, t]);

  if (error) return <ErrorBox message={error} />;
  if (!employee) return <Spinner />;

  return (
    <div>
      <PageHeader kicker={t("nav.hr")} title={`${t("hr.edit_employee")} — ${employee.name}`} />
      <EmployeeForm employee={employee} />
    </div>
  );
}
