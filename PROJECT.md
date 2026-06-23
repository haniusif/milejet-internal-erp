# MileJet Internal ERP — Project Reference & Status

**Stack:** Laravel 13 (PHP 8.4) + Next.js 16 SPA (React 19, Tailwind 4) + Odoo 17 Community + OCA Payroll
**Working dir (production):** `/var/www/milejet-internal-erp` — ⚠️ **this IS the live prod box**, deployed straight from the working tree
**Database:** MySQL `milejet_controll` (local cache; **Odoo is the source of truth**)
**Repo:** `github.com/haniusif/milejet-internal-erp` (Flutter mobile app lives at `github.com/haniusif/milejet-mobile`)
**Snapshot date:** 2026-06-05

---

## 1. Architecture (current)

```
                        [ Users (Browser) ]
                                │
              https://portal.milejet.space  ←── THE app (SPA)
                                │
            ┌───────────────────┴────────────────────┐
            ▼ /                                      ▼ /api, /sanctum
   Next.js SPA (next start :3000)          Laravel API v1 (php-fpm)
   frontend/ — HR·CRM·Fleet·Finance        /api/v1/* — Sanctum cookies
   AR/EN + RTL · dark mode                          │
                                       ┌────────────┼────────────┐
                                       ▼            │            ▼
                                  MySQL cache       │    Odoo 17 (XML-RPC)
                                  (read path)       │    erp.milejet.space
                                                    └── every write ──┘

   hr/crm/fleet/finance.milejet.space → 301 to the matching SPA section
   (their /api + /sanctum still pass through to Laravel for the mobile app)
```

- **One URL for users:** `portal.milejet.space` serves the SPA; module subdomains redirect into it.
- **Read path:** SPA → Laravel API → MySQL cache (fast). **Write path:** Laravel → Odoo first; local record refreshed on success.
- **Sync:** `php artisan odoo:sync`, the ⟳ button (SPA header, `sync.run` gate), or `POST /api/v1/hr/sync`.
- **Auth:** credentials validated against Odoo `res.users` (shared `OdooAuthService` used by web, SPA API and mobile). Roles mapped from Odoo groups on every login.
- **SSO:** `SESSION_DOMAIN=.milejet.space` + Sanctum stateful cookies — one login covers SPA and any Blade page.
- **Blade UI:** still in the codebase, no longer routed to users (rollback configs exist as `*.blade.bak`). The SPA reached full feature parity 2026-06-05.

### Serving layout

| Host | Serves | nginx config (live name) |
|---|---|---|
| portal.milejet.space | **SPA** + `/api`,`/sanctum` → Laravel | `portal-milejet` (`deploy/nginx/portal.milejet.space`) |
| hr.milejet.space | 301 → portal/hr (+API passthrough) | `milejet-hr` (`deploy/nginx/hr.milejet.space.spa`) |
| crm / fleet / finance | 301 → portal/<module> (+API passthrough) | `<mod>.milejet.space` (`deploy/nginx/<mod>.milejet.space.spa`) |
| erp.milejet.space | **Odoo itself** — never claim this vhost | `odoo` |

SPA process: `milejet-spa.service` (systemd) → `next start -p 3000` as `www-data` in `frontend/`.

---

## 2. Roles & permissions

Roles live as a JSON array on `users.roles`, synced from Odoo `res.groups` at login (`OdooRoleMapper`, "Category / Name" matching). Every user gets `employee`; elevated roles stack on top:

`admin`, `hr_manager`→`hr_officer`, `payroll_manager`→`payroll_officer`, `leave_manager`,
`recruitment_manager`→`recruitment_officer`, `crm_manager`→`crm_user`, `fleet_manager`→`fleet_officer`,
`finance_manager`→`finance_officer`.

Gates defined in `AppServiceProvider` (`employees.write`, `payslips.view`, `hr.view_all`, `crm.view`, …).
**Employee-only users are scoped to their own data** (profile, leaves, attendance, payslips) — enforced
server-side in both web controllers and API v1; the `hr.view_all` gate separates HR staff from plain employees.
The SPA receives precomputed `abilities` from `GET /api/v1/auth/me` to drive nav/buttons.

User↔employee link: `users.odoo_employee_id` (set at login via `hr.employee.user_id`) with a
work-email fallback — `User::employeeRecord()`.

---

## 3. The SPA (`frontend/`)

Next 16 App Router · TypeScript · Tailwind 4 · no extra state libs. 27 routes, all client-rendered
against the API. Custom cookie-based i18n (`mj_locale`, ar/en with full RTL flip) and dark mode
(`mj_theme` cookie + Tailwind `@custom-variant dark`).

| Module | Screens |
|---|---|
| HR | dashboard · employees (list/profile/**create/edit/delete**) · org chart · leaves (request/approve/refuse/delete + 📎 attachments) · attendance (check-in/out/delete) · payslips (list/detail/**bulk create**/recompute/delete) · recruitment (jobs + applicant pipeline) · contracts · departments admin · offices admin (geofence fields) |
| CRM | pipeline **kanban with drag-drop stage moves**, won/lost/restore · customers · new lead/customer forms |
| Fleet | vehicles (+stats/filters) · vehicle detail (state, odometer, driver assign, services) · new vehicle · service log |
| Finance | invoices · bills · detail view |
| Shell | module switcher (ability-gated) · role-aware HR nav (staff vs "my stuff") · ⟳ Odoo sync · AR/EN · dark mode · user menu w/ role badges |

Key files: `src/lib/api.ts` (fetch + CSRF), `src/lib/auth.tsx`, `src/lib/i18n.tsx`,
`src/components/{AppShell,ui,form,EmployeeForm,InvoiceList}.tsx`, `src/messages/{en,ar}.json`.

**Redeploy:** `cd frontend && npm run build && sudo systemctl restart milejet-spa`

---

## 4. API v1 (`/api/v1/*`)

Sanctum **stateful cookie** auth for the SPA (`statefulApi()` in bootstrap; CORS in `config/cors.php`).
Controllers in `app/Http/Controllers/Api/V1/`:

- `AuthController` — login/logout/me (me returns roles + abilities + employee link)
- `HrController` — dashboard, employees, departments, leaves (+types/store/approve/refuse), attendances (+check-in/out), payslips
- `HrAdminController` — employee CRUD, departments/work-locations CRUD, org-chart, contracts, payslip create (bulk)/compute/delete, leave attachments (list+stream)/delete, attendance delete, sync
- `RecruitmentController` — jobs, applicants, store/stage/refuse/restore
- `CrmController` — pipeline, lead store/stage/won/lost/restore, customers (+store)
- `FleetController` — vehicles (+detail/store), services, models, drivers, state/odometer/driver/service actions
- `FinanceController` — invoices, bills, show

Same gates as web routes on every endpoint. Mobile API (`/api/mobile/*`, token-based) is unchanged
and keeps working on every host (nginx passthrough).

⚠️ **Routes are cached in prod** — after ANY change to `routes/*.php` run `php artisan route:cache`
(same for `config:cache` after config/.env changes). A forgotten rebuild = silent 404s.

---

## 5. Odoo configuration

| Setting | Value |
|---|---|
| Server | `https://erp.milejet.space` (Odoo **17.0 Community**) |
| Database | `milejet` · Company MileJet (id=1) |
| Admin user (this app) | `haniusif@gmail.com` |
| Odoo conf / service | `/etc/odoo/odoo.conf` · `systemctl restart odoo` · venv `/opt/odoo/venv/` |

HR addons: `hr_attendance`, `hr_contract`, `hr_expense`, `hr_fleet`, `hr_holidays`, `hr_org_chart`,
`hr_recruitment`, `hr_skills`, plus OCA **`payroll`** (installed; `payroll_account`,
`payroll_contract_advantages`, `payroll_hr_public_holidays`, `hr_payroll_document` available, not installed).
Business modules synced: CRM (`crm.lead`, `res.partner`), Fleet (`fleet.vehicle` + logs), Finance (`account.move`).

### Payroll structure (SA-STD — Saudi Monthly Salary, 9 rules)

| Code | Category | When | Formula |
|---|---|---|---|
| `BASIC` | BASIC | always | `contract.wage * 10/13.5` |
| `HOUSING` | ALW | always | `contract.wage * 3/13.5` |
| `TRANSPORT` | ALW | always | `contract.wage * 0.5/13.5` |
| `GROSS` | GROSS | always | `BASIC + ALW` |
| `GOSI_EE` | DED | **Saudi only** | `-(BASIC+ALW) * 0.10` |
| `NET` | NET | always | `GROSS + DED` |
| `GOSI_ER_SA` | COMP | Saudi only | `min(wage*13/13.5, 45000) * 0.12` |
| `GOSI_ER_FOREIGN` | COMP | non-Saudi | `min(wage*13/13.5, 45000) * 0.02` |
| `EOS_ACCRUAL` | COMP | always | `(wage*10/13.5) / 24` |

Nationality gate: `employee.country_id.code == 'SA'` (both branches verified with test payslips —
see git history / Odoo for the worked examples). Master data originally imported from
`public/Master Sheet - HR-2026.xlsx` (34 employees MJ-001…MJ-034 + contracts, manager chain, nationalities).

---

## 6. Laravel app layout

```
app/Http/Controllers/          — Blade controllers (Auth, Dashboard, Employee, Department,
                                 WorkLocation, Leave, Attendance, Contract, Payslip,
                                 Recruitment, Crm, Fleet, Finance, Preferences, MobileApi)
app/Http/Controllers/Api/V1/   — SPA API (see §4)
app/Services/                  — OdooService (XML-RPC; per-user creds + useServiceAccount()),
                                 OdooAuthService (shared login), OdooRoleMapper, SyncService
app/Models/                    — User + cache mirrors (Employee, Department, WorkLocation, Leave,
                                 LeaveType, Attendance, Contract, Payslip(+Line), JobPosition,
                                 Applicant, RecruitmentStage, Crm*, Fleet*, FinanceInvoice, SyncLog)
routes/                        — web.php (Blade, role-gated), api.php (mobile + v1),
                                 portal/crm/fleet/finance.php (domain-bound roots)
frontend/                      — the SPA (see §3)
deploy/nginx/                  — tracked vhost configs (portal + 4 redirect configs)
```

Mobile API specifics (geofenced attendance, leave attachments, provisioning via
`php artisan odoo:provision-users`) are unchanged — see `MobileApiController` and `config/attendance.php`.

---

## 7. Operations

```bash
# SPA redeploy
cd /var/www/milejet-internal-erp/frontend && npm run build && sudo systemctl restart milejet-spa

# Laravel caches (ALWAYS after route/config changes)
php artisan route:cache && php artisan config:cache && php artisan view:cache

# Sync from Odoo
php artisan odoo:sync            # everything
php artisan odoo:sync payslips   # one model

# Logs
journalctl -u milejet-spa -f
tail -f storage/logs/laravel.log

# Rollbacks (nginx) — per subdomain
sudo cp /etc/nginx/sites-available/portal-milejet.legacy.bak /etc/nginx/sites-available/portal-milejet \
  && sudo nginx -t && sudo systemctl reload nginx        # Blade portal hub back
# (hr/crm/fleet/finance have *.blade.bak equivalents)
```

**Env keys:** `ODOO_*`, `SESSION_DOMAIN=.milejet.space`, `APP_URL=https://portal.milejet.space`
(makes portal Sanctum-stateful automatically), `ATTENDANCE_*` (geofence), `CORS_ALLOWED_ORIGINS` (dev only).

⚠️ **Never run destructive tests/migrations here** — live DB. (2026-06-05 incident: tests with cached
config wiped the DB; guards now live in `TestCase` + `phpunit.xml`.)
⚠️ `erp.milejet.space` is Odoo — adding that server_name to nginx shadows the ERP backbone.

---

## 8. Known caveats

| Area | Note |
|---|---|
| Payroll split | 74/22/4 basic/housing/transport is a convention; per-employee Excel allowances (cols 24-31) not used in rules yet |
| GOSI base | `GOSI_EE` deducts 10% of full gross; law says 10% of (basic+housing) capped 45,000 — formula patch pending |
| Employer cost | COMP category not rolled up into local payslip totals/UI |
| Work-week calendar | Odoo default Mon–Fri vs Saudi Sun–Thu — Sun/Sat leaves compute 0 days (friendly error shown); real fix = switch calendar |
| Sync limits | leaves 500 / attendances 1000 / payslips 500 most-recent (SyncService) |
| Deletes in Odoo | not propagated by sync — manual local cleanup |
| Shared password | provisioned employee accounts (2026-06-01) still on `12345678` — rotate |
| Mobile app | still calls `hr.milejet.space/api/mobile/*` — works via passthrough; consider repointing to portal |

---

## 9. Status log

- **2026-06-05 — SPA cutover (this snapshot):** role scoping for employee-only users (own data only);
  fixed Odoo string-id bug (form many2one ids now cast to int) + employee update redirect;
  built API v1 (all modules) + Next.js SPA with **full Blade feature parity** incl. dark mode;
  deployed on portal.milejet.space (`milejet-spa.service`); hr/crm/fleet/finance subdomains now 301
  into the SPA; Blade UI retired from user traffic (rollback configs kept).
- **2026-06-05 (earlier):** Finance module; module-aware header; Fleet finalized on fleet.milejet.space;
  CRM on crm.milejet.space; portal hub cards wired (commits `0316b96`…`6332a38`).
- **2026-06-03:** leave attachments (mobile + web); friendly Odoo error mapping.
- **2026-06-02:** service-account attendance punch (employees lack hr.attendance rights).
- **2026-06-01:** provisioned 45 employee `res.users` for mobile login.
- **2026-05:** payroll build-out (SA-STD structure, GOSI nationality gating, EOS accrual), Excel master
  import (34 employees + contracts), payslip workflow end-to-end.

**Outstanding / roadmap:** commit the 2026-06-05 work to git (large uncommitted set!);
rotate shared mobile password; GOSI_EE formula fix; surface COMP in payslip UI; Sun–Thu calendar;
repoint mobile app base URL to portal; consider `payroll_account` / `hr_payroll_document` OCA addons.
