# CapePoint POS — Back-office MVP

A classic Laravel + Blade + jQuery back-office for sales, purchases, inventory, contacts, expenses, payments and reports.
Layout and patterns follow the UltimatePOS style (AdminLTE sidebar + navbar, server-side Yajra DataTables with export buttons,
modal CRUD forms, action dropdowns), with a refreshed deep-blue / teal theme.

There is **no** cashier/POS screen, installer wizard, or documents module.

## Stack

| | |
|---|---|
| Framework | Laravel 12 (PHP 8.2+). Laravel 10 was requested, but every 10.x release is now blocked by unpatched security advisories. |
| UI | Blade, AdminLTE 3.2, Bootstrap 4.6, Font Awesome 5, jQuery, Select2, daterangepicker, Toastr, SweetAlert2, Chart.js |
| Tables | `yajra/laravel-datatables-oracle` (server-side) + DataTables Buttons (Copy / CSV / Excel / PDF / Print / Columns; exports fetch **all** filtered rows) |
| Auth / RBAC | `spatie/laravel-permission` |
| PDF | `barryvdh/laravel-dompdf` |
| Audit | `spatie/laravel-activitylog` |
| DB | MySQL / MariaDB |

All front-end assets are vendored in `public/vendor`, so no CDN or npm build is needed.

## Setup

```bash
cp .env.example .env            # set DB_* (database: capepoint_pos)
composer install
php artisan key:generate
php artisan migrate --seed      # schema + roles/permissions + settings + demo data
php artisan storage:link        # for the business logo
php artisan serve               # http://127.0.0.1:8000
```

Reset with fresh demo data: `php artisan migrate:fresh --seed`.

### Demo logins (password: `password`)

| Role | Email | Access |
|---|---|---|
| Admin | admin@capepoint.test | Everything (bypasses permission checks) |
| Manager | manager@capepoint.test | All operations and reports; no settings, users or roles |
| Accountant | accountant@capepoint.test | Payments, expenses, reports; read-only on sales, purchases and stock |
| Viewer | viewer@capepoint.test | Read-only on all modules and reports |

Change these passwords before any real use. Roles are editable under **Settings → Roles & Permissions**. Defaults live in `config/pos.php`.

## Modules

- **Dashboard**: a **Filter by date** control (Today, Yesterday, Last 7/30 days, This/Last month, This month last year, This/Last year, Current/Last financial year, Custom range) drives total sales, net sales, invoice due, returns, purchases, purchase due, expenses, gross and net profit, and payments in and out. Current receivables, payables and low stock are shown separately, alongside a sales vs purchases chart and recent, overdue and top-product lists.
- **Notifications**: the navbar bell opens a panel with Unread / All tabs, *mark all as read* and per-item read markers. Alerts cover low and out-of-stock products, overdue invoices and supplier bills, and customer payments received, and go only to users whose role covers that module. Overdue checks run when the panel opens (at most every 30 minutes) and hourly via `php artisan schedule:run`.
- **Settings**: business profile and logo (also used in the sidebar, on the login page and as the favicon), currency format, tax label and PIN, date format, financial year start month, document number prefixes, payment terms, the payment methods on offer, stock adjustment reasons, negative-stock switch, invoice terms and footer. Also tax rates, users, roles and permissions, and the activity log.
- **Inventory**: products (SKU, barcode, category, unit, tax, cost and selling price, alert quantity, opening stock), categories, units, stock adjustments, low-stock alerts, and per-product stock history.
- **Contacts**: customers and suppliers with opening balance, pay terms, credit limit, live balance, and a statement of account (on screen and as PDF).
- **Sales**:
  - Invoices with line discount (%), per-line tax, and an invoice discount (fixed or %).
  - Payment is taken on the invoice itself (Cash, Bank, Mobile Money, Cheque, Card, or Credit / partial payment).
  - Invoice list with Paid / Partial / Due status, a Sales Due page, and credit notes (sales returns that restock and can refund).
  - Delivery notes, created from an invoice or standalone.
  - PDFs for the invoice, credit note and delivery note.
- **Purchases**:
  - LPOs (with PDF), which can be received in full or in part into a purchase invoice / GRN.
  - Direct purchases.
  - Purchase returns (debit notes, with PDF).
  - Supplier payments.
  - Stock cost is updated as a weighted average.
- **Expenses**: categories and entries, with a per-category summary.
- **Payments**:
  - Customer receipts and supplier payments.
  - Allocation to one or more invoices (with an oldest-first auto-allocate button).
  - Any unallocated amount is kept as credit on the account.
  - Receipts and payment vouchers as PDF.
- **Reports** (date-range filters, summary boxes, DataTables with export):
  - Purchase & Sale, Sales Summary (plus by product), Sales Due / Receivables, Aging (0–30 / 31–60 / 61–90 / 90+, by invoice or due date), Sales Returns.
  - Purchase Summary, Purchase Returns, Supplier Payables (with aging).
  - Expense Report, Stock & Valuation (plus movement summary), Customer & Supplier, Profit Snapshot (net sales − COGS − expenses, by product and by month).

## Accounting rules

- **Invoice:** discounts come off **before tax**. A line discount (%) applies first. The invoice discount (fixed, or % of subtotal) is then spread across the lines in proportion to their value, and each line's tax is charged on its discounted amount. Total = subtotal − invoice discount + tax, which always equals the sum of the line amounts. The same algorithm runs in `App\Services\DocumentTotals` and in `public/js/doc-form.js`.
- **Pay term:** the pay term on the invoice or purchase (defaulting to the contact's term, then the business default) sets the due date. The due date can still be overridden.
- **Returns:** credit and debit notes use the line's discounted net and tax. Returning the last units on a line credits exactly what is left on it, so there is never a leftover cent.
- **Edits:** an invoice or purchase can't be edited below the payments already allocated to it. An edited invoice keeps its original cost of goods. Reversing a purchase also reverses its effect on average cost.
- **Per invoice:** `paid = allocations − refunds`, `returned = Σ credit notes`, `due = max(0, total − returned − paid)`.
- **Contact balance (ledger):** opening + invoiced − returned − paid + refunded. Overpayments and advances show as a negative (credit) balance.
- **Stock:** every change writes a `stock_movements` row (opening, purchase, sale, returns, adjustment). Editing or deleting a document reverses its movements.
- **COGS:** each sale line stores the product's weighted-average cost at the time of sale. Stock adjustments are valued at the current average cost and don't change it. Dashboard and Profit report figures come from the same `ProfitService`.
- **Deletes:** documents are soft-deleted and excluded from every report. Deleting an invoice also removes any payment taken on the invoice form; separate receipts stay on the account as credit.

## Security

- Every route is protected by a Spatie permission. The Admin role bypasses checks via `Gate::before`.
- Only administrators can assign the Admin role or edit, reset or delete admin accounts. A non-admin can't change the permissions of a role they hold.
- CSRF is on for all state-changing requests. Login is throttled, sessions are regenerated on login, and inactive or deleted users are signed out.
- Output is escaped everywhere, including toastr (`escapeHtml`) and notification rendering.
- Business rules are enforced on the server with row locks: no overselling, over-returning, over-refunding, over-allocating, or over-receiving an LPO.
- Security headers: X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, and HSTS over HTTPS. DomPDF remote loading and PHP are disabled.
- Password policy for new passwords: at least 10 characters, mixed case, with numbers.
- **Production:** set `APP_ENV=production` and `APP_DEBUG=false` (the defaults in `.env.example`), serve over HTTPS with `SESSION_SECURE_COOKIE=true`, and change the demo passwords.

## Code map

```
app/Services/            SaleService, PurchaseService, PaymentService, StockService, ProfitService,
                         LedgerService (statements), DocumentTotals, ReferenceService (numbering),
                         NotificationService (bell alerts)
app/Models/Concerns/     HasPaymentStatus, HasCreator, LogsModelActivity
app/Http/Controllers/    one controller per module; ContactController is shared by customers & suppliers
public/js/app.js         DataTables defaults, modal loader, AJAX forms, delete confirm, money formatting
public/js/doc-form.js    line-item editor for invoices, LPOs and purchases
public/css/theme.css     the theme layer over AdminLTE
resources/views/pdf/     DomPDF templates (layout + invoice, credit note, delivery note, LPO, debit note, receipt, statement)
config/pos.php           permission groups and default roles
```

## Known limitations

- Delivery notes don't track quantity already delivered against an invoice.
- Aging and receivables use current balances. An "as of" past date does not rebuild historical balances.
- A fixed-amount discount on an LPO isn't carried over when goods are received (percentage discounts are).
- Money is stored with 2 decimals. The decimal places setting allows 0–2.
- Single location / warehouse.
