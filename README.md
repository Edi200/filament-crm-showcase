# Filament CRM Showcase
 
A collection of production code samples extracted from a real-world internal CRM built with Laravel 13 and Filament v5 — a heavy machinery management system covering fleet tracking, service workbooks, parts inventory, and parts issuance logging.
 
## Samples
 
### Machine Number Observer (`MachineObserver`)
 
An Eloquent observer that automatically generates sequential machine numbers scoped by year and type.
 
- Fires on `creating` and `updating` Eloquent events
- Calculates `machine_number` as `max + 1` scoped to `(year, type)` combination
- On year or type change, recalculates the number for the new scope excluding the current record
- Handles both raw strings and `MachineType` enum via a `normalizeType()` helper
- Registered in `AppServiceProvider::boot()` via `Machine::observe()`
  
### Spare Parts CSV Import (`ImportSpareParts`)
 
A queued Laravel job that imports and syncs spare parts from a semicolon-delimited ERP export.
 
- Reads `parts.csv` from local storage using `league/csv` with stream parsing
- Skips empty rows and rows with missing `id` or `name`
- Validates that `id` is numeric before processing
- Upserts by explicit ERP `id` using `Model::unguarded()` to bypass non-incrementing primary key restrictions
- Updates existing records only when tracked fields have actually changed (`name`, `category`, `price`, `unit`, `quantity`)
- Logs row-level skip/error reasons and a final summary (`inserted`, `updated`, `skipped`, `errors`)
  
### Workbook CSV Import (`ImportWorkbooks`)
 
A queued Laravel job that imports service workbook headers from an ERP CSV export, resolving machine references by serial number.
 
- Reads `WbHeader.csv` using `league/csv` with semicolon delimiter
- Resolves `machine_id` by looking up `machines.serial_number` — skips and logs rows where the serial is not found
- Normalizes dates via `Carbon::parse()` with a safe fallback to `null`
- Strips surrounding quotes from nullable remark fields
- Upserts by explicit ERP `id` using `Model::unguarded()`
- Updates existing rows only when data has actually changed
- Full error isolation per row — a single bad row does not abort the entire import
  
### Workbook Resource (`WorkbookResource`)
 
A fully read-only Filament v5 resource for browsing and viewing service workbooks.
 
- Read-only table with no create, edit, or delete actions
- Row click navigates to a custom `ViewWorkbookInfo` page
- Custom row actions: `View Info` and `Manage Images`
- Searchable by workbook number, machine model, and customer name
- Customer name column uses a correlated subquery for reliable sorting across joins
- Role-based access: admin and mechanic can view; only admin can create, edit, or delete
- Default sort by `work_date` descending
- `stackedOnMobile()` for responsive table layout
- Pagination options: 10 / 20 / 30 / 40 / 50
  
### Workbook Info Page (`ViewWorkbookInfo`)
 
A custom Filament `ViewRecord` page rendering a full workbook overview with three sections.
 
- Eager-loads `machine.customer`, `wbDetails.sparePart`, and `images` in `resolveRecord()` to avoid N+1 queries
- Section 1: read-only infolist with workbook fields and machine/owner details via `columnSpanFull()`
- Section 2: spare parts table rendered in a custom Blade view — stacked card layout on mobile, full table on desktop
- Section 3: image gallery with `GLightbox` lightbox, resolving URLs via `Storage::temporaryUrl()` with a fallback to `Storage::url()`
- Gross total computed from `quantity × price` across all workbook detail lines
  
## Stack
 
- Laravel 13
- PHP 8.3
- Filament v5
- Spatie Laravel Permission
- league/csv
- MySQL
- Tailwind CSS
