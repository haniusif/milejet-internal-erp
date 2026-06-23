"use client";

// Shared create/edit employee form (employees.write), as a multi-step wizard:
// Basic → Personal/IDs → Employment → Salary → Contract (create only).
// All steps share one state object; Next submits the visible step so the
// browser's required-field validation runs per step. Save happens on the last.

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { api, ApiError } from "@/lib/api";
import type { Department, EmployeeDetail } from "@/lib/types";
import { useI18n } from "@/lib/i18n";
import { ErrorBox } from "@/components/ui";
import { Field, FormCard, Select, SubmitButton, TextInput } from "@/components/form";

const FAMILY_STATUSES = ["S", "M", "W", "D", "C"] as const;

// Stored as stable keys; rendered via hr.contract_type_* messages.
const CONTRACT_TYPES = ["fixed_term", "open_ended"] as const;

// Fixed-term durations in months; rendered via hr.duration_* messages.
const CONTRACT_DURATIONS = [1, 6, 12, 24] as const;

// Stored as stable keys; rendered via hr.work_schedule_* messages.
const WORK_SCHEDULES = ["full_time", "part_time", "shifts", "remote"] as const;

/** Contract end for a joining date: + duration months − 1 day. */
function contractEndFor(joining: string, months: number): string {
  const d = new Date(joining + "T00:00:00Z");
  if (isNaN(d.getTime())) return "";
  d.setUTCMonth(d.getUTCMonth() + months);
  d.setUTCDate(d.getUTCDate() - 1);
  return d.toISOString().slice(0, 10);
}

const SALARY_FIELDS = [
  "total_salary",
  "basic_salary",
  "allowance_house",
  "allowance_rent",
  "allowance_transport",
  "allowance_car",
  "allowance_special",
  "allowance_project",
  "allowance_food",
  "allowance_other",
  "ot_allowance",
  "gosi_pm",
] as const;

// Fixed allowances summed as "other allowances" by the payroll calculator
// (housing & transport are the %-based ones it derives).
const OTHER_ALLOWANCE_FIELDS = [
  "allowance_rent",
  "allowance_car",
  "allowance_special",
  "allowance_project",
  "allowance_food",
  "allowance_other",
  "ot_allowance",
] as const;

const TEXT_FIELDS = [
  "work_email",
  "work_phone",
  "mobile_phone",
  "job_title",
  "emp_code",
  "birthday",
  "family_status",
  "nationality_code",
  "nationality",
  "region",
  "iqama_id",
  "iqama_expiry_date",
  "passport_id",
  "passport_expiry_date",
  "license_expiry_date",
  "cchi_card_type",
  "date_of_joining",
  "contract_type",
  "contract_end_date",
  "work_schedule",
] as const;

function StepGrid({ children }: { children: React.ReactNode }) {
  return <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">{children}</div>;
}

function Stepper({
  steps,
  current,
  onJump,
}: {
  steps: string[];
  current: number;
  onJump: (i: number) => void;
}) {
  return (
    <ol className="flex items-center gap-2 mb-2 overflow-x-auto">
      {steps.map((title, i) => {
        const done = i < current;
        const active = i === current;
        return (
          <li key={title} className="flex items-center gap-2 shrink-0">
            {i > 0 && (
              <span
                className={`h-px w-6 sm:w-10 ${done || active ? "bg-brand-600" : "bg-slate-200 dark:bg-slate-700"}`}
              />
            )}
            <button
              type="button"
              onClick={() => i < current && onJump(i)}
              className={`flex items-center gap-2 ${i < current ? "cursor-pointer" : "cursor-default"}`}
            >
              <span
                className={`flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold ${
                  done
                    ? "bg-brand-600 text-white"
                    : active
                      ? "bg-brand-50 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300 ring-2 ring-brand-600"
                      : "bg-slate-100 dark:bg-slate-800 text-slate-400"
                }`}
              >
                {done ? "✓" : i + 1}
              </span>
              <span
                className={`text-sm hidden sm:inline ${
                  active
                    ? "font-semibold text-slate-900 dark:text-slate-100"
                    : "text-slate-500 dark:text-slate-400"
                }`}
              >
                {title}
              </span>
            </button>
          </li>
        );
      })}
    </ol>
  );
}

export default function EmployeeForm({ employee }: { employee?: EmployeeDetail }) {
  const { t } = useI18n();
  const router = useRouter();

  const [departments, setDepartments] = useState<Department[]>([]);
  const [managers, setManagers] = useState<{ odoo_id: number; name: string }[]>([]);
  const [locations, setLocations] = useState<{ odoo_id: number; name: string }[]>([]);
  const [countries, setCountries] = useState<{ id: number; code: string; name: string }[]>([]);
  const [companies, setCompanies] = useState<{ odoo_id: number; name: string; parent_odoo_id: number | null }[]>([]);

  const s = employee?.sensitive;
  const num = (v: number | null | undefined) => (v === null || v === undefined ? "" : String(v));
  const [form, setForm] = useState({
    name: employee?.name ?? "",
    work_email: employee?.work_email ?? "",
    work_phone: employee?.work_phone ?? "",
    mobile_phone: employee?.mobile_phone ?? "",
    job_title: employee?.job_title ?? "",
    department_id: employee?.department_odoo_id ? String(employee.department_odoo_id) : "",
    company_id: employee?.company_odoo_id ? String(employee.company_odoo_id) : "",
    parent_id: employee?.manager_odoo_id ? String(employee.manager_odoo_id) : "",
    work_location_id: employee?.work_location_odoo_id ? String(employee.work_location_odoo_id) : "",
    // Personal / IDs
    emp_code: employee?.emp_code ?? "",
    birthday: s?.birthday ?? "",
    family_status: s?.family_status ?? "",
    nationality_code: s?.nationality_code ?? "",
    nationality: s?.nationality ?? "",
    region: s?.region ?? "",
    iqama_id: s?.iqama_id ?? "",
    iqama_expiry_date: s?.iqama_expiry_date ?? "",
    passport_id: s?.passport_id ?? "",
    passport_expiry_date: s?.passport_expiry_date ?? "",
    license_expiry_date: s?.license_expiry_date ?? "",
    cchi_card_type: s?.cchi_card_type ?? "",
    // Employment
    date_of_joining: s?.date_of_joining ?? "",
    contract_type: s?.contract_type ?? "",
    contract_end_date: s?.contract_end_date ?? "",
    contract_duration_months: num(s?.contract_duration_months),
    work_schedule: s?.work_schedule ?? "",
    notice_period_days: num(s?.notice_period_days),
    probation_period_days: num(s?.probation_period_days),
    auto_renewal: s?.auto_renewal === true ? "1" : s?.auto_renewal === false ? "0" : "",
    // Salary
    // Profile photo: "" = unchanged; a data-URI = new upload sent to the API.
    image: "",
    total_salary: num(s?.total_salary),
    basic_salary: num(s?.basic_salary),
    allowance_house: num(s?.allowance_house),
    allowance_rent: num(s?.allowance_rent),
    allowance_transport: num(s?.allowance_transport),
    allowance_car: num(s?.allowance_car),
    allowance_special: num(s?.allowance_special),
    allowance_project: num(s?.allowance_project),
    allowance_food: num(s?.allowance_food),
    allowance_other: num(s?.allowance_other),
    ot_allowance: num(s?.ot_allowance),
    gosi_pm: num(s?.gosi_pm),
  });
  // Payroll calculator rates (UI-only; computed amounts land in the fields below).
  // Defaults mirror the Odoo SA-STD structure (parts of 13.5: basic 10, housing 3,
  // transport 0.5) so the stored allowances match what Odoo computes on the payslip:
  // housing = 3/10 = 30% of basic, transport = 0.5/10 = 5% of basic. With no other
  // fixed allowances this yields basic = gross/(1+0.30+0.05) = gross/1.35 (≈74/22/4).
  // GOSI employee share applies to Saudi nationals only — the rate follows nationality.
  const [pct, setPct] = useState(() => ({
    housing: "30",
    transport: "5",
    gosi: s?.nationality_code && s.nationality_code !== "SA" ? "0" : "9.75",
  }));
  const [createContract, setCreateContract] = useState(false);
  const [step, setStep] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const stepTitles = [
    t("hr.section_basic"),
    t("hr.section_personal"),
    t("hr.section_employment"),
    t("hr.section_salary"),
    ...(employee ? [] : [t("hr.section_contract")]),
  ];
  const lastStep = stepTitles.length - 1;

  useEffect(() => {
    api.get<{ data: Department[] }>("/hr/departments").then((r) => setDepartments(r.data)).catch(() => {});
    api
      .get<{ data: { odoo_id: number; name: string }[] }>("/hr/employees?per_page=100")
      .then((r) => setManagers(r.data.filter((m) => m.odoo_id !== employee?.odoo_id)))
      .catch(() => {});
    api
      .get<{ data: { odoo_id: number; name: string }[] }>("/hr/work-locations")
      .then((r) => setLocations(r.data))
      .catch(() => {});
    api
      .get<{ data: { id: number; code: string; name: string }[] }>("/hr/countries")
      .then((r) => setCountries(r.data))
      .catch(() => {});
    api
      .get<{ data: { odoo_id: number; name: string; parent_odoo_id: number | null; active: boolean }[] }>("/hr/companies")
      .then((r) => setCompanies(r.data.filter((c) => c.active)))
      .catch(() => {});
  }, [employee?.odoo_id]);

  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  // Read the chosen photo as a data-URI; the API strips the prefix and stores
  // it as Odoo image_1920. Capped to keep the JSON payload reasonable.
  function onPhoto(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 4 * 1024 * 1024) {
      setError(t("hr.photo_too_large"));
      e.target.value = "";
      return;
    }
    const reader = new FileReader();
    reader.onload = () => setForm((f) => ({ ...f, image: String(reader.result) }));
    reader.readAsDataURL(file);
  }

  // Shown in the picker: the freshly chosen photo, else the employee's current one.
  const photoPreview = form.image || employee?.avatar || null;

  const [scanning, setScanning] = useState(false);

  // OCR auto-fill: read an ID/Iqama photo, ask the API to extract fields, and
  // prefill whichever recognised keys map to this form. Operator confirms.
  function onScanId(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = async () => {
      setScanning(true);
      try {
        const r = await api.post<{
          data: { configured: boolean; fields?: Record<string, string>; message?: string };
        }>("/hr/ocr/extract", { image: String(reader.result), kind: "iqama" });
        if (!r.data.configured) {
          window.alert(r.data.message ?? t("ocr.not_configured"));
          return;
        }
        const fields = r.data.fields ?? {};
        const apply = Object.entries(fields).filter(([k]) => k in form);
        if (apply.length === 0) {
          window.alert(t("ocr.nothing"));
          return;
        }
        setForm((prev) => ({ ...prev, ...Object.fromEntries(apply) }));
      } catch {
        window.alert(t("common.error"));
      } finally {
        setScanning(false);
        e.target.value = "";
      }
    };
    reader.readAsDataURL(file);
  }

  /** GOSI contributory wage: basic + housing, bounded to the 400–45,000 SAR range. */
  const gosiEligible = (basic: number, housing: number) =>
    basic + housing > 0 ? Math.min(Math.max(basic + housing, 400), 45000) : 0;

  /**
   * Payroll calculator: gross is all-inclusive, so fixed allowances come off
   * first, then basic is reversed out of the housing/transport percentages.
   * GOSI (employee share, Saudis) is computed on the eligible wage.
   */
  function applyPayroll() {
    const gross = Number(form.total_salary);
    if (!gross || gross <= 0) return;
    const h = (Number(pct.housing) || 0) / 100;
    const tr = (Number(pct.transport) || 0) / 100;
    const g = (Number(pct.gosi) || 0) / 100;
    setForm((f) => {
      const other = OTHER_ALLOWANCE_FIELDS.reduce((sum, k) => sum + (Number(f[k]) || 0), 0);
      const basic = (gross - other) / (1 + h + tr);
      if (basic <= 0) return f;
      return {
        ...f,
        basic_salary: basic.toFixed(2),
        allowance_house: (basic * h).toFixed(2),
        allowance_transport: (basic * tr).toFixed(2),
        gosi_pm: (gosiEligible(basic, basic * h) * g).toFixed(2),
      };
    });
  }

  async function save() {
    setBusy(true);
    setError(null);

    const body: Record<string, unknown> = { name: form.name };
    for (const k of TEXT_FIELDS) body[k] = form[k] || undefined;
    for (const k of SALARY_FIELDS) body[k] = form[k] === "" ? undefined : Number(form[k]);
    body.contract_duration_months = form.contract_duration_months ? Number(form.contract_duration_months) : undefined;
    body.notice_period_days = form.notice_period_days === "" ? undefined : Number(form.notice_period_days);
    body.probation_period_days = form.probation_period_days === "" ? undefined : Number(form.probation_period_days);
    body.auto_renewal = form.auto_renewal === "" ? undefined : form.auto_renewal === "1";
    body.department_id = form.department_id ? Number(form.department_id) : undefined;
    body.company_id = form.company_id ? Number(form.company_id) : undefined;
    body.parent_id = form.parent_id ? Number(form.parent_id) : undefined;
    body.work_location_id = form.work_location_id ? Number(form.work_location_id) : undefined;
    // Only send the photo when a new one was picked (unchanged stays "").
    if (form.image) body.image = form.image;

    try {
      if (employee) {
        await api.put(`/hr/employees/${employee.id}`, body);
        router.push(`/hr/employees/${employee.id}`);
      } else {
        body.create_contract = createContract;
        const r = await api.post<{ data: { id: number | null }; warning?: string | null }>(
          "/hr/employees",
          body,
        );
        if (r.warning) window.alert(r.warning);
        router.push(r.data.id ? `/hr/employees/${r.data.id}` : "/hr/employees");
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : t("common.error"));
      setBusy(false);
    }
  }

  // Next is a submit so per-step required fields get native validation.
  function submit(e: React.FormEvent) {
    e.preventDefault();
    if (step < lastStep) {
      setStep(step + 1);
      return;
    }
    void save();
  }

  const salaryInput = (k: (typeof SALARY_FIELDS)[number]) => (
    <TextInput type="number" min="0" step="0.01" value={form[k]} onChange={set(k)} />
  );

  return (
    <>
      {error && <ErrorBox message={error} />}
      <FormCard onSubmit={submit} wide>
        <Stepper steps={stepTitles} current={step} onJump={setStep} />

        {step === 0 && (
          <StepGrid>
            <div className="md:col-span-2 lg:col-span-3 flex items-center gap-4">
              <div className="h-20 w-20 shrink-0 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-800 ring-1 ring-slate-200 dark:ring-slate-700 grid place-items-center">
                {photoPreview ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img src={photoPreview} alt="" className="h-full w-full object-cover" />
                ) : (
                  <span className="text-2xl text-slate-300 dark:text-slate-600">
                    {form.name.charAt(0).toUpperCase() || "?"}
                  </span>
                )}
              </div>
              <div>
                <label className="inline-flex items-center h-9 px-4 rounded-md border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">
                  {t("hr.choose_photo")}
                  <input type="file" accept="image/*" className="hidden" onChange={onPhoto} />
                </label>
                {form.image && (
                  <button
                    type="button"
                    onClick={() => setForm((f) => ({ ...f, image: "" }))}
                    className="ms-2 text-xs text-slate-400 hover:text-rose-600"
                  >
                    {t("common.cancel")}
                  </button>
                )}
                <p className="text-[11px] text-slate-400 mt-1">{t("hr.photo_hint")}</p>
              </div>
            </div>
            <div className="md:col-span-2 lg:col-span-3">
              <Field label={t("common.name")} required>
                <TextInput required value={form.name} onChange={set("name")} />
              </Field>
            </div>
            <Field label={t("auth.email")}>
              <TextInput type="email" value={form.work_email} onChange={set("work_email")} />
            </Field>
            <Field label={t("hr.job_title")}>
              <TextInput value={form.job_title} onChange={set("job_title")} />
            </Field>
            <Field label={t("hr.work_phone")}>
              <TextInput value={form.work_phone} onChange={set("work_phone")} />
            </Field>
            <Field label={t("hr.mobile_phone")}>
              <TextInput value={form.mobile_phone} onChange={set("mobile_phone")} />
            </Field>
            <Field label={t("hr.department")}>
              <Select value={form.department_id} onChange={set("department_id")}>
                <option value="">{t("hr.none")}</option>
                {departments.map((d) => (
                  <option key={d.odoo_id} value={d.odoo_id}>
                    {d.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("hr.manager")}>
              <Select value={form.parent_id} onChange={set("parent_id")}>
                <option value="">{t("hr.no_manager")}</option>
                {managers.map((m) => (
                  <option key={m.odoo_id} value={m.odoo_id}>
                    {m.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("hr.work_location")}>
              <Select value={form.work_location_id} onChange={set("work_location_id")}>
                <option value="">{t("hr.none")}</option>
                {locations.map((l) => (
                  <option key={l.odoo_id} value={l.odoo_id}>
                    {l.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("co.company_branch")}>
              <Select value={form.company_id} onChange={set("company_id")}>
                <option value="">{t("hr.none")}</option>
                {companies.map((c) => (
                  <option key={c.odoo_id} value={c.odoo_id}>
                    {c.parent_odoo_id ? "— " : ""}
                    {c.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("hr.emp_code")}>
              {/* Auto-assigned on create (next number in the series) — editable later */}
              <TextInput
                value={form.emp_code}
                onChange={set("emp_code")}
                disabled={!employee}
                placeholder={employee ? undefined : t("hr.emp_code_auto")}
              />
            </Field>
          </StepGrid>
        )}

        {step === 1 && (
          <StepGrid>
            <div className="md:col-span-2 lg:col-span-3 rounded-lg border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40 p-3 flex items-center justify-between gap-3">
              <div>
                <p className="text-sm font-medium text-slate-700 dark:text-slate-200">{t("ocr.scan_id")}</p>
                <p className="text-[11px] text-slate-400">{t("ocr.hint")}</p>
              </div>
              <label className="inline-flex items-center h-9 px-4 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-medium cursor-pointer whitespace-nowrap">
                {scanning ? "…" : t("ocr.scan_btn")}
                <input type="file" accept="image/*" className="hidden" onChange={onScanId} disabled={scanning} />
              </label>
            </div>
            <Field label={t("hr.birthday")}>
              <TextInput type="date" value={form.birthday} onChange={set("birthday")} />
            </Field>
            <Field label={t("hr.family_status")}>
              <Select value={form.family_status} onChange={set("family_status")}>
                <option value="">{t("hr.none")}</option>
                {FAMILY_STATUSES.map((c) => (
                  <option key={c} value={c}>
                    {t(`hr.family_status_${c}`)}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("hr.nationality")}>
              <Select
                value={form.nationality_code}
                onChange={(e) => {
                  const code = e.target.value;
                  const country = countries.find((c) => c.code === code);
                  setForm((f) => ({ ...f, nationality_code: code, nationality: country?.name ?? "" }));
                  // GOSI employee share: Saudis 9.75%, non-Saudis none.
                  if (code) setPct((p) => ({ ...p, gosi: code === "SA" ? "9.75" : "0" }));
                }}
              >
                <option value="">{t("hr.none")}</option>
                {/* legacy rows may hold a name with no matching ISO code — keep it selectable */}
                {form.nationality && !countries.some((c) => c.code === form.nationality_code) && (
                  <option value={form.nationality_code}>{form.nationality}</option>
                )}
                {countries.map((c) => (
                  <option key={c.id} value={c.code}>
                    {c.name} ({c.code})
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("hr.region")}>
              <TextInput value={form.region} onChange={set("region")} />
            </Field>
            <Field label={t("hr.iqama")}>
              <TextInput value={form.iqama_id} onChange={set("iqama_id")} />
            </Field>
            <Field label={t("hr.iqama_expiry")}>
              <TextInput type="date" value={form.iqama_expiry_date} onChange={set("iqama_expiry_date")} />
            </Field>
            <Field label={t("hr.passport")}>
              <TextInput value={form.passport_id} onChange={set("passport_id")} />
            </Field>
            <Field label={t("hr.passport_expiry")}>
              <TextInput type="date" value={form.passport_expiry_date} onChange={set("passport_expiry_date")} />
            </Field>
            <Field label={t("hr.license_expiry")}>
              <TextInput type="date" value={form.license_expiry_date} onChange={set("license_expiry_date")} />
            </Field>
            <Field label={t("hr.cchi_card_type")}>
              <TextInput value={form.cchi_card_type} onChange={set("cchi_card_type")} />
            </Field>
          </StepGrid>
        )}

        {step === 2 && (
          <StepGrid>
            <Field label={t("hr.date_of_joining")}>
              <TextInput
                type="date"
                value={form.date_of_joining}
                onChange={(e) => {
                  const joining = e.target.value;
                  // Prefill contract end = joining + duration − 1 day (still editable below).
                  setForm((f) => ({
                    ...f,
                    date_of_joining: joining,
                    contract_end_date:
                      joining && f.contract_type !== "open_ended"
                        ? contractEndFor(joining, Number(f.contract_duration_months) || 12)
                        : f.contract_end_date,
                  }));
                }}
              />
            </Field>
            <Field label={t("hr.contract_type")}>
              <Select
                value={form.contract_type}
                onChange={(e) => {
                  const type = e.target.value;
                  setForm((f) => ({
                    ...f,
                    contract_type: type,
                    // Open-ended has no end date / duration / renewal.
                    ...(type === "open_ended"
                      ? { contract_duration_months: "", auto_renewal: "", contract_end_date: "" }
                      : type === "fixed_term" && f.date_of_joining
                        ? {
                            contract_end_date: contractEndFor(
                              f.date_of_joining,
                              Number(f.contract_duration_months) || 12,
                            ),
                          }
                        : {}),
                  }));
                }}
              >
                <option value="">{t("hr.none")}</option>
                {/* legacy rows may hold a free-text value — keep it selectable */}
                {form.contract_type && !CONTRACT_TYPES.includes(form.contract_type as never) && (
                  <option value={form.contract_type}>{form.contract_type}</option>
                )}
                {CONTRACT_TYPES.map((c) => (
                  <option key={c} value={c}>
                    {t(`hr.contract_type_${c}`)}
                  </option>
                ))}
              </Select>
            </Field>
            {form.contract_type === "fixed_term" && (
              <Field label={t("hr.contract_duration")}>
                <Select
                  value={form.contract_duration_months}
                  onChange={(e) => {
                    const months = e.target.value;
                    setForm((f) => ({
                      ...f,
                      contract_duration_months: months,
                      contract_end_date:
                        f.date_of_joining && months
                          ? contractEndFor(f.date_of_joining, Number(months))
                          : f.contract_end_date,
                    }));
                  }}
                >
                  <option value="">{t("hr.none")}</option>
                  {CONTRACT_DURATIONS.map((m) => (
                    <option key={m} value={m}>
                      {t(`hr.duration_${m}`)}
                    </option>
                  ))}
                </Select>
              </Field>
            )}
            {form.contract_type !== "open_ended" && (
              <Field label={t("hr.contract_end_date")}>
                <TextInput type="date" value={form.contract_end_date} onChange={set("contract_end_date")} />
              </Field>
            )}
            {form.contract_type === "fixed_term" && (
              <Field label={t("hr.auto_renewal")}>
                <Select value={form.auto_renewal} onChange={set("auto_renewal")}>
                  <option value="">{t("hr.none")}</option>
                  <option value="1">{t("common.yes")}</option>
                  <option value="0">{t("common.no")}</option>
                </Select>
              </Field>
            )}
            <Field label={t("hr.work_schedule")}>
              <Select value={form.work_schedule} onChange={set("work_schedule")}>
                <option value="">{t("hr.none")}</option>
                {WORK_SCHEDULES.map((w) => (
                  <option key={w} value={w}>
                    {t(`hr.work_schedule_${w}`)}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label={t("hr.notice_period")}>
              <TextInput
                type="number"
                min="0"
                max="365"
                placeholder="60"
                value={form.notice_period_days}
                onChange={set("notice_period_days")}
              />
            </Field>
            <Field label={t("hr.probation_period")}>
              <TextInput
                type="number"
                min="0"
                max="365"
                placeholder="90"
                value={form.probation_period_days}
                onChange={set("probation_period_days")}
              />
            </Field>
          </StepGrid>
        )}

        {step === 3 && (
          <>
            {/* Payroll calculator: gross + rates → basic / housing / transport / GOSI */}
            <div className="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/60 dark:bg-slate-800/40 p-4">
              <p className="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3">
                {t("hr.payroll_calc")}
              </p>
              <div className="grid grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <Field label={t("hr.total_salary")}>
                  <TextInput type="number" min="0" step="0.01" value={form.total_salary} onChange={set("total_salary")} />
                </Field>
                <Field label={t("hr.housing_pct")}>
                  <TextInput
                    type="number" min="0" max="100" step="0.01"
                    value={pct.housing}
                    onChange={(e) => setPct((p) => ({ ...p, housing: e.target.value }))}
                  />
                </Field>
                <Field label={t("hr.transport_pct")}>
                  <TextInput
                    type="number" min="0" max="100" step="0.01"
                    value={pct.transport}
                    onChange={(e) => setPct((p) => ({ ...p, transport: e.target.value }))}
                  />
                </Field>
                <Field label={t("hr.gosi_pct")}>
                  <TextInput
                    type="number" min="0" max="100" step="0.01"
                    value={pct.gosi}
                    onChange={(e) => setPct((p) => ({ ...p, gosi: e.target.value }))}
                  />
                </Field>
                <button
                  type="button"
                  onClick={applyPayroll}
                  disabled={!form.total_salary}
                  className="h-9 px-4 rounded-md bg-brand-700 hover:bg-brand-800 disabled:opacity-50 text-white text-sm font-medium"
                  title={t("hr.payroll_calc_hint")}
                >
                  {t("hr.calculate")}
                </button>
              </div>
              <p className="text-[11px] text-slate-400 mt-2">
                {form.nationality_code === "SA"
                  ? t("hr.gosi_note_saudi")
                  : form.nationality_code
                    ? t("hr.gosi_note_nonsaudi")
                    : t("hr.gosi_note")}
              </p>
            </div>

            <StepGrid>
              <Field label={t("hr.basic_salary")}>{salaryInput("basic_salary")}</Field>
              <Field label={t("hr.allowance_house")}>{salaryInput("allowance_house")}</Field>
              <Field label={t("hr.allowance_transport")}>{salaryInput("allowance_transport")}</Field>
              <Field label={t("hr.allowance_rent")}>{salaryInput("allowance_rent")}</Field>
              <Field label={t("hr.allowance_car")}>{salaryInput("allowance_car")}</Field>
              <Field label={t("hr.allowance_special")}>{salaryInput("allowance_special")}</Field>
              <Field label={t("hr.allowance_project")}>{salaryInput("allowance_project")}</Field>
              <Field label={t("hr.allowance_food")}>{salaryInput("allowance_food")}</Field>
              <Field label={t("hr.allowance_other")}>{salaryInput("allowance_other")}</Field>
              <Field label={t("hr.ot_allowance")}>{salaryInput("ot_allowance")}</Field>
              <Field label={t("hr.gosi")}>{salaryInput("gosi_pm")}</Field>
            </StepGrid>

            {Number(form.total_salary) > 0 &&
              (() => {
                const gross = Number(form.total_salary) || 0;
                const eligible = gosiEligible(Number(form.basic_salary) || 0, Number(form.allowance_house) || 0);
                const employeeGosi = Number(form.gosi_pm) || 0;
                // Employer side: Saudis pension 9% + SANED 0.75% + OH 2%; non-Saudis OH 2% only.
                const employerGosi = eligible * (form.nationality_code === "SA" ? 0.1175 : 0.02);
                const rows = [
                  ["hr.gross_salary", gross, "text-slate-900 dark:text-slate-100"],
                  ["hr.gosi_eligible", eligible, "text-slate-600 dark:text-slate-300"],
                  ["hr.gosi", -employeeGosi, "text-rose-600"],
                  ["hr.net_salary", gross - employeeGosi, "text-emerald-700"],
                  ["hr.employer_gosi", employerGosi, "text-amber-600"],
                  ["hr.employer_cost", gross + employerGosi, "text-brand-700"],
                ] as const;
                return (
                  <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                    {rows.map(([label, value, cls]) => (
                      <div key={label} className="rounded-lg border border-slate-200 dark:border-slate-700 p-3 text-center">
                        <p className="text-xs text-slate-500 mb-1">{t(label)}</p>
                        <p className={`text-lg font-bold tabular-nums ${cls}`}>
                          {value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                        </p>
                      </div>
                    ))}
                  </div>
                );
              })()}
          </>
        )}

        {!employee && step === 4 && (
          <label className="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input
              type="checkbox"
              checked={createContract}
              onChange={(e) => setCreateContract(e.target.checked)}
              className="mt-0.5 rounded border-slate-300 dark:border-slate-700"
            />
            <span>
              {t("hr.create_contract")}
              <span className="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {t("hr.create_contract_hint")}
              </span>
            </span>
          </label>
        )}

        <div className="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
          <button
            type="button"
            onClick={() => setStep(Math.max(0, step - 1))}
            disabled={step === 0 || busy}
            className="h-9 px-4 rounded-md border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50"
          >
            {t("common.prev")}
          </button>
          {step < lastStep ? (
            <button
              type="submit"
              className="h-9 px-5 rounded-md bg-brand-700 hover:bg-brand-800 text-white text-sm font-medium"
            >
              {t("common.next")}
            </button>
          ) : (
            <SubmitButton busy={busy} />
          )}
        </div>
      </FormCard>
    </>
  );
}
