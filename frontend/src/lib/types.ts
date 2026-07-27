// API payload shapes — mirror app/Http/Controllers/Api/V1/* summaries.

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  roles: string[];
  abilities: Record<string, boolean>;
  employee: {
    id: number;
    odoo_id: number;
    name: string;
    job_title: string | null;
    department: string | null;
    avatar: string | null;
  } | null;
}

export interface EmployeeSummary {
  id: number;
  odoo_id: number;
  emp_code: string | null;
  name: string;
  job_title: string | null;
  department: string | null;
  department_odoo_id: number | null;
  company: string | null;
  company_odoo_id: number | null;
  manager: string | null;
  manager_odoo_id: number | null;
  work_email: string | null;
  work_phone: string | null;
  mobile_phone: string | null;
  work_location: string | null;
  work_location_odoo_id: number | null;
  contract_status: string | null;
  geofence_exempt: boolean;
  iqama_expiry_date: string | null;
  license_expiry_date: string | null;
  passport_expiry_date: string | null;
  active: boolean;
  avatar: string | null;
}

export interface EmployeeDetail extends EmployeeSummary {
  sensitive?: {
    nationality: string | null;
    nationality_code: string | null;
    iqama_id: string | null;
    iqama_expiry_date: string | null;
    passport_id: string | null;
    passport_expiry_date: string | null;
    license_expiry_date: string | null;
    birthday: string | null;
    family_status: string | null;
    region: string | null;
    cchi_card_type: string | null;
    date_of_joining: string | null;
    contract_type: string | null;
    contract_end_date: string | null;
    contract_duration_months: number | null;
    work_schedule: string | null;
    notice_period_days: number | null;
    probation_period_days: number | null;
    auto_renewal: boolean | null;
    total_salary: number | null;
    basic_salary: number | null;
    allowance_house: number | null;
    allowance_rent: number | null;
    allowance_transport: number | null;
    allowance_car: number | null;
    allowance_special: number | null;
    allowance_project: number | null;
    allowance_food: number | null;
    allowance_other: number | null;
    ot_allowance: number | null;
    gosi_pm: number | null;
  };
  contract?: {
    id: number;
    name: string | null;
    wage: number;
    date_start: string | null;
    date_end: string | null;
    state: string;
    signed?: boolean;
  } | null;
  recent_leaves?: Leave[];
  recent_attendances?: Attendance[];
  recent_payslips?: Payslip[];
  manager_employee?: EmployeeSummary | null;
  reports?: EmployeeSummary[];
}

export interface Leave {
  id: number;
  odoo_id: number;
  employee_name: string;
  leave_type: string | null;
  date_from: string | null;
  date_to: string | null;
  number_of_days: number;
  state: string;
  description: string | null;
}

export interface LeaveType {
  odoo_id: number;
  name: string;
}

export interface Attendance {
  id: number;
  employee_name: string;
  check_in: string | null;
  check_out: string | null;
  worked_hours: number;
}

export interface Payslip {
  id: number;
  number: string | null;
  employee_name: string;
  date_from: string | null;
  date_to: string | null;
  basic_total: number;
  allowance_total: number;
  gross_total: number;
  deduction_total: number;
  net_total: number;
  state: string;
  payment_status?: string;
  amount_paid?: number;
  amount_due?: number;
  lines?: PayslipLine[];
}

export interface PayslipLine {
  code: string;
  name: string;
  category_code: string | null;
  total: number;
}

export interface Department {
  odoo_id: number;
  name: string;
}

export interface CrmStage {
  odoo_id: number;
  name: string;
  sequence: number;
  is_won: boolean;
}

export interface CrmLead {
  id: number;
  odoo_id: number;
  name: string;
  contact_name: string | null;
  partner_name: string | null;
  email_from: string | null;
  phone: string | null;
  expected_revenue: number | null;
  probability: number | null;
  stage_id: number | null;
  stage_name: string | null;
  salesperson: string | null;
  date_deadline: string | null;
  priority: string | null;
  active: boolean;
  created_at: string | null;
}

export interface CrmCustomer {
  id: number;
  odoo_id: number;
  name: string;
  is_company: boolean;
  email: string | null;
  phone: string | null;
  city: string | null;
  country: string | null;
  vat: string | null;
  leads_count: number;
}

export interface CrmActivity {
  odoo_id: number;
  type: string | null;
  summary: string | null;
  note: string | null;
  deadline: string | null;
  user: string | null;
  state: string | null; // overdue | today | planned
}

export interface CrmNote {
  author: string | null;
  date: string | null;
  body: string;
}

export interface CrmLeadDetail extends CrmLead {
  mobile?: string | null;
  description: string | null;
  tag_names: string | null;
  team_name: string | null;
  lost_reason: string | null;
  activities: CrmActivity[];
  notes: CrmNote[];
  customer: { id: number; name: string; account_manager: string | null; credit_limit: number; leads_count: number } | null;
}

export interface CrmConfig {
  salespeople: { id: number; name: string }[];
  tags: { odoo_id: number; name: string; color: number }[];
  lost_reasons: { odoo_id: number; name: string }[];
  stages: { odoo_id: number; name: string; is_won: boolean }[];
}

export interface CrmCustomer360 {
  id: number;
  odoo_id: number;
  name: string;
  is_company: boolean;
  email: string | null;
  phone: string | null;
  mobile: string | null;
  city: string | null;
  country: string | null;
  vat: string | null;
  account_manager: string | null;
  credit_limit: number;
  kpis: { opps: number; invoiced: number; due: number; shipments: number | null };
  opportunities: CrmLead[];
  invoices: { name: string; date: string | null; total: number; residual: number; payment_state: string | null }[];
  payments: { name: string; date: string | null; amount: number }[];
  activities: CrmActivity[];
}

export interface FleetVehicle {
  id: number;
  odoo_id: number;
  name: string;
  model_name: string | null;
  license_plate: string | null;
  driver_name: string | null;
  state_id: number | null;
  state_name: string | null;
  odometer: number;
  odometer_unit: string | null;
  fuel_type: string | null;
  category_name?: string | null;
}

export interface FleetInspectionLine {
  id: number;
  item_name: string | null;
  result: string;
  result_description: string | null;
}

export interface FleetInspection {
  id: number;
  name: string | null;
  direction: string | null;
  state: string;
  result: string | null;
  date_inspected: string | null;
  odometer: number | null;
  inspected_by: string | null;
  note: string | null;
  lines: FleetInspectionLine[];
}

export interface FleetUsage {
  id: number;
  name: string | null;
  partner_name: string | null;
  state: string;
  date_picking: string | null;
  date_return: string | null;
  notes: string | null;
}

export interface FleetService {
  id: number;
  vehicle_name: string;
  service_type: string | null;
  included_services?: string | null;
  description: string | null;
  date: string | null;
  amount: number | null;
  vendor: string | null;
  state: string | null;
}

export interface Contract {
  id: number;
  name: string | null;
  employee_name: string;
  wage: number;
  date_start: string | null;
  date_end: string | null;
  state: string;
  struct_name: string | null;
  signed?: boolean;
}

export interface JobPosition {
  id: number;
  odoo_id: number;
  name: string;
  department: string | null;
  openings: number;
  applicants: number;
  recruiter: string | null;
}

export interface Applicant {
  id: number;
  odoo_id: number;
  name: string;
  email: string | null;
  phone: string | null;
  job_id: number | null;
  job_name: string | null;
  stage_id: number | null;
  stage_name: string | null;
  department: string | null;
  salary_expected: number | null;
  salary_proposed: number | null;
  active: boolean;
  created_at: string | null;
}

export interface RecruitmentStage {
  odoo_id: number;
  name: string;
  sequence: number;
  hired: boolean;
}

export interface DepartmentFull {
  id: number;
  odoo_id: number;
  name: string;
  parent_odoo_id: number | null;
  parent_name: string | null;
  manager_odoo_id: number | null;
  manager_name: string | null;
  total_employee: number | null;
}

export interface CompanyEntry {
  id: number;
  odoo_id: number;
  name: string;
  parent_odoo_id: number | null;
  parent_name: string | null;
  company_registry: string | null;
  vat: string | null;
  phone: string | null;
  email: string | null;
  city: string | null;
  country: string | null;
  active: boolean;
  employees: number;
}

export interface Loan {
  id: number;
  odoo_id: number;
  name: string | null;
  employee_name: string;
  date: string | null;
  amount: number;
  installment: number;
  reason: string | null;
  state: string;
  repaid_amount: number;
  balance: number;
  lines: { id: number; date: string | null; amount: number; note: string | null }[];
}

export interface SalaryAdjustment {
  id: number;
  odoo_id: number;
  name: string | null;
  employee_name: string;
  odoo_employee_id: number;
  kind: "penalty" | "deduction" | "reward" | "allowance";
  date: string | null;
  amount: number;
  reason: string | null;
  state: string;
}

export interface PayslipPayment {
  id: number;
  date: string | null;
  amount: number;
  method: string | null;
  reference: string | null;
  note: string | null;
}

export interface WorkLocationFull {
  id: number;
  odoo_id: number;
  name: string;
  location_type: string;
  address_name: string | null;
  latitude: number | null;
  longitude: number | null;
  geofence_radius: number | null;
  active: boolean;
  employees?: number;
}

export interface OrgEmployee {
  id: number;
  odoo_id: number;
  emp_code: string | null;
  name: string;
  job_title: string | null;
  department: string | null;
  parent_odoo_id: number | null;
  avatar: string | null;
}

export interface FleetVehicleDetail extends FleetVehicle {
  vin_sn: string | null;
  model_year: string | null;
  color: string | null;
  seats: number | null;
  doors: number | null;
  acquisition_date: string | null;
  car_value: number | null;
  fuel_capacity: number | null;
  in_use: boolean;
  services: FleetService[];
  inspections: FleetInspection[];
  usages: FleetUsage[];
  service_types: { odoo_id: number; name: string }[];
  inspection_templates: { odoo_id: number; name: string }[];
  inspection_items: { odoo_id: number; name: string }[];
}

export interface LeaveAttachment {
  id: number;
  name: string;
  mimetype: string;
}

export interface FinanceInvoice {
  id: number;
  odoo_id: number;
  move_type: string;
  name: string;
  ref: string | null;
  partner_name: string | null;
  invoice_date: string | null;
  invoice_date_due: string | null;
  amount_total: number;
  amount_residual: number;
  currency: string;
  state: string;
  payment_state: string | null;
  journal: string | null;
}
