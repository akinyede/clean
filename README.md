# Clean — Cleaning Service Booking & Operations

Clean is a work‑in‑progress PHP platform for residential/commercial cleaning services. It enables online bookings and quote requests, staff scheduling, customer and payment management, reminders, and admin reporting.

Features
- Online booking and quote requests
- Admin dashboard with calendar and staff assignment
- Manage customers, bookings, invoices, and payments
- Automated reminders via cron
- Reports; early QuickBooks integration stub

Tech Stack
- PHP 8.x + Composer
- MySQL/MariaDB
- Vanilla JavaScript for admin UI
- REST-like endpoints in `Website/wip/admin/api/`

Getting Started
1) Install PHP 8.x and Composer
2) `cd Website/wip && composer install`
3) Create a database and import `Website/wip/database-schema.sql`
4) Configure credentials in `Website/wip/app/database.php`
5) Serve `Website/wip/` (e.g., `php -S localhost:8000 -t Website/wip`)

Project Structure
- `Website/wip/admin/` — dashboard, pages, and admin JS
- `Website/wip/admin/api/` — REST-like endpoints
- `Website/wip/app/` — bootstrap, database, auth, helpers, quickbooks
- `Website/wip/database-schema.sql` — database schema

Status & Security
- Early WIP; not production-ready
- Review authentication, input validation, and secret handling before going live

Roadmap (suggested)
- Auth + RBAC; pricing/rate cards
- Recurring bookings; payment gateway integration
- Notifications (email/SMS) and delivery logs
- Tests (unit/integration) and security hardening
