"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { api, qs } from "@/lib/api";
import type { CrmCustomer, Paginated } from "@/lib/types";
import { useAuth } from "@/lib/auth";
import { useI18n } from "@/lib/i18n";
import { Badge, EmptyRow, PageHeader, Pagination, Spinner, Table, Td, Th } from "@/components/ui";

export default function CustomersPage() {
  const { t } = useI18n();
  const { can } = useAuth();
  const [page, setPage] = useState<Paginated<CrmCustomer> | null>(null);
  const [q, setQ] = useState("");
  const [pageNum, setPageNum] = useState(1);

  useEffect(() => {
    api
      .get<Paginated<CrmCustomer>>(`/crm/customers${qs({ q, page: pageNum })}`)
      .then(setPage)
      .catch(() => setPage(null));
  }, [q, pageNum]);

  return (
    <div>
      <PageHeader kicker={t("nav.crm")} title={t("nav.customers")}>
        {can("crm.write") && (
          <a href="/crm/customers/new" className="h-9 px-4 inline-flex items-center rounded-md bg-accent-500 hover:bg-accent-600 text-white text-sm font-medium">
            + {t("crm.new_customer")}
          </a>
        )}
      </PageHeader>

      <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-3 mb-5">
        <input
          value={q}
          onChange={(e) => {
            setQ(e.target.value);
            setPageNum(1);
          }}
          placeholder={t("common.search")}
          className="h-9 px-3 border border-slate-200 rounded-md text-sm w-full md:w-80"
        />
      </div>

      {!page ? (
        <Spinner />
      ) : (
        <>
          <Table
            head={
              <>
                <Th>{t("crm.customer")}</Th>
                <Th>{t("auth.email")}</Th>
                <Th>{t("crm.contact")}</Th>
                <Th>VAT</Th>
                <Th end>{t("crm.pipeline")}</Th>
              </>
            }
          >
            {page.data.length === 0 && <EmptyRow colSpan={5} />}
            {page.data.map((c) => (
              <tr key={c.id} className="hover:bg-slate-50/60 dark:hover:bg-slate-800/40">
                <Td className="font-medium text-slate-900 dark:text-slate-100">
                  <Link href={`/crm/customers/${c.id}`} className="hover:text-brand-600 hover:underline">{c.name}</Link>{" "}
                  {c.is_company && <Badge tone="indigo">Co.</Badge>}
                </Td>
                <Td>{c.email ?? "—"}</Td>
                <Td className="tabular-nums">{c.phone ?? "—"}</Td>
                <Td className="font-mono text-xs">{c.vat ?? "—"}</Td>
                <Td end className="tabular-nums">
                  {c.leads_count}
                </Td>
              </tr>
            ))}
          </Table>
          <Pagination page={page} onPage={setPageNum} />
        </>
      )}
    </div>
  );
}
