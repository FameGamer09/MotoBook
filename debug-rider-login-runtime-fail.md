# Debug Session: rider-login-runtime-fail

**Status:** [OPEN]  
**Created:** 2026-09-30  
**Objective:** Fix runtime startup of Rider MotoBook system so SSO login redirects to Rider panel successfully. User-reported symptom: PHP server / MySQL / XAMPP start commands keep failing, cancelled, or crashing.

## Symptoms Reported

- XAMPP Apache `apache_start.bat` and `mysql_start.bat` return immediately, server not up.
- mysqld.exe detached launches but MySQL port 3306 is True for ~4s only, then crashes / refuses new connections when PHP script runs 5s later.
- `Start-Process php.exe -S 127.0.0.1:8080 -t motobook` keeps being skipped/cancelled or PowerShell parser error.
- Migration runner `_apply_mig_v2.php` stdout not flushing, no error messages visible to know if migration applied.

## Repro Steps (User's Attempt)

1. Run login.php via browser
2. Expect rider login works
3. Actual: Server down

## 5 Falsifiable Hypotheses

| # | Hypothesis | Evidence to Verify |
|---|---|---|
| H1 | **MariaDB 10.4 XAMPP crashes** due to stale `*.pid` files (LAPTOP-MNK9LSAV.pid + mysql.pid) causing lock contention; ib_logfile LSN sequence mismatch after multiple killed instances triggers crash recovery loop (exit immediately after `Server socket created on IP: '::'.`) | Read `C:\xampp\mysql\data\*.err` tail last 60 lines at crash moment; delete stale pid + redo logs; retry start |
| H2 | **`proc_open` + `create_new_console` spawning of mysqld in PHP keepalive** closes mysqld immediately when parent PHP script exits (Windows console group cleanup kills child processes). mysqld.exe needs `-daemonize` / `--console=false` or Start-Process -NoNewWindow / use sc.exe service, not raw proc_open child. | Check if mysqld.exe spawned via Start-Process (not child of php.exe) lives > 60s with polling loop |
| H3 | **`ADD INDEX IF NOT EXISTS` SQL at migration line 176** is invalid in MariaDB < 10.8, causing migration apply script to halt early and rider_orders/rider_deposits tables never created; SSO `/session/me` selects `gcash_mobile_number` against non-migrated legacy riders → PDOException unknown column at rider login. | Run `SHOW TABLES LIKE 'rider_deposits'` after migration; if empty inspect migration stderr. |
| H4 | **PowerShell Start-Process parsing of arguments** that contain comma/dash tokens: `-ArgumentList '-S','127.0.0.1:8080','-t',$docRoot` gets mangled by PS 5 binder when server spawn is skipped by user; resulting in no HTTP server running at all so browser can't reach login.php. | After server spawn, do `Invoke-WebRequest 127.0.0.1:8080/login.php -TimeoutSec 15` with retry loop, collect raw HTTP status code. |
| H5 | **Migration 001 seed SQL INSERT IGNORE uses placeholder bcrypt hash** `$2y$10$r4n8tT2d8bDZt0v9xwQyeeWd63Kz8B7p3R9sQxUaOeLfYgP7yQdKC` which doesn't match default password `Motobook200409`, so even if MySQL + tables exist, password_verify() at login returns false → user cannot log in. | Verify `password_verify('Motobook200409', (SELECT password_hash FROM riders WHERE rider_code='RDR-0001'))` via PHP probe. |

## Evidence Log

| Timestamp | Source | Finding |
|-----------|--------|---------|
| (tbd) | | |
