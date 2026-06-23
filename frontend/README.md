# MileJet ERP — SPA Frontend

Next.js (App Router, TypeScript, Tailwind 4) frontend for the MileJet internal
ERP. Consumes the Laravel JSON API at `/api/v1/*` (Sanctum stateful cookies)
served by the main app in the repo root. Covers **HR, CRM, Fleet, Finance**
with the same role scoping as the Blade UI (employee-only users see only their
own profile/leaves/attendance/payslips).

## Architecture

```
Browser ── *.milejet.space ──► nginx
                                ├── /            → next start (this app, port 3000)
                                └── /api, /sanctum → Laravel (php-fpm) — same host
```

Same-origin in production → no CORS, and the existing `.milejet.space` session
cookie (SSO) is shared with the Blade app: a user signed in on
portal.milejet.space is already signed in here.

- Auth: `src/lib/auth.tsx` — `/sanctum/csrf-cookie` → `POST /api/v1/auth/login` → cookie session.
  `me()` returns roles + precomputed `abilities` used to gate nav and actions
  (the API enforces everything server-side regardless).
- i18n: `src/lib/i18n.tsx` + `src/messages/{en,ar}.json` — locale in `mj_locale`
  cookie; `<html lang/dir>` set server-side, full RTL via logical properties.
- API client: `src/lib/api.ts` — XSRF header handling, typed errors.

## Development

```bash
npm install
API_PROXY_TARGET=https://hr.milejet.space npm run dev
```

`API_PROXY_TARGET` makes `next dev` proxy `/api` + `/sanctum` so the browser
sees one origin. **Cookie caveat:** the Laravel session cookie is scoped to
`.milejet.space`, so use a hosts-file alias, e.g.

```
127.0.0.1  dev.milejet.space
```

then browse `http://dev.milejet.space:3000`. (Plain `localhost` will not hold
the session cookie.) Also add the dev host to the API's env:

```
SANCTUM_STATEFUL_DOMAINS=dev.milejet.space:3000,<spa-prod-host>
CORS_ALLOWED_ORIGINS=http://dev.milejet.space:3000   # only needed without the proxy
```

…then `php artisan config:cache` in the repo root.

## Production deployment (cutover plan)

1. `npm run build` here; run with `next start -p 3000` under systemd/pm2.
2. nginx server block for the chosen subdomain (e.g. `app.milejet.space`):
   proxy `/` to `127.0.0.1:3000`; proxy `/api/`, `/sanctum/` to the existing
   Laravel upstream **with the same Host header**.
3. Add the subdomain to `SANCTUM_STATEFUL_DOMAINS` in the Laravel `.env`,
   `php artisan config:cache`.
4. The Blade app keeps serving the module subdomains until parity — migrate a
   subdomain to the SPA by pointing its nginx root at `next start` when its
   screens are ready.

## What exists vs. what's left

Done: login, app shell (module switcher, role-aware nav, AR/EN + RTL),
HR (dashboard, employees + profile, leaves + request/approve/refuse,
attendance + check-in/out, payslips + detail), CRM (pipeline kanban with
drag-and-drop stage moves, won/lost/restore, customers), Fleet (vehicles,
services), Finance (invoices, bills).

Not yet ported from Blade: employee create/edit, departments/work-locations
admin, recruitment, contracts, org chart, payslip creation (single/bulk),
leave attachments, Odoo sync trigger, dark mode.
