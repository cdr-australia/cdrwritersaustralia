STATE after B0
Versions: PHP 8.5.12 NTS, Composer 2.9.4, Laravel 13.43.0, laravel/breeze v2.4.2, MySQL 8.4.3, Node 22.22.0, npm 10.9.4
Project: C:\laragon\app\cdrwa-app (git branch main, pushed)
Database: cdrwa (MySQL, root, no password). Tests use SQLite in memory via phpunit.xml.
Admin: seeded from CDRWA_ADMIN_EMAIL / CDRWA_ADMIN_PASSWORD (in .env, not committed)
Laravel 13 notes: User model uses #[Fillable] and #[Hidden] attributes; role is not fillable.
Commands that must keep passing:
  php artisan migrate:fresh --seed
  php artisan cdrwa:show-config  (registry_path missing until B2)
  php artisan cdrwa:init-data    (prints "nothing to do" when data exists)
  php artisan test               (34 passed)
Local content: storage/app/cdrwa/data (25 files from seed). Backups and output folders not created yet.
Tables: users (+role), drafts, revisions, audit_log, redirects (minimal design, H03 pending)
Open items: confirm migration deviations when H03 arrives; role middleware tests for the multi-role form