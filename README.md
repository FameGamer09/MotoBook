# MotoBook

MotoBook contains three connected apps: the Laravel customer storefront, the PHP management system (`admin/` and `A-management/`), and the React rider PWA (`A-rider/`). The Laravel app uses two MySQL databases: its own customer/order database and the management database `motobook_admin`.

## Local setup on Windows/XAMPP

Install PHP 8.2+, Composer, Node.js/npm, and MySQL/MariaDB. Start Apache and MySQL in XAMPP, then open PowerShell in this repository.

For Apple sign-in dependency installation, enable the bundled sodium extension in `C:\xampp\php\php.ini` (`extension=sodium`) and restart Apache. Composer must be able to reach Packagist to update the lock file when dependency requirements change.

1. Create a primary database (the example environment uses `olivaian`) in phpMyAdmin.
2. Import `admin/database/schema.sql` and then `admin/database/operations.sql`. These create the management database and its tables.
3. Apply the rider schema to `motobook_admin`, in order:

   In phpMyAdmin, select `motobook_admin` and import each SQL file in order. You can also run the redirection commands from **Command Prompt** (not PowerShell):

   ```bat
   mysql -u root motobook_admin < A-rider\migrations\001_init_rider_schema.sql
   mysql -u root motobook_admin < A-rider\migrations\002_rider_account_settings.sql
   ```
4. Install Laravel dependencies and prepare its environment:

   ```powershell
   composer install
   Copy-Item .env.example .env
   ```

   Edit `.env`: set `APP_URL`, `DB_*` to the primary database, and `MANAGEMENT_DB_*` to `motobook_admin`. Keep `.env` private and out of Git.
5. Initialize Laravel and build its frontend:

   ```powershell
   php artisan key:generate
   php artisan migrate
   npm install
   npm run build
   ```

6. Build the rider PWA:

   ```powershell
   Set-Location A-rider
   npm install
   npm run build
   Set-Location ..
   ```

   The rider build output is served from `A-rider/public/build/`. For local use, start the site through XAMPP or `php artisan serve` and open the customer/admin/rider URLs configured for this checkout.
7. Run the read-only configuration check:

   ```powershell
   php artisan motobook:check
   ```

## Local operation

- Run `php artisan schedule:work` so pending deliveries are retried every minute.
- If queued jobs are enabled, run `php artisan queue:work` in a separate terminal.
- Check `storage/logs/laravel.log` and the PHP/Apache logs when a screen fails.
- Make a database backup before migrations or production updates. Example for a local MySQL install:

  ```powershell
  mysqldump -u root olivaian > motobook-primary-backup.sql
  mysqldump -u root motobook_admin > motobook-management-backup.sql
  ```

  Store backups outside the public web root and verify that a backup can be restored before relying on it.

## Integration and launch checks

- Rider GPS is sent by the rider PWA while an active delivery is open. The browser/device must grant location permission. Customer tracking only displays recent rider fixes; it reports waiting/unavailable when the latest fix is older than 90 seconds. Browsers require HTTPS for location access outside localhost, and a phone test is required to confirm OS background-location behavior.
- Customer order status alerts currently use the in-app notification system. SMS requires a configured, funded Semaphore account (`SEMAPHORE_API_KEY` and optional `SEMAPHORE_SENDER_NAME`). Browser notifications require browser permission. These provider settings are not included in Git.
- GCash checkout records an order as pending. Do not treat a submitted order or a customer-provided reference as proof of payment; payment confirmation/reconciliation must be performed through an approved GCash integration or the configured management process.
- Google/Facebook/Apple sign-in requires current Composer dependencies plus provider client IDs/secrets, exact callback URLs, and provider-side app configuration. Empty `.env` values are placeholders, not working credentials.
- Before launch, use separate production database credentials, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, scheduled jobs, queue monitoring, real email/SMS/payment provider accounts, and a tested backup/restore procedure. Never publish `.env`, provider secrets, database dumps, or real customer data.

`php artisan motobook:check` checks local configuration and database connectivity without changing records. It cannot validate provider-side credentials, deliver an SMS, confirm a real payment, or test GPS on a physical phone.
