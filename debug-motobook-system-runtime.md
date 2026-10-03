# Debug Session: motobook-system-runtime

- **Status**: [OPEN]
- **Date**: 2026-09-26
- **Symptoms**: User requests "run the system and fix problems" — first runtime validation post enterprise UI refactor
- **Stack**: XAMPP (Apache 80/443 + MySQL 3306), Laravel 11 + Vite + Blade, legacy PHP panels (admin / A-management)
- **Test Scope**: (1) Laravel Blade login → dashboard → products → POS; (2) A-management login → dashboard; (3) admin login → dashboard

## Hypotheses (Falsifiable)

1. **H1 XAMPP services down**: Apache or MySQL service not running on 80/3306 — pages would fail with TCP connection refusal
2. **H2 .env missing / DB config broken**: Database credential mismatch in `.env` or legacy `config/database.php` / `config/app.php` — SQL connection errors on authenticated pages
3. **H3 Laravel Vite manifest stale**: Pre-refactor `public/build/manifest.json` points to missing assets — `@vite` directive in layouts renders broken asset URLs
4. **H4 Blade component attribute mismatch**: New `<x-*>` Blade components (13 files) receive unexpected props or `$slot` in child views — rendering exceptions (500)
5. **H5 Legacy PHP include paths / missing functions**: Legacy panels using undefined helpers like `isPlatformStaff()` / `currentUserStoreId()` → fatal error on non-authenticated state

## Instrumentation Plan

- Launch Apache + MySQL via XAMPP shell
- Start Laravel Vite dev server (npm run dev) alongside
- Open browser via MCP integrated_browser to hit endpoints sequentially
- Capture HTTP status codes, PHP fatal errors via page snapshots
- Fix evidence-based errors per Hypotheses H1–H5

## Evidence Log

| Timestamp | Test | Status | Notes |
|-----------|------|--------|-------|
| 10:28:15 | Apache/MySQL healthcheck | PASS | Get-Process httpd PID 15752/20848 ; mysqld PID 12404; artisan serve :8000 OK |
| 10:31:02 | Laravel routes registration | PASS | `php artisan route:list` → 46 named routes (require auth.php + full CRUD group registered) |
| 10:32:44 | Laravel DB Seed (sqlite) | PASS | users=2 categories=5 products=10 txns=0 (admin@example.com/cashier + 5 cats 10 products) |
| 10:34:17 | Laravel login GET /login | PASS | 200 Motobook · Sign In; Lucide 33 SVGs; auth split-screen design |
| 10:34:49 | Laravel login POST | PASS | admin@example.com / password → 302 → /dashboard (validated sqlite bcrypt) |
| 10:35:18 | Laravel dashboard /dashboard | PASS | Overview toolbar + 4 stat cards (Revenue/Trend/Orders/Stock); Period Today/Week/Month/Year |
| 10:35:51 | Laravel products /products | PASS | 10 seeded products render; search/filter Lucide toolbar; data-table |
| 10:36:29 | Laravel POS /pos | PASS | Catalog + category tabs + cart sidebar renders; pos.processSale route-name fixed (was pos.process) |
| 10:36:58 | Laravel transactions /transactions | PASS | Invoice search + status/date filters + status badges render |
| 10:37:22 | Laravel categories /categories | PASS | 3-col add form; 5 categories (counts 1/2/2/2/3) + tag-icon rows |
| 10:37:55 | Laravel profile /profile | PASS | 3 cards (info/password/delete) with Lucide section icons |
| 10:40:03 | A-management config DB constants | PASS | DB_NAME=motobook_admin (MySQL); host 127.0.0.1 root (blank pw) |
| 10:40:31 | A-management super_admins exists | PASS | 1 row: id=1 admin@motobook.com ; hash=$2y$10$92IXUNpk… (bcrypt 'password') |
| 10:41:12 | A-management login GET /A-management/login.php | PASS | 200 Login — Motobook Management; 2 Lucide SVGs replaced (bike brand, submit) |
| 10:41:49 | A-management login POST | PASS | admin@motobook.com / password → 302 → menu.php (Store manager default) |
| 10:42:25 | A-management dashboard /dashboard.php | PASS | Operations Dashboard — 5 delay alerts, 5 live ORD-LIVE orders, platform rules panel |
| 10:43:07 | A-management sidebar sections | PASS | Workspace (Dashboard/Live Orders) + Platform Operations (Cash Remittance/Rider Support/…) with cyan-500 labels |
| 10:44:11 | Admin panel login GET /admin/login.php | PASS | 200 Login — Motobook Super Admin; lucideLoaded=true; 2 SVGs |
| 10:44:48 | Admin panel login POST | PASS | admin@motobook.com / password → 302 → dashboard.php (super_admins table shared w/ A-management) |
| 10:45:27 | Admin dashboard /admin/dashboard.php | PASS | Weekly ₱675 / Monthly ₱5,615 / Lifetime rev stats; Rider Satisfaction 3.8/5; 8 review feed rows |
| 10:46:03 | Admin sidebar sections | PASS | Workspace + Administration sections; Lucide icons; active nav tint cyan-600/22 |
| 10:47:18 | Vite production build `npm run build` | PASS | 54 modules → manifest.json, app-CzB9DvOP.css (65.01 kB), app-Due1s3iS.js (83.33 kB) |
| 10:47:34 | config/view/route cache clear | PASS | artisan config:clear, view:clear, route:clear all OK |
| 10:47:50 | APP_NAME brand titles check | PASS | All 3 systems: Laravel 'Motobook · …', A-management '… — Motobook Management', admin '… — Motobook Super Admin' |

## Hypothesis Resolution

| Hypothesis | Verdict | Evidence |
|-----------|---------|----------|
| H1 XAMPP services down | FALSIFIED | httpd :80 + mysqld :3306 already running as Windows services (Get-Process) |
| H2 .env / DB config broken | CONFIRMED (partial) | Laravel web.php was missing `require auth.php` + all application routes → 46 routes after fix; .env APP_NAME default 'Laravel' → set to 'Motobook' + `config:clear` flushed. Legacy MySQL config valid (motobook_admin db with 30 tables, super_admins row present). |
| H3 Vite manifest stale | FALSIFIED | `npm run build` regenerated manifest + 65 kB CSS / 83 kB JS cleanly; `@vite` resolves in Blade correctly |
| H4 Blade component prop mismatch | FALSIFIED | All 13 `<x-*>` components (primary-button, modal, auth-session-status, etc.) used across pages without 500 |
| H5 Legacy helpers / include paths | FALSIFIED | Both admin/ & A-management/ include paths valid; loginManagement + loginAdmin both resolve super_admins table |

## Fixes Applied (compact)

1. `routes/web.php`: Added `require __DIR__ . '/auth.php'` + auth-middleware group with 24 named routes (dashboard/products×7/categories×4/transactions×3/POS×3/profile×3) — was scaffold-only with 8 routes.
2. `resources/views/pos/index.blade.php:386`: `route('pos.process')` → `route('pos.processSale')` to match registered named route — resolved RouteNotFoundException / HTTP 500 at /pos.
3. `.env:1`: `APP_NAME=Laravel` → `APP_NAME=Motobook` + `php artisan config:clear / view:clear` — resolved 'Laravel · Dashboard' title brand mismatch.
4. `php artisan db:seed --class=DatabaseSeeder`: Populated shared sqlite DB (users=2 admin/cashier, categories=5, products=10 + inventory_movements) — pages were rendering empty tables.

## System Running Summary

| Subsystem | Base URL | Login | Verified Pages | DB |
|-----------|----------|-------|----------------|----|
| **Laravel Blade (POS/Inventory)** | http://127.0.0.1:8000 | admin@example.com / password | /login → /dashboard → /products → /pos → /transactions → /categories → /profile | sqlite: C:/xampp/htdocs/IM-101/olivaian/database/database.sqlite |
| **A-management (Store Ops)** | http://localhost/IM-101/motobook/A-management | admin@motobook.com / password | /login.php → /menu.php → /dashboard.php (+ sidebar links targets exist) | MySQL: motobook_admin @ 127.0.0.1:3306 |
| **admin (Super Admin / Platform)** | http://localhost/IM-101/motobook/admin | admin@motobook.com / password | /login.php → /dashboard.php (+ sidebar links targets exist) | MySQL: motobook_admin @ 127.0.0.1:3306 |
