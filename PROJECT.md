# MileJet HR System — Project Reference

**Stack:** Laravel 13 + Odoo 17 Community + OCA Payroll
**Working dir:** `/Users/haniyousif/dev/milejet-space/milejet-new-hr/hr-system`
**Local URL:** http://127.0.0.1:8001 (run with PHP 8.4: `/opt/homebrew/opt/php@8.4/bin/php artisan serve`)
**Database:** MySQL `milejet_controll` (local cache; Odoo is source of truth)
**Mobile backend:** Sanctum-authenticated API under `/api/mobile/` (consumed by the Flutter `hr-mobile` app)
**Snapshot date:** 2026-06-01

---

## 1. Architecture

```
              [ User (Browser) ]
                     │
                     ▼
            Laravel UI (Arabic RTL)
            http://127.0.0.1:8001
                     │
        ┌────────────┼──────────────┐
        ▼            │              ▼
  MySQL DB           │      Odoo 17 (XML-RPC)
  (read cache)       │      erp.milejet.space
                     │              │
                     └── write ─────┘
                       (every CRUD)
```

- **Read path:** Laravel UI reads from the local MySQL cache (fast).
- **Write path:** Every create/update/delete goes to Odoo first; on success, the local SQLite record is refreshed.
- **Sync:** Either manually via `php artisan odoo:sync` or by clicking the 🔄 button in the navbar.
- **Auth:** Login validates credentials against Odoo's `res.users`. Laravel stores the user's Odoo email + encrypted API key.

---

## 2. Odoo configuration

| Setting | Value |
|---|---|
| Server | `https://erp.milejet.space` |
| Version | Odoo **17.0 Community Edition** |
| Database | `milejet` |
| Company | MileJet (id=1) |
| Working calendar | Standard 40 hours/week (id=1) |
| Admin user (this app) | `haniusif@gmail.com` |

### Installed addons (HR-related)

Core Odoo modules: `hr_attendance`, `hr_contract`, `hr_expense`, `hr_fleet`, `hr_holidays`, `hr_org_chart`, `hr_recruitment`, `hr_skills`, …

Custom addons (from OCA):

| Addon | State | Path |
|---|---|---|
| `payroll` | **installed** | `/opt/odoo/custom-addons/payroll/payroll` |
| `payroll_account` | not installed | available |
| `payroll_contract_advantages` | not installed | available |
| `payroll_hr_public_holidays` | not installed | available |
| `hr_payroll_document` | not installed | available |

Odoo conf path: `/etc/odoo/odoo.conf`
Service: `systemctl restart odoo`
Python venv: `/opt/odoo/venv/`

---

## 3. Data state

### Counts (verified 2026-05-15)

| Object | Odoo | Laravel local |
|---|---:|---:|
| Employees (`hr.employee`) | 36 | 36 |
| Departments (`hr.department`) | 20 | 20 |
| Contracts (`hr.contract`) | 34 | 34 |
| Leave types (`hr.leave.type`) | 4 | 4 |
| Leaves (`hr.leave`) | 0 | 0 |
| Attendances (`hr.attendance`) | 1 | 1 |
| Payslips (`hr.payslip`) | 2 | 2 |
| Payslip lines (`hr.payslip.line`) | — | 22 |
| Salary rule categories | 6 | — |
| Salary rules | 9 | — |
| Payroll structures | 1 | — |

### Imported data (from `public/Master Sheet - HR-2026.xlsx`)

**34 employees** (codes MJ-001 to MJ-034) imported into `hr.employee` with:

- `name`, `job_title`, `identification_id` (MJ-NNN), `birthday` (DOB)
- `parent_id` (manager chain) — 33 of 34 linked (KHALID FOUAD is top of org)
- `country_id` — 33 of 34 backfilled from Excel `Nat` column

**34 contracts** (one per employee) with:

- `wage` = Total Salary from Excel
- `date_start` = DOJ, `date_end` = contract end date
- `state` = `open`
- `struct_id` = SA-STD (Saudi Monthly Salary)

### Nationality distribution

| Excel value | ISO | Count |
|---|---|---:|
| Sudanese | SD | 14 |
| Pakistani | PK | 6 |
| Yemeni | YE | 4 |
| Bangladeshi | BD | 4 |
| Jordanian | JO | 2 |
| Myanmar | MM | 2 |
| Egyptian | EG | 1 |
| Chadian | TD | 1 |
| **Total** | | **34** |

No Saudi nationals — by current rules, `GOSI_EE` deduction never fires.

---

## 4. Payroll configuration

### Structure
- **Name:** Saudi Monthly Salary
- **Code:** SA-STD
- **Company:** MileJet
- **Rule count:** 9

### Salary rules

| Code | Name | Category | When | Formula |
|---|---|---|---|---|
| `BASIC` | Basic Salary | BASIC | always | `contract.wage * 10/13.5` |
| `HOUSING` | Housing Allowance | ALW | always | `contract.wage * 3/13.5` |
| `TRANSPORT` | Transport Allowance | ALW | always | `contract.wage * 0.5/13.5` |
| `GROSS` | Gross Salary | GROSS | always | `categories.BASIC + categories.ALW` |
| `GOSI_EE` | GOSI — Employee (10%) | DED | **Saudi only** | `-(categories.BASIC + categories.ALW) * 0.10` |
| `NET` | Net Salary | NET | always | `categories.GROSS + categories.DED` |
| `GOSI_ER_SA` | GOSI — Employer (12%) | COMP | **Saudi only** | `min(wage*13/13.5, 45000) * 0.12` |
| `GOSI_ER_FOREIGN` | GOSI — Employer (2%) | COMP | **non-Saudi** | `min(wage*13/13.5, 45000) * 0.02` |
| `EOS_ACCRUAL` | End-of-Service Accrual | COMP | always | `(wage*10/13.5) / 24` |

### Categories

| Code | Name | Affects NET? |
|---|---|---|
| BASIC | Basic | yes |
| ALW | Allowance | yes |
| GROSS | Gross | sum line |
| DED | Deduction | yes |
| NET | Net | result line |
| COMP | Employer Cost | **no** (employer-side cost only) |

### Nationality gating logic

```python
# Saudi-only rules
result = bool(employee.country_id) and employee.country_id.code == 'SA'

# Non-Saudi-only rules
result = not employee.country_id or employee.country_id.code != 'SA'
```

---

## 5. Verified test computations

### Test 1 — SABRI OMER (Sudanese, wage 13,999.50)

```
BASIC              10,370.00   [Basic]
HOUSING             3,111.00   [Allowance]
TRANSPORT             518.50   [Allowance]
GROSS              13,999.50   [Gross]
NET                13,999.50   [Net]       ← no employee GOSI
─── Employer Cost ────────────────────
GOSI_ER_FOREIGN       269.62   (2%)
EOS_ACCRUAL           432.08
```

### Test 2 — KHALID FOUAD as Saudi (wage 16,000)

```
BASIC              11,851.85   [Basic]
HOUSING             3,555.56   [Allowance]
TRANSPORT             592.59   [Allowance]
GROSS              16,000.00   [Gross]
GOSI_EE            -1,600.00   [Deduction]
NET                14,400.00   [Net]
─── Employer Cost ────────────────────
GOSI_ER_SA          1,848.89   (12%)
EOS_ACCRUAL           493.83
```

Both branches verified — nationality gate works.

---

## 6. Laravel app

### Database tables

```
users              — local mirror of res.users (with encrypted API key)
departments        — local cache of hr.department
employees          — local cache of hr.employee
leave_types        — local cache of hr.leave.type
leaves             — local cache of hr.leave
attendances        — local cache of hr.attendance (+ in_/out_latitude/longitude geofence columns)
contracts          — local cache of hr.contract
payslips           — local cache of hr.payslip (with rolled-up totals)
payslip_lines      — local cache of hr.payslip.line
sync_logs          — every sync operation logged
sessions           — Laravel session storage
```

### Routes

| Method | URL | Action |
|---|---|---|
| GET | `/login` | Show login form |
| POST | `/login` | Authenticate against Odoo `res.users` |
| POST | `/logout` | End session |
| GET | `/` | Dashboard |
| POST | `/sync` | Trigger sync (model param: all/employees/contracts/etc.) |
| GET | `/employees` | List + filter + paginate |
| GET | `/employees/create` | Form |
| POST | `/employees` | Create in Odoo + sync |
| GET | `/employees/{id}/edit` | Edit form |
| PUT | `/employees/{id}` | Update in Odoo + sync |
| DELETE | `/employees/{id}` | Unlink in Odoo + delete locally |
| GET | `/departments` | Same CRUD pattern |
| GET | `/leaves` | List + filter |
| POST | `/leaves` | Create leave request |
| POST | `/leaves/{id}/approve` | `action_confirm` + `action_approve` |
| POST | `/leaves/{id}/refuse` | `action_refuse` |
| GET | `/attendances` | List + filter |
| POST | `/attendances/check-in` | Create `hr.attendance` |
| POST | `/attendances/{id}/check-out` | Write `check_out` |
| GET | `/contracts` | List + filter (read-only) |
| GET | `/payslips` | List + filter + month picker + totals |
| GET | `/payslips/create` | Generate form |
| POST | `/payslips` | Create + `compute_sheet` + sync |
| GET | `/payslips/{id}` | Detail view with lines |
| POST | `/payslips/{id}/compute` | Recompute |
| DELETE | `/payslips/{id}` | Cancel + unlink |

### Controllers

```
app/Http/Controllers/
├── AuthController.php       — login/logout via Odoo
├── DashboardController.php  — stats + sync trigger
├── EmployeeController.php   — CRUD on hr.employee
├── DepartmentController.php — CRUD on hr.department
├── LeaveController.php      — create/approve/refuse leaves
├── AttendanceController.php — check-in / check-out
├── ContractController.php   — read-only list
└── PayslipController.php    — full payslip workflow
```

### Services

```
app/Services/
├── OdooService.php  — XML-RPC client wrapper (singleton, per-user credentials)
└── SyncService.php  — pull each model from Odoo + write into local DB
```

### Models

```
app/Models/
├── User.php          — Authenticatable + Crypt for api_key
├── Employee.php      — relationships to Department, Leave, Attendance, Contract
├── Department.php
├── Leave.php         — stateLabel/stateColor for UI
├── LeaveType.php
├── Attendance.php
├── Contract.php      — stateLabel/stateColor
├── Payslip.php       — relationships + helpers
├── PayslipLine.php   — line-item detail
└── SyncLog.php
```

---

## 6b. Mobile API (Sanctum)

`app/Http/Controllers/MobileApiController.php`, routes in `routes/api.php` under `/api/mobile/`.

- **Auth:** `POST /mobile/login` validates email + password (Odoo password *or* API key) against Odoo
  `res.users` via XML-RPC, links the employee by `hr.employee.user_id = uid`, then returns a Sanctum
  bearer token (30-day expiry). The local `users` row is auto-created on first login (`updateOrCreate`
  on `odoo_uid`).
- **Endpoints:** `me`, `leaves`, `leave-types`, `leaves` (POST), `attendance`, `attendance/config`,
  `attendance/current`, `attendance/check-in`, `attendance/{id}/check-out`, `payslips`, `payslips/{id}`,
  `notifications` (stub), `logout`.

### Geofenced attendance

Check-in/out are restricted to within a configurable radius of the office.

- **Config:** `config/attendance.php` ← `.env` keys `ATTENDANCE_OFFICE_LAT/LNG`,
  `ATTENDANCE_GEOFENCE_RADIUS` (meters, default 300), `ATTENDANCE_GEOFENCE_ENFORCE` (bool).
- `GET /mobile/attendance/config` exposes `{latitude, longitude, radius, enforce}` to the app.
- `checkIn`/`checkOut` accept `latitude`/`longitude`, validate distance with a Haversine helper, reject
  out-of-radius (or missing-when-enforced) punches with **HTTP 422**, write Odoo GPS fields
  (`in_/out_latitude/longitude`) + the local geo columns, and return the coordinates.

### Employee user provisioning

`php artisan odoo:provision-users` creates internal `res.users` (attendance self-service) for
employees with no linked Odoo user and sets `hr.employee.user_id` so they can log in to the mobile app.
Uses `work_email` when present, otherwise builds `{emp_code}@{--domain}` (default `milejet.space`).
Dry-run by default; `--apply` writes to Odoo and exports a credentials CSV to `storage/app/`.
**Run 2026-06-01:** provisioned 45 accounts (password `12345678`), 47 employees now linked; 2 edge
cases skipped (one with no emp_code, one with a stale `odoo_id`).

---

## 7. How to run

### Start the server
```bash
cd /Users/haniyousif/dev/milejet-space/hr/hr-system
php artisan serve --host=127.0.0.1 --port=8001
```

### Sync data manually
```bash
php artisan odoo:sync                # everything
php artisan odoo:sync contracts      # specific model
php artisan odoo:sync payslips
```

### Provision employee login accounts
```bash
php artisan odoo:provision-users               # dry-run preview
php artisan odoo:provision-users --apply        # create + link users, export CSV
```

### Run migrations (fresh database)
```bash
php artisan migrate
```

### Login credentials (admin)
- URL: http://127.0.0.1:8001/login
- Email: `haniusif@gmail.com`
- Password / API key: same as your Odoo account

---

## 8. Environment

`.env` configuration (already populated):

```env
APP_NAME="HR System"
APP_ENV=local
APP_KEY=base64:<auto-generated>
APP_DEBUG=true

DB_CONNECTION=mysql
DB_DATABASE=milejet_controll

ODOO_URL=https://erp.milejet.space
ODOO_DB=milejet
ODOO_USERNAME=haniusif@gmail.com
ODOO_API_KEY=2a52bd350ca92d798d5402e08b3b384f77e544c3
ODOO_VERIFY_SSL=true

# Geofenced attendance — set to your real office coordinates
ATTENDANCE_OFFICE_LAT=24.7136
ATTENDANCE_OFFICE_LNG=46.6753
ATTENDANCE_GEOFENCE_RADIUS=300
ATTENDANCE_GEOFENCE_ENFORCE=true
```

⚠️ The API key is currently in plain text in `.env` — treat it as a secret. `.env` is excluded by Laravel's default `.gitignore`.

Dependencies:
- PHP 8.4.7 (Homebrew)
- Composer
- `phpxmlrpc/phpxmlrpc` ^4.11

---

## 9. Caveats / known limitations

| Area | Limitation |
|---|---|
| Payroll formula | 74/22/4 basic/housing/transport split is a Saudi convention, not your real Excel breakdown. Per-employee allowances (House, Transport, Special, Project, Food etc.) are not used yet — they're in Excel cols 24-31. |
| GOSI base | `GOSI_EE` deducts 10% of full gross. Per Saudi law it should be 10% of (basic + housing) capped at 45,000 SAR. Easy fix — patch the formula. |
| Employer cost in Laravel | Local payslip totals roll up BASIC/ALW/GROSS/DED/NET — but COMP (Employer Cost) is not surfaced in the UI. Migration + view change needed if you want it. |
| Employee login | **Resolved (2026-06-01).** `php artisan odoo:provision-users --apply` created internal `res.users` for 45 employees and linked `hr.employee.user_id`; 47 now log in to the mobile app. Shared password `12345678` should be rotated. 2 employees still need manual fixes (missing emp_code / stale odoo_id). |
| Sync limits | `syncLeaves` pulls last 500. `syncAttendances` pulls last 1000. `syncPayslips` pulls last 500. Adjust in `SyncService.php` if you outgrow these. |
| Soft deletes | If a record is deleted in Odoo, the local sync doesn't remove it. (We saw this with the original Hani Yousif duplicate — cleanup is manual.) |
| OCA payroll docs gap | OCA payroll uses `condition_python` / `amount_python_compute` differently from Odoo Enterprise payroll. Examples in this codebase work; some online tutorials targeting Enterprise won't. |

---

## 10. Roadmap / outstanding decisions

These were proposed but not implemented:

1. ~~**Employee login**~~ — **done** via option (C): `odoo:provision-users` creates one `res.users`
   per employee. Follow-ups: rotate the shared `12345678` password (or force reset on first login),
   and fix the 2 skipped employees.
2. **Per-employee allowances** — install OCA `payroll_contract_advantages` OR use `hr.payslip.input` for monthly variable inputs
3. **Tighten GOSI_EE formula** to (basic + housing) only, capped at 45,000
4. **Surface employer cost (COMP)** in Laravel payslip views
5. **Mobile experience** — make Laravel app fully responsive + register as PWA so employees can install from home screen
6. **OCA add-ons** to consider installing:
   - `payroll_account` (journal entries from payslips)
   - `hr_payroll_document` (PDF payslip generation)
   - `payroll_contract_advantages` (per-contract allowance fields)

---

## 11. Quick references

### Useful tinker queries

```php
// Verify Odoo connection
$odoo = app(App\Services\OdooService::class);
$odoo->searchCount('hr.employee', []);

// Get an employee
App\Models\Employee::where('name', 'SABRI OMER')->first();

// Recompute a payslip
$odoo->executeKw('hr.payslip', 'compute_sheet', [[1]]);

// Patch a salary rule
$odoo->write('hr.salary.rule',
    $odoo->search('hr.salary.rule', [['code','=','GOSI_EE']]),
    ['amount_python_compute' => '...']);
```

### Adding a new HR model (sync pattern)

1. Migration: add table with `odoo_id` unique + your fields + `synced_at`.
2. Model: `protected $guarded = ['id'];` + relationships.
3. `SyncService::syncFoo()`: read from Odoo, `updateOrCreate` keyed on `odoo_id`.
4. Wire into `syncAll()`, `SyncOdooCommand`, and `DashboardController::sync`.
5. Optional: controllers + routes + views.

### Adding a salary rule

```php
$odoo->create('hr.salary.rule', [
    'name' => 'Your Rule',
    'code' => 'YOUR_CODE',
    'category_id' => $catId['ALW'],  // BASIC|ALW|GROSS|DED|NET|COMP
    'sequence' => 50,
    'condition_select' => 'none',     // or 'python'
    'condition_python' => 'result = True',
    'amount_select' => 'code',
    'amount_python_compute' => 'result = contract.wage * 0.05',
    'appears_on_payslip' => true,
    'company_id' => 1,
]);

// Attach to structure (use union, not replace)
$structId = 1; // SA-STD
$current = $odoo->read('hr.payroll.structure', [$structId], ['rule_ids'])[0]['rule_ids'];
$merged = array_values(array_unique(array_merge($current, [$newRuleId])));
$odoo->write('hr.payroll.structure', [$structId], ['rule_ids' => [[6, 0, $merged]]]);
```

---

## 12. Conversation history (this build)

In chronological order:

1. Explored the original `hr-full/` source folder
2. Scaffolded fresh Laravel project as sibling: `hr-system/`
3. Installed `phpxmlrpc`, registered `OdooServiceProvider`, ran migrations
4. Connected to `erp.milejet.space` with user creds + API key
5. First sync: 1 dept / 3 employees / 4 leave types
6. Imported 34 employees from Excel `Master Sheet - HR-2026.xlsx`
7. Discovered Odoo Community has no payroll → installed OCA `payroll` 17.0 on VPS
8. Created 34 `hr.contract` records with wages from Excel
9. Built SA-STD payroll structure + 6 base rules in Odoo
10. Generated test payslip → math verified (BASIC + HOUSING + TRANSPORT = wage; NET = gross - GOSI)
11. Built Laravel-side payroll: migrations, models, sync, controllers, routes, views
12. Verified end-to-end: created SABRI OMER's May 2026 payslip via Laravel UI
13. Added nationality gating to GOSI rules + backfilled 33/34 country_id values
14. Added 3 Saudi-compliant rules: GOSI_ER_SA, GOSI_ER_FOREIGN, EOS_ACCRUAL
15. Verified both nationality branches fire correctly

End of build snapshot.
