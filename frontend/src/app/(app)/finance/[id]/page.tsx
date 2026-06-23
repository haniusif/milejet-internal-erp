"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { FinanceInvoice } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { Badge, ErrorBox, money, PageHeader, Spinner } from "@/components/ui";

export default function InvoiceDetail() {
  const { t } = useI18n();
  const { id } = useParams<{ id: string }>();
  const [inv, setInv] = useState<FinanceInvoice | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .get<{ data: FinanceInvoice }>(`/finance/invoices/${id}`)
      .then((r) => setInv(r.data))
      .catch((e) => setError(e instanceof ApiError ? e.message : t("common.error")));
  }, [id, t]);

  if (error) return <ErrorBox message={error} />;
  if (!inv) return <Spinner />;

  const rows: [string, React.ReactNode][] = [
    [t("fin.partner"), inv.partner_name],
    [t("fin.ref"), inv.ref],
    [t("fin.journal"), inv.journal],
    [t("common.date"), inv.invoice_date],
    [t("fin.due_date"), inv.invoice_date_due],
    [t("common.amount"), `${money(inv.amount_total)} ${inv.currency}`],
    [t("fin.residual"), `${money(inv.amount_residual)} ${inv.currency}`],
  ];

  return (
    <div>
      <PageHeader
        kicker={inv.move_type === "out_invoice" ? t("fin.type.invoice") : t("fin.type.bill")}
        title={inv.name}
      >
        <Badge tone={inv.payment_state === "paid" ? "green" : inv.payment_state === "partial" ? "amber" : "rose"}>
          {inv.payment_state === "paid" ? t("fin.paid") : inv.payment_state === "partial" ? t("fin.partial") : t("fin.not_paid")}
        </Badge>
      </PageHeader>

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 max-w-xl">
        <dl className="space-y-4 text-sm">
          {rows.map(([label, value]) => (
            <div key={label} className="flex justify-between gap-4">
              <dt className="text-slate-400">{label}</dt>
              <dd className="text-slate-900 dark:text-slate-100 font-medium tabular-nums text-end">{value ?? "—"}</dd>
            </div>
          ))}
        </dl>
      </div>
    </div>
  );
}
