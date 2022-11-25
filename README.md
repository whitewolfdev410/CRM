# Test Task CRM-X

This is a code extracted from a hypothetical complex CRM system "CRM-X"

### Key notes:

Laravel's default config system has been replaced with the cascading config system (from Laravel 5)

Files from `/config/<environment>/` override the defaults from `/config/`

Environment is best to be set via `APP_ENV=local` environment variable or `--env=local` artisan command option

For simplicity, frontend part has been removed completely, so you will start from scratch

### Setup

1. Create a new MySQL database
2. Import database from the file `db.sql`
3. Set your database connection in the database config file

## The Task

### Create a new module called Importer
- this system is organized into modules, feel free to use existing modules for reference and/or use the dedicated commands
- this module holds functionality of importing various data from HTML files
- implements importing WorkOrders from the file `work_orders.html`
- each import run should be stored in DB table `importer_log` - create table migration ( id, type, run_at, entries_processed, entries_created )
- allows user to import new file and to see log of previously completed imports
- exposes both web interface (simple view, no authentication required) and console interface (artisan command)

### Logic of WorkOrders import
- new work orders should be created in the database `work_order` table
- there should be no duplicates in `work_order_number` column
- once the import completes, user should receive a report in a CSV file
- CSV report should contain data of each parsed work order plus a note whether it has been created or skipped because it already existed in DB
- HTML to database columns mapping:
  - `Ticket` -> `work_order.work_order_number`
  - `entityid` from `Ticket` link href (e.g. "t6UJ9A06IAK4") -> `work_order.external_id`
  - `Urgancy` -> `work_order.priority`
  - `Rcvd Date` -> `work_order.received_date`
  - `Category` -> `work_order.category`
  - `Store Name` -> `work_order.fin_loc`

### Tips
- feel free to use any 3rd party packages you will find useful for the task (remember to add them to composer)
- browser's web tools can help while finding elements to parse in the HTML
- no need for a fancy view, it only needs to be functional
- we prefer using high level database abstractions such as models instead of raw SQL
- we appreciate code that's easily readable and maintainable

*Good luck!*