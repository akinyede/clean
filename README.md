# Clean

Work-in-progress PHP web application for managing bookings, staff, customers, and payments, with an admin dashboard, reports, reminders, and basic QuickBooks integration.

- Stack: PHP + Composer, vanilla JS for admin UI
- Key areas: `Website/wip/admin/` (dashboard, bookings, customers, staff, payments, reports), `Website/wip/app/` (bootstrap, database, auth, helpers, QuickBooks), REST-like admin APIs in `Website/wip/admin/api/`
- DB schema: see `Website/wip/database-schema.sql`
- Status: early WIP, not production-ready. Expect breaking changes.

Quick start (local)
1. PHP 8.x and Composer installed
2. `cd Website/wip` and run `composer install`
3. Create a database and import `Website/wip/database-schema.sql`
4. Configure connection in `Website/wip/app/database.php`
5. Serve `Website/wip/` via a local PHP server or your web server of choice

Security note: This is a prototype; review authentication, input validation, and secrets handling before exposing publicly.
