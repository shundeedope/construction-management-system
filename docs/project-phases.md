# Construction Management Project Phases

This document turns `docs/construction-management-filament-agent-plan.md` into an implementation-ready, numbered task list for Laravel 13, Filament 5, Livewire 4, and Pest 4.

## Status legend

- `[x]` Complete and verified in the repository.
- `[ ]` Not implemented.
- `[~]` Partially complete; the completed portion is stated explicitly.
- `Optional` is outside the primary MVP and must not begin until the internal workflows are stable.

## Current repository audit

As of 2026-07-12:

- `[x]` Laravel 13.19, PHP 8.5, Filament 5.6, Livewire 4.3, Pest 4.7, and Larastan 3.10 are installed.
- `[x]` SQLite is configured and the default Laravel `users`, password reset, session, cache, queue, passkey, and two-factor tables exist.
- `[x]` Fortify authentication, the Livewire starter UI, and authentication/settings feature tests exist.
- `[x]` The Filament admin panel is registered at `/admin`.
- `[x]` Pest is configured with database refreshes for feature tests.
- `[x]` Pint, Larastan, FilaCheck Pro, and Composer quality scripts are configured.
- `[~]` Construction-domain models, migrations, factories, seeders, and Phase 1 database tests now exist; policies, services, and Filament resources remain for later phases.
- `[~]` Spatie Media Library, role/permission, and activity-log packages are installed; PDF and Google Drive packages remain intentionally uninstalled until their phases.

## Global implementation rules

These rules apply to every phase:

1. Work phase-by-phase and keep each task a complete, tested vertical slice.
2. Use integer cents in signed `bigInteger` columns for money. Never use floats for money.
3. Use string columns for stable workflow/type values, with migration comments listing the initial PHP enum values. Do not use database enum columns.
4. Use lookup tables and foreign keys for user-managed classifications. Do not create lookup tables speculatively for fixed workflow states.
5. Use explicit foreign keys, deliberate delete behavior, unique constraints, and indexes for filters, joins, numbering, and date-based warnings.
6. Use `decimal` only for non-money quantities or hours. Store retention rates as integer basis points so `1% = 100` and `10% = 1000`.
7. Financial records that have entered a workflow must retain history. Prefer status transitions and soft deletion where deletion is allowed.
8. Use policies for authorization and project assignment as a second access boundary. Hiding a Filament action is not sufficient authorization.
9. Put calculations and state transitions in typed application services/actions, not Filament resources.
10. Use database transactions and locking for numbering and financial state transitions.
11. Generate every Filament resource with separate `Schemas/*Form.php` and `Tables/*Table.php` classes. Do not generate View pages/infolists unless a task explicitly requires one.
12. For every `app/Filament` change, run `vendor/bin/filacheck --fix` before the affected Pest tests.
13. For every PHP change, run `vendor/bin/pint --dirty --format agent`, the narrowest `php artisan test --compact ...` command, and relevant Larastan checks.
14. Use `Livewire::test(ComponentClass::class)` in Pest tests; do not use the `livewire()` helper.
15. Store uploaded files through `spatie/laravel-medialibrary` and its package-provided `media` table. Do not create bespoke file path columns for ordinary uploads.

---

# Phase 1: Database Structure

## Goal

Create the complete relational foundation, Eloquent models, factories, and seeders before building application workflows or Filament resources. The schema must support the full internal MVP without conflicting with Laravel's existing tables.

## Phase 1.1: Database conventions and package-owned tables

### Task 1.1.1: Confirm database portability and naming

- `[~]` Review every planned migration against SQLite (current development/test engine) and production-capable MySQL/PostgreSQL conventions; SQLite migration execution and a static portability guard are verified, while a deployment-specific MySQL/PostgreSQL run still needs its configured production credentials.
- `[x]` Keep the existing Laravel tables unchanged unless a domain requirement requires an additive migration.
- `[x]` Use plural snake-case table names and conventional `{model}_id` foreign keys.
- `[x]` Define delete behavior explicitly: restrict deletion of referenced financial masters, cascade true child line items, and null actor references where retaining history is more important than retaining the user.

Automated feature tests / acceptance criteria:

- `DatabaseSchemaTest` proves all domain migrations run from an empty database.
- `DatabaseSchemaTest` proves all migrations roll back cleanly and can be reapplied.
- `DatabaseSchemaTest` proves required foreign keys, unique constraints, and high-value indexes exist.

### Task 1.1.2: Add package-owned database infrastructure

- `[x]` With approval to change dependencies, install `spatie/laravel-medialibrary` and publish its `media` migration.
- `[x]` Add a maintained roles/permissions package (`spatie/laravel-permission`) and publish its migrations; do not create a conflicting replacement `users` table.
- `[x]` Add a maintained activity-log package (`spatie/laravel-activitylog`) and publish its `activity_log` migration.
- `[x]` Configure package migrations so SQLite tests and the intended production database behave consistently.

Automated feature tests / acceptance criteria:

- `PackageDatabaseInfrastructureTest` proves the `media`, permission, role, and activity-log tables migrate successfully.
- `PackageDatabaseInfrastructureTest` proves a user can receive a role and permission.
- `PackageDatabaseInfrastructureTest` proves a model can attach media on the test disk and create an activity entry.

## Phase 1.2: People, organizations, and project access

### Task 1.2.1: Create employee and organization models

- `[x]` Generate `Employee`, `Client`, `ClientContact`, and `Vendor` models, migrations, factories, and seeders with Artisan.
- `[x]` `employees`: optional unique `user_id`, employee number, first/last name, email, phone, hire/termination dates, active flag, and timestamps.
- `[x]` `clients`: company name, primary contact details, billing address fields, tax/registration identifier, notes, active flag, and timestamps.
- `[x]` `client_contacts`: `client_id`, name, title, email, phone, primary flag, and timestamps; index `client_id` and email.
- `[x]` `vendors`: company name, vendor type string, primary contact details, billing address fields, tax identifier, insurance expiration date, active flag, notes, and timestamps.
- `[x]` Add typed casts, fillable attributes, relationships, and useful factory states such as inactive, terminated, and expired insurance.

Automated feature tests / acceptance criteria:

- `PeopleAndOrganizationsSchemaTest` proves each model can be created through its factory.
- `PeopleAndOrganizationsRelationshipsTest` proves client contacts belong to a client and employees may link one-to-one to users.
- `PeopleAndOrganizationsRelationshipsTest` proves deleting a client cascades its contacts but referenced organizations cannot silently orphan financial records.
- `PeopleAndOrganizationsFactoryTest` proves factory states produce valid active/inactive and expiration scenarios.

### Task 1.2.2: Create projects and project assignments

- `[x]` Generate `Project` with `client_id`, nullable `project_manager_id` referencing users, unique job number, name, description, address fields, start/expected/actual completion dates, status string, internal notes, timestamps, and soft deletes.
- `[x]` Add a status comment listing initial values: `planning`, `active`, `on_hold`, `completed`, `cancelled`.
- `[x]` Create `project_user` with unique (`project_id`, `user_id`), project role string, `can_view_financials`, `can_manage_documents`, and timestamps.
- `[x]` Index project status, client, manager, and completion dates used by tables and warning queries.
- `[x]` Add `Project`, `User`, and organization relationships plus assigned/unassigned factory states.

Automated feature tests / acceptance criteria:

- `ProjectSchemaTest` proves duplicate job numbers and duplicate project-user assignments are rejected.
- `ProjectRelationshipsTest` proves client, manager, and team relationships work in both directions.
- `ProjectRelationshipsTest` proves assignment flags and project role persist correctly.
- `ProjectFactoryTest` proves planning, active, completed, and assigned-project states create valid records.

## Phase 1.3: Contracts and change orders

### Task 1.3.1: Create contracts and Schedule of Values

- `[x]` Generate `Contract` with `project_id`, nullable `client_id`, nullable `vendor_id`, type string, unique number, title, description, status string, signed `original_amount` cents, `retention_basis_points`, execution/start/completion dates, `created_by`, nullable `approved_by`, timestamps, and soft deletes.
- `[x]` Type comment: `owner`, `subcontract`, `purchase_order`. Status comment: `draft`, `pending_approval`, `executed`, `closed`, `cancelled`.
- `[x]` Add indexes for project/type/status, client, vendor, and workflow dates.
- `[x]` Generate `ContractLineItem` with `contract_id`, optional cost-code string, description, nullable decimal quantity, nullable unit string, nullable signed unit-price cents, signed amount cents, sort order, and timestamps.
- `[x]` Cascade line items when a never-used draft contract is removed; otherwise preserve financial history through service-layer restrictions.

Automated feature tests / acceptance criteria:

- `ContractSchemaTest` proves contracts and line items can be created and money is stored exactly as integer cents.
- `ContractSchemaTest` proves contract numbers are unique.
- `ContractRelationshipsTest` proves owner contracts can link clients and downstream contracts can link vendors.
- `ContractRelationshipsTest` proves line-item ordering and cascade behavior.
- `ContractFactoryTest` proves owner, subcontract, purchase-order, draft, and executed states are valid.

### Task 1.3.2: Create change orders and line items

- `[x]` Generate `ChangeOrder` with `project_id`, `contract_id`, type string, unique number, title, description, status string, request/submission/approval/rejection timestamps, schedule-impact days, `created_by`, nullable `approved_by`, rejection reason, timestamps, and soft deletes.
- `[x]` Type comment: `owner`, `subcontract`. Status comment: `draft`, `submitted`, `approved`, `rejected`, `cancelled`.
- `[x]` Generate `ChangeOrderLineItem` with `change_order_id`, nullable `contract_line_item_id`, optional cost-code string, description, signed amount cents, sort order, and timestamps.
- `[x]` Index project/contract/status and approval queue dates.

Automated feature tests / acceptance criteria:

- `ChangeOrderSchemaTest` proves signed positive and negative line amounts retain exact cents.
- `ChangeOrderSchemaTest` proves change-order numbers are unique.
- `ChangeOrderRelationshipsTest` proves project, contract, creator, approver, and line-item relationships.
- `ChangeOrderFactoryTest` proves draft, submitted, approved, rejected, owner, and subcontract states create internally consistent dates.

## Phase 1.4: Invoices and payments

### Task 1.4.1: Create invoices and progress-billing lines

- `[x]` Generate `Invoice` with `project_id`, `contract_id`, type string, unique number, status string, billing period, issue/due dates, signed subtotal/retention/total cents, sent/approved/voided timestamps, `created_by`, nullable `approved_by`, rejection/void reasons, notes, timestamps, and soft deletes.
- `[x]` Type comment: `client`, `subcontractor`. Status comment: `draft`, `submitted`, `approved`, `sent`, `partially_paid`, `paid`, `rejected`, `void`.
- `[x]` Generate `InvoiceLineItem` with `invoice_id`, `contract_line_item_id`, scheduled value, previously billed, current work, stored materials, retention, current due (all signed cents), sort order, and timestamps.
- `[x]` Add unique/index constraints needed to prevent duplicate numbers and efficiently calculate previous billing by contract line and invoice status.

Automated feature tests / acceptance criteria:

- `InvoiceSchemaTest` proves invoice and progress line values retain exact integer cents.
- `InvoiceSchemaTest` proves invoice numbers are unique and billing-period queries are indexed.
- `InvoiceRelationshipsTest` proves invoice/project/contract/line/actor relationships.
- `InvoiceFactoryTest` proves client, subcontractor, draft, approved, partially-paid, paid, and void states create consistent timestamps.

### Task 1.4.2: Create payments

- `[x]` Generate `Payment` with `invoice_id`, `project_id`, direction string, positive amount cents, payment date, method string, reference, notes, `recorded_by`, timestamps, and soft deletes.
- `[x]` Direction comment: `incoming`, `outgoing`. Keep payment method as a string for the MVP; introduce a lookup table only when administrators must manage methods.
- `[x]` Index invoice/date, project/direction, and reference.

Automated feature tests / acceptance criteria:

- `PaymentSchemaTest` proves exact integer-cent persistence and relationships to invoice, project, and recorder.
- `PaymentSchemaTest` proves soft-deleted payments remain auditable.
- `PaymentFactoryTest` proves incoming and outgoing payment states are valid and always positive.

## Phase 1.5: Documents, labor, compliance, and system records

### Task 1.5.1: Create document metadata around Media Library

- `[x]` Generate `Document` as the authorized domain record with `project_id`, nullable morph `documentable`, name, description, category string, nullable external URL, nullable Google Drive file/folder IDs, `uploaded_by`, timestamps, and soft deletes.
- `[x]` Do not add local upload path, MIME, or size columns; Media Library's `media` table owns those values and file collections.
- `[x]` Category initial values: `contract`, `change_order`, `client_invoice`, `subcontractor_invoice`, `plan`, `compliance`, `photo`, `general`.
- `[x]` Add indexes for project/category, morph target, and Drive identifiers.

Automated feature tests / acceptance criteria:

- `DocumentSchemaTest` proves documents can belong directly to a project or polymorphically to supported records.
- `DocumentMediaTest` proves one document can attach media and Media Library retains MIME type, size, disk, and generated filename.
- `DocumentFactoryTest` proves local-media and external-link states.

### Task 1.5.2: Create time and leave ledgers

- `[x]` Generate `TimeEntry` with `employee_id`, `project_id`, work date, decimal regular/overtime hours, billable flag, description, status string, submission/approval timestamps, nullable `approved_by`, rejection reason, timestamps, and soft deletes.
- `[x]` Status comment: `draft`, `submitted`, `approved`, `rejected`.
- `[x]` Add an intentional uniqueness rule for the chosen entry granularity (at minimum employee/project/date unless multiple daily rows are explicitly supported).
- `[x]` Generate `LeaveTransaction` with `employee_id`, type string, decimal hours, effective date, notes, `created_by`, and timestamps. Type comment: `accrual`, `usage`, `adjustment`.
- `[x]` Index employee/date/status and project/date/status for weekly sheets and approval queues.

Automated feature tests / acceptance criteria:

- `LaborSchemaTest` proves time and leave values persist without floating-point drift.
- `LaborSchemaTest` proves the selected duplicate-time-entry rule is enforced.
- `LaborRelationshipsTest` proves employee, project, approver, and creator relationships.
- `LaborFactoryTest` proves workflow and leave-ledger states.

### Task 1.5.3: Create compliance requirements and records

- `[x]` Generate `ComplianceRequirement` with name, description, applies-to string, required flag, default reminder days, active flag, and timestamps. Initial applies-to values: `employee`, `vendor`, `project`.
- `[x]` Generate `ComplianceRecord` with `compliance_requirement_id`, nullable employee/vendor/project/document references, issued/expiry dates, status string, notes, nullable `verified_by`, verification timestamp, timestamps, and soft deletes.
- `[x]` Status comment: `valid`, `expiring_soon`, `expired`, `missing`; status is a cache/display aid and must be recomputable from dates and requirement rules.
- `[~]` Enforce in validation/service logic that exactly one primary subject is present where appropriate; the schema supports nullable subjects, with domain validation remaining for Phase 10.
- `[x]` Index subject references, expiry/status, and requirement/status.

Automated feature tests / acceptance criteria:

- `ComplianceSchemaTest` proves employee, vendor, and project compliance records can be represented.
- `ComplianceRelationshipsTest` proves requirement, subject, evidence document, and verifier relationships.
- `ComplianceFactoryTest` proves valid, expiring, expired, missing, and verified scenarios.

### Task 1.5.4: Create number sequences and company settings

- `[x]` Generate `NumberSequence` with unique key/scope, format, next value, padding, reset frequency string, current period, and timestamps.
- `[x]` Initial reset values: `never`, `yearly`. Add a unique constraint that prevents two counters for the same key/scope/period.
- `[x]` Generate `CompanySetting` as a typed key/value settings store suitable for company identity, invoice instructions, compliance warning window, daily-hour maximum, and optional Drive root folder ID.
- `[x]` Encrypt sensitive setting values and never seed real credentials; no sensitive credentials are seeded, and Phase 2's `CompanySettings` service now provides typed encrypted setting access.

Automated feature tests / acceptance criteria:

- `SystemSchemaTest` proves duplicate sequence scopes/periods are rejected.
- `SystemSchemaTest` proves supported setting types round-trip correctly and sensitive values are not stored as plaintext.
- `SystemFactoryTest` proves yearly and non-resetting sequence states.

## Phase 1.6: Eloquent model quality, factories, and seeders

### Task 1.6.1: Complete the Eloquent model layer

- `[x]` Add explicit fillable attributes, typed casts, return-typed relationships, PHPDoc where it improves static analysis, Media Library interfaces/traits, and soft-delete traits.
- `[x]` Extend `User` with employee, project assignment, created/approved/recorded, role, and permission relationships without breaking Fortify/passkeys/2FA.
- `[x]` Add only reusable query scopes justified by planned tables and warnings; avoid embedding financial calculations in accessors.
- `[x]` Ensure factories use relationships and named states rather than manually repeating foreign keys.

Automated feature tests / acceptance criteria:

- `ModelArchitectureTest` proves every domain model uses a factory and guarded mass assignment.
- `ModelRelationshipsTest` exercises every defined relationship in both directions.
- Larastan reports no new model or relationship type errors at the configured level.

### Task 1.6.2: Build baseline domain seeders

- `[x]` Create separate seeders for roles/permissions, company settings, number sequences, people/organizations, projects, financial records, labor/compliance, and demo users.
- `[x]` Seed the six planned users: admin, manager, accounting, supervisor, employee, and executive, using an explicitly local/demo password.
- `[~]` Use factories, `recycle()`, and named states so all seeded relationships reconcile; baseline seeders use idempotent model records, while factories and named states are covered independently.
- `[x]` Keep final showcase amounts in Phase 13, but Phase 1 seeders must already create a coherent minimum record for every table.
- `[x]` Make seeders safe to rerun without multiplying fixed roles, permissions, settings, sequences, or demo accounts.

Automated feature tests / acceptance criteria:

- `DatabaseSeederTest` runs `DatabaseSeeder` on an empty database and proves all expected demo users and core records exist.
- `DatabaseSeederTest` runs fixed-data seeders twice and proves unique fixed records are not duplicated.
- `DatabaseSeederTest` proves every foreign key points to an existing record and every seeded financial total reconciles at the raw line-item level.

## Phase 1.7: Database phase verification

### Task 1.7.1: Verify the complete schema

- `[x]` Run `php artisan migrate:fresh --seed --no-interaction` against SQLite.
- `[x]` Run the Phase 1 feature tests, Pint, and relevant Larastan analysis.
- `[x]` Inspect the resulting schema for missing constraints/indexes and resolve all failures before Phase 2.

Automated feature tests / acceptance criteria:

- All Phase 1 tests pass with `php artisan test --compact` using the relevant test files.
- A fresh migration and seed completes without warnings or duplicate-key failures.
- `php artisan migrate:refresh --seed --no-interaction` completes successfully, proving migration rollback and re-application.
- No domain table uses a database enum or floating money column.
- All ordinary uploaded-file metadata lives in the package `media` table.

---

# Phase 2: Domain Foundation, Authorization, and Shared Services

## Phase 2.1: PHP enums and money behavior

### Task 2.1.1: Implement PHP enums

- `[x]` Create backed PHP enums for all initial status/type/direction values documented in Phase 1.
- `[x]` Give UI-facing enums Filament-compatible labels and colors where useful.
- `[x]` Cast model string attributes to enums while preserving string database columns.

Automated feature tests / acceptance criteria:

- `DomainEnumTest` proves every persisted initial value round-trips through its enum.
- `DomainEnumTest` proves labels and colors exist for UI-facing workflow enums.
- `DomainEnumTest` proves invalid values fail predictably rather than silently entering workflows.

### Task 2.1.2: Implement integer-money helpers

- `[x]` Add a focused value object/formatter for converting user decimal input to cents and formatting cents for display.
- `[x]` Define rounding rules and reuse them for retention and percentage calculations.
- `[x]` Keep locale/currency configurable through company settings.

Automated feature tests / acceptance criteria:

- `MoneyTest` covers zero, negative, large, fractional, formatted, and invalid inputs.
- `MoneyTest` proves `50000000` renders as `$500,000.00` under demo settings.
- `MoneyTest` proves retention rounding is deterministic and no float drift occurs.

## Phase 2.2: Roles, permissions, and policies

### Task 2.2.1: Define application roles and permissions

- `[x]` Define Administrator, Project Manager, Accounting, Site Supervisor, Employee, and Executive / Read Only roles.
- `[x]` Define granular capabilities for projects, financials, documents, labor, compliance, administration, and approval actions.
- `[x]` Seed and assign roles to the six demo users.

Automated feature tests / acceptance criteria:

- `RolePermissionTest` proves the seeded capability matrix for every role.
- `RolePermissionTest` proves the executive cannot mutate records and the administrator has full access.
- `RolePermissionTest` proves accounting can manage invoices/payments without automatically gaining operational project edits.

### Task 2.2.2: Implement project-scoped policies

- `[x]` Create policies for every domain model and explicit policy methods for workflow actions.
- `[x]` Combine global roles with `project_user` assignment and per-assignment financial/document flags.
- `[~]` Scope Filament queries as well as individual record actions to prevent URL/direct-query bypasses; record-level authorization is complete and the reusable `Project::visibleTo($user)` query scope exists, but there are no Filament resources to apply it to until Phase 3, which must use it on every table and select.

Automated feature tests / acceptance criteria:

- `ProjectAuthorizationTest` proves an employee cannot view an unassigned project.
- `ProjectAuthorizationTest` proves a manager can access assigned project financials.
- `ProjectAuthorizationTest` proves a supervisor cannot see confidential financials without the flag.
- `ProjectAuthorizationTest` proves accounting can access all financial records and a read-only executive cannot change them.

## Phase 2.3: Shared infrastructure services

### Task 2.3.1: Implement concurrency-safe numbering

- `[x]` Create `NumberSequenceService` using transactions and `lockForUpdate()` with project/year interpolation.
- `[x]` Support project, owner contract, subcontract, change-order, client-invoice, and vendor-invoice formats.
- `[x]` Support configured yearly reset and enforce uniqueness at the database layer.

Automated feature tests / acceptance criteria:

- `NumberSequenceServiceTest` proves sequential unique project numbers.
- `NumberSequenceServiceTest` proves project/year interpolation and yearly reset.
- `NumberSequenceServiceTest` simulates competing generation and proves duplicate numbers cannot be committed.
- `NumberSequenceServiceTest` proves a failed surrounding transaction does not consume/commit an inconsistent counter.

### Task 2.3.2: Implement activity logging conventions

- `[x]` Log important create/update transitions and all financial approvals, rejections, execution, voiding, payment changes, and document actions.
- `[x]` Record actor, subject, old/new state, and project context without logging secrets.

Automated feature tests / acceptance criteria:

- `ActivityLoggingTest` proves important transitions create one attributable log entry.
- `ActivityLoggingTest` proves idempotent retries do not create duplicate transition logs.
- `ActivityLoggingTest` proves sensitive settings and credentials are excluded.

---

# Phase 3: Clients, Vendors, and Project Workspace

## Phase 3.1: Client and vendor management

### Task 3.1.1: Build Client and Vendor Filament resources

- `[x]` Create separate form/table classes, filters, search, relation managers, policies, and navigation groups.
- `[x]` Manage client contacts and expose organization relationships without loading large datasets eagerly.
- `[x]` Display vendor insurance warnings and active/inactive states.

Automated feature tests / acceptance criteria:

- `ClientResourceTest` uses `Livewire::test()` to cover list, search, create, edit, validation, contacts, and authorization.
- `VendorResourceTest` covers list, search, create, edit, validation, insurance warning, and authorization.
- Both are Filament smoke-tested for authorized users, and forbidden for unauthorized roles.

## Phase 3.2: Project management and project team

### Task 3.2.1: Build the Project resource

- `[x]` Build project forms/tables with generated job numbers, client/manager selection, status/date validation, filters, warning indicator, and financial revised-value, commitments, and gross-margin columns from shared aggregate summaries.
- `[x]` Add project-team management for role and financial/document flags.
- `[x]` Ensure project number generation is automatic and immutable.

Automated feature tests / acceptance criteria:

- `ProjectResourceTest` covers create/edit/validation, generated immutable number, filters, search, and assignment management.
- `ProjectResourceTest` proves unrelated projects are absent from tables and inaccessible directly.
- `ProjectResourceTest` proves role-specific financial columns and actions are hidden and server-authorized.

### Task 3.2.2: Build the Project overview shell

- `[x]` Create the dedicated project View page with sections for overview, team, contracts, changes, invoices, payments, documents, labor, compliance, and activity.
- `[x]` Render zero-state financial KPIs and attention panels before later financial phases are populated.

Automated feature tests / acceptance criteria:

- `ProjectOverviewTest` proves every authorized role sees only permitted sections.
- `ProjectOverviewTest` proves a new project renders all zero-state figures without exceptions or divide-by-zero.
- `ProjectOverviewTest` smoke-tests the page and its relation managers.

---

# Phase 4: Owner Contracts, Subcontracts, and Schedule of Values

## Phase 4.1: Contract calculations and workflows

### Task 4.1.1: Implement contract calculation services

- `[x]` Make Schedule of Values line items the source of truth for original contract amount.
- `[x]` Validate owner/client and subcontract-or-purchase-order/vendor invariants.
- `[x]` Implement exact cents totals and retention calculations.

Automated feature tests / acceptance criteria:

- `ContractCalculationServiceTest` proves line totals, zero values, negative invalid values, and retention rounding.
- `ContractInvariantTest` rejects owner contracts without the project client and downstream contracts without a vendor.
- `ContractInvariantTest` rejects a contract whose project/organization relationships conflict.

### Task 4.1.2: Implement execution workflow

- `[x]` Implement draft → pending approval → executed → closed/cancelled transitions in transactions.
- `[x]` Lock original financial fields and line items after execution; later changes must use change orders.
- `[x]` Authorize and log execution, closure, and cancellation.

Automated feature tests / acceptance criteria:

- `ContractWorkflowTest` proves valid transitions and rejects invalid transitions.
- `ContractWorkflowTest` proves executed original amounts and Schedule of Values cannot change.
- `ContractWorkflowTest` proves only authorized roles execute contracts and retries are safe.

## Phase 4.2: Contract Filament interfaces

### Task 4.2.1: Build owner contract and subcontract resources/pages

- `[x]` Create separate owner-contract and subcontract/purchase-order experiences over the shared table.
- `[x]` Use full pages for complex editing, a Schedule of Values manager, read-only overview after execution, and related placeholders for future records/documents.
- `[x]` Add execute/close/cancel actions with confirmation, policy checks, notifications, and activity history.

Automated feature tests / acceptance criteria:

- `OwnerContractResourceTest` covers create, Schedule of Values, totals, execution lock, actions, and authorization.
- `SubcontractResourceTest` covers vendor requirements, Schedule of Values, execution lock, actions, and authorization.
- Project overview tests prove original owner and subcontract totals update correctly.

---

# Phase 5: Change Orders and Project Financial Summary

## Phase 5.1: Change-order workflow

### Task 5.1.1: Implement submission, approval, and rejection

- `[x]` Implement draft → submitted → approved/rejected transitions with validation, transactions, locking, timestamps, actors, and activity logs.
- `[x]` Lock submitted records except review fields; forbid silent edits after approval/rejection.
- `[x]` Make approval idempotent and require rejection reasons.

Automated feature tests / acceptance criteria:

- `ChangeOrderWorkflowTest` proves draft editability and submitted/approved locking.
- `ChangeOrderWorkflowTest` proves approval is idempotent and unauthorized approval is rejected.
- `ChangeOrderWorkflowTest` proves rejection requires and stores a reason without affecting totals.

## Phase 5.2: Revised values and project margin

### Task 5.2.1: Implement shared financial calculations

- `[x]` Create `ContractCalculationService` and `ProjectFinancialSummaryService` for approved owner changes, revised owner value, revised subcontract commitments, gross margin, and margin percentage.
- `[x]` Include only approved, non-void financial records and handle zero owner value safely.
- `[x]` Use these services everywhere; do not duplicate formulas in widgets/resources.

Automated feature tests / acceptance criteria:

- `ProjectFinancialSummaryServiceTest` proves draft/submitted/rejected changes do not affect totals and approved changes do.
- `ProjectFinancialSummaryServiceTest` proves gross margin and percentage, including a zero-value owner contract.
- `ProjectFinancialSummaryServiceTest` proves owner and subcontract changes affect the correct side only.

## Phase 5.3: Change-order Filament interfaces

### Task 5.3.1: Build change-order pages and actions

- `[x]` Build owner/subcontract change-order forms, line management, badges, filters, submit/approve/reject actions, notifications, and activity history.
- `[x]` Surface pending approvals and revised values in project and contract pages.

Automated feature tests / acceptance criteria:

- `ChangeOrderResourceTest` covers create/edit, line totals, workflow actions, visibility, validation, notifications, and authorization.
- `ChangeOrderResourceTest` proves locked fields cannot be changed by a crafted Livewire request.
- Project overview tests prove approved changes immediately update revised value and margin.

---

# Phase 6: Client Progress Invoicing and PDF

## Phase 6.1: Invoice calculations and workflow

### Task 6.1.1: Implement progress-billing calculations

- `[x]` Create `InvoiceCalculationService` for scheduled value, prior approved billing, current work, stored materials, retention, current due, subtotal, and total.
- `[x]` Validate same project/owner contract, no negative billing, no duplicate billing, and no line/contract overbilling.
- `[x]` Snapshot derived values on finalized invoices so history does not change when later records are added.

Automated feature tests / acceptance criteria:

- `ClientInvoiceCalculationTest` proves previous billing and current due across multiple periods.
- `ClientInvoiceCalculationTest` blocks line and aggregate overbilling and mismatched projects/contracts.
- `ClientInvoiceCalculationTest` proves exact retention rounding and finalized snapshots.

### Task 6.1.2: Implement client invoice workflow

- `[x]` Implement authorized draft → approved → sent and voiding transitions; partially-paid/paid transitions are handled by Phase 8's payment service.
- `[x]` Lock finalized values, preserve void history, record sent timestamp, and log transitions.

Automated feature tests / acceptance criteria:

- `ClientInvoiceWorkflowTest` covers valid/invalid transitions, locking, sent timestamp, void history, authorization, and logging.
- `ClientInvoiceWorkflowTest` proves only non-void approved/sent invoices affect project invoiced totals.

## Phase 6.2: Client invoice UI and PDF

### Task 6.2.1: Build client invoice Filament pages

- `[x]` Build dedicated create/edit/review pages, Schedule of Values lines, status actions, filters, and project/contract navigation.

Automated feature tests / acceptance criteria:

- `ClientInvoiceResourceTest` covers creating from an owner contract, calculations, validation, actions, authorization, notifications, and locking.
- Project overview tests prove client-invoiced and remaining-to-bill totals update.

### Task 6.2.2: Generate professional invoice PDFs

- `[x]` With dependency approval, install a maintained Laravel-compatible PDF renderer.
- `[x]` Render company, client, project, invoice, period, Schedule of Values, retention, amount due, instructions, and notes.
- `[x]` Authorize generation/download and test without relying on external services.

Automated feature tests / acceptance criteria:

- `ClientInvoicePdfTest` proves an authorized user receives a PDF response with the expected filename/content type.
- `ClientInvoicePdfTest` extracts/asserts key invoice text and reconciled totals.
- `ClientInvoicePdfTest` proves unauthorized users cannot generate or download it.

---

# Phase 7: Subcontractor Invoicing

## Phase 7.1: Vendor invoice rules and workflow

### Task 7.1.1: Implement downstream invoice calculations

- `[x]` Reuse shared invoice primitives while keeping downstream business rules explicit.
- `[x]` Validate the subcontract/purchase order and project/vendor relationship and prevent billing beyond revised commitment.
- `[x]` Implement authorized draft → submitted → approved/rejected transitions with rejection reason, locking, and activity logging; partially-paid/paid transitions are handled by Phase 8's payment service.

Automated feature tests / acceptance criteria:

- `SubcontractorInvoiceCalculationTest` blocks billing beyond revised subcontract commitment and mismatched contracts/projects.
- `SubcontractorInvoiceWorkflowTest` covers submit, approve, reject with reason, locking, authorization, idempotence, and activity.
- `SubcontractorInvoiceWorkflowTest` proves only approved non-void invoices affect project cost totals.

## Phase 7.2: Vendor invoice Filament interface

### Task 7.2.1: Build entry and review pages

- `[x]` Build internal invoice entry, manager review, accounting approval, filters, notifications, and project/contract navigation.
- `[x]` Keep line-level approval/resubmission out of the primary MVP; reserve it for Phase 12 portal work if required.

Automated feature tests / acceptance criteria:

- `SubcontractorInvoiceResourceTest` covers create, submit, approve, reject, validation, notifications, authorization, and locked fields.
- Project overview tests prove subcontractor-invoiced and remaining-commitment totals update.

---

# Phase 8: Partial and Full Payments

## Phase 8.1: Payment service and invoice status recalculation

### Task 8.1.1: Implement payment recording and removal

- `[x]` Create `PaymentService` using transactions and invoice locking.
- `[x]` Require positive amounts, validate incoming/client and outgoing/subcontractor direction, and block cumulative overpayment.
- `[x]` Recalculate invoice status after create or soft-delete and log both operations.

Automated feature tests / acceptance criteria:

- `PaymentServiceTest` proves partial payment results in `partially_paid` and final payment results in `paid`.
- `PaymentServiceTest` blocks zero, negative, wrong-direction, mismatched-project, and overpayment cases.
- `PaymentServiceTest` proves removing a payment recalculates status and concurrent payments cannot overpay.

## Phase 8.2: Payment UI and financial totals

### Task 8.2.1: Build payment actions/resources

- `[x]` Add record-payment actions and payment history to invoices/projects, plus a dedicated accounting table if useful.
- `[x]` Display receivables, payables, received/paid, remaining-to-bill, and remaining commitments from shared summaries.

Automated feature tests / acceptance criteria:

- `PaymentResourceTest` covers record/remove actions, validation, notifications, visibility, and authorization.
- `ProjectFinancialSummaryServiceTest` proves received, paid, receivable, payable, and remaining figures after partial/full payments.

---

# Phase 9: Documents and Optional Google Drive

## Phase 9.1: Secure local document storage

### Task 9.1.1: Implement document storage service and UI

- `[x]` Create `DocumentStorageService` over Media Library for upload, replace, download, and deletion.
- `[x]` Use private storage by default; validate filename-independent MIME type, extension, and size.
- `[~]` Attach documents to projects, contracts, change orders, and invoices through authorized Filament actions/relation managers. The compliance-record relationship is ready, while its UI waits for the Phase 10 compliance resource.

Automated feature tests / acceptance criteria:

- `[x]` `DocumentStorageTest` uses `Storage::fake()` to cover upload, replace, download, delete, metadata, and validation.
- `[x]` `DocumentAuthorizationTest` proves users without project or financial-document access cannot download files through direct URLs.
- `[x]` `DocumentResourceTest` smoke-tests relation managers with `Livewire::test()`.

## Phase 9.2: Optional Google Drive synchronization

### Task 9.2.1: Isolate Drive integration

- `[ ]` With dependency/credential approval, add a `GoogleDriveService` contract and implementation, project folder structure, queued uploads, external links, retries, and failure logging.
- `[ ]` Fall back cleanly to local storage when Drive is disabled or credentials are absent.

Automated feature tests / acceptance criteria:

- `GoogleDriveServiceTest` uses a fake adapter to prove folder naming, upload metadata, replacement, and links.
- `GoogleDriveFallbackTest` proves every local document operation works with missing credentials.
- `GoogleDriveJobTest` proves retries/failures do not lose local files or duplicate domain records.

---

# Phase 10: Time Entries, Sick Leave, and Compliance

## Phase 10.1: Employee time tracking

### Task 10.1.1: Implement time-entry workflow and UI

- `[x]` Create `TimeEntryApprovalService`, employee resource, weekly timesheet page, manager queue, and project labor summary.
- `[x]` Validate non-negative hours, configurable daily maximum, project assignment, duplicate rule, and approved-entry locking.
- `[x]` Authorize employees for their own entries and managers for assigned-project review.

Automated feature tests / acceptance criteria:

- `TimeEntryWorkflowTest` proves an employee can draft/submit own assigned-project time and cannot use unrelated projects.
- `TimeEntryWorkflowTest` proves manager approval/rejection, required rejection reason, locking, and authorization.
- `WeeklyTimesheetPageTest` covers entry, totals, filters, approval queue, and project labor summary.

## Phase 10.2: Sick leave ledger

### Task 10.2.1: Implement auditable leave balances

- `[x]` Build restricted leave transaction management and calculate balance as accrual + signed adjustment - usage.
- `[x]` Never overwrite a balance; corrections are adjustment transactions.

Automated feature tests / acceptance criteria:

- `LeaveBalanceServiceTest` proves accrual, use, positive/negative adjustments, and exact balance.
- `LeaveAuthorizationTest` proves employees see only permitted data and sensitive HR management is restricted.
- `LeaveResourceTest` covers validation and immutable ledger history.

## Phase 10.3: Compliance tracking and warnings

### Task 10.3.1: Implement requirements, status service, and UI

- `[x]` Create `ComplianceStatusService`, requirement/record resources, evidence documents, verification actions, filters, and warning panels, including dashboard compliance attention.
- `[x]` Calculate valid, expiring soon, expired, and missing using configurable warning days.
- `[x]` Surface vendor blocking warnings without claiming jurisdiction-specific legal compliance in project-scoped UI and dashboard attention.

Automated feature tests / acceptance criteria:

- `ComplianceStatusServiceTest` covers boundary dates for valid, expiring, expired, and missing statuses.
- `ComplianceResourceTest` covers creation, evidence, verification, filters, and authorization.
- `ComplianceWarningTest` proves dashboard counts and vendor warnings respect project and HR permissions.

---

# Phase 11: Dashboards, Reporting, and Notifications

## Phase 11.1: Main and project dashboards

### Task 11.1.1: Build financial and attention widgets

- `[x]` Build main widgets for active projects, revised value, receivables, payables, margin, overdue invoices, pending changes/timesheets, and expiring compliance.
- `[x]` Complete project KPIs for original/approved/revised values, invoiced/paid balances, commitments, and margin.
- `[x]` Add a projects-by-status chart after the core figures are correct.

Automated feature tests / acceptance criteria:

- `[x]` `DashboardWidgetTest` reconciles financial values and every attention-queue count while exercising the chart widget.
- `[x]` `DashboardAuthorizationTest` proves financial widgets are absent for unauthorized users and assignment-level financial scoping is preserved.
- `[x]` `DashboardQueryTest` asserts bounded query counts for financial, attention, and status widgets.

## Phase 11.2: Reporting tables and reminders

### Task 11.2.1: Build operational reports

- `[x]` Add overdue invoices, pending change-order approvals, upcoming compliance, projects-by-status, and lowest-margin reports with scoped filters and sortable financial totals. Exports are not justified for the current live operational queues.
- `[x]` Add queued database notifications for change-order and invoice rejections, overdue invoices, compliance expiry, and pending timesheets with idempotent daily scheduling.

Automated feature tests / acceptance criteria:

- `[x]` `ReportingTableTest` and `ProjectFinancialReportTest` prove report scoping, status filtering, lowest-margin sorting, and financial totals.
- `[x]` `ReminderNotificationTest` uses notification fakes to prove correct overdue-invoice recipients and no duplicate reminder in the configured period.
- `[x]` `ReportingAuthorizationTest` proves confidential financial and compliance reports are hidden from unauthorized users; projects-by-status remains available only to users who can view projects.

---

# Phase 12: Optional Subcontractor Portal

This entire phase is optional and blocked until Phases 1–11 pass their tests.

## Phase 12.1: External panel and vendor identities

### Task 12.1.1: Create the `/subcontractor` Filament panel

- `[x]` Add vendor-user association, panel access rules, separate navigation, authentication boundaries, and vendor-only query scopes.

Automated feature tests / acceptance criteria:

- `[x]` `SubcontractorPanelAuthorizationTest` proves vendor users enter only their panel and internal users retain their intended panel access.
- `[x]` `SubcontractorPanelAuthorizationTest` and portal isolation tests prove cross-vendor access is impossible through lists, record URLs, Livewire calls, and downloads.
- `[x]` `SubcontractorPanelSmokeTest` smoke-tests all portal pages.

## Phase 12.2: Invoice and document submission

### Task 12.2.1: Build vendor self-service workflows

- `[x]` Let vendors view their profile, assigned projects, subcontracts, approved changes, invoices, documents, rejection reasons, and payment status.
- `[x]` Let vendors submit/resubmit invoices and supporting documents without exposing owner revenue, other vendors, or internal margin.

Automated feature tests / acceptance criteria:

- `[x]` `VendorInvoiceSubmissionTest` covers submit, reject, correct, and resubmit while retaining history.
- `[x]` `VendorDocumentSubmissionTest` covers authorized upload/download and malicious cross-vendor attempts.
- `[x]` `VendorDataIsolationTest` proves owner contracts, client invoices, internal margin, and unrelated vendor data never appear.

---

# Phase 13: Demo Data, End-to-End Verification, and Delivery

## Phase 13.1: Reconciled showcase data

### Task 13.1.1: Complete the Summit General Contractors scenario

- `[x]` Seed the specified company, Riverside client/project, `$500,000.00` owner Schedule of Values, `$185,000.00` subcontracts, approved/submitted changes, invoices, and payments.
- `[x]` Seed an overdue invoice, partial payment, pending change order, expired insurance, expiring compliance record, and pending timesheets.
- `[x]` Reconcile the expected revised owner value `$515,000.00`, revised commitments `$195,000.00`, gross margin `$320,000.00`, and margin `62.14%`.

Automated feature tests / acceptance criteria:

- `DemoDataReconciliationTest` runs the complete seeder and asserts every specified record and amount.
- `DemoDataReconciliationTest` proves dashboard services return all expected summary figures.
- `DemoDataReconciliationTest` proves each demo user can authenticate and receives the expected role/project assignment.

## Phase 13.2: End-to-end MVP slice

### Task 13.2.1: Verify the complete internal workflow

- `[x]` Cover client → project/number → manager → owner contract/SOV → two subcontracts → owner/subcontract changes → client invoice → partial incoming payment → vendor invoice → outgoing payment → document → time submission/approval → dashboard.

Automated feature tests / acceptance criteria:

- `ConstructionManagementWorkflowTest` executes the complete vertical slice through application services and asserts every state, activity entry, and financial result.
- Filament feature tests cover the critical UI actions for the same path using `Livewire::test()`.
- A role-matrix regression test proves no step bypasses policy or project scoping.

## Phase 13.3: Quality, documentation, and release readiness

### Task 13.3.1: Run final quality gates

- `[x]` Run FilaCheck, Pint, the full compact Pest suite, Larastan, frontend build, and dependency audits; resolve all failures.
- `[x]` Check major pages for exceptions, JavaScript errors, broken actions, N+1 queries, and empty states through the existing Filament smoke, workflow, authorization, and query-count tests; no browser log file is present in the local environment.

Automated feature tests / acceptance criteria:

- `vendor/bin/filacheck --fix` finishes cleanly.
- `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, `vendor/bin/phpstan analyse`, and `npm run build` pass.
- All Filament resources have smoke tests and no known authorization or financial-rule gaps remain.

### Task 13.3.2: Write delivery documentation

- `[x]` Update the README with installation, environment, migrations, seeding, demo credentials, queue/PDF/storage/Drive setup, test/quality commands, roles, workflows, limitations, and excluded scope.
- `[x]` Add the requested architecture overview and `How This Project Demonstrates Filament` mapping.
- `[x]` Document intentional deviations from the source plan and leave optional integrations clearly disabled by default.

Automated feature tests / acceptance criteria:

- `DocumentationTest` or an equivalent architecture test proves every required README heading and referenced command/config key exists.
- A fresh-clone setup verification completes migration, seed, build, and tests using only the documented steps.

## Explicitly excluded from the primary MVP

Do not add Gantt scheduling, RFIs, submittals, drawing markup, estimating/bidding, payroll/certified payroll, jurisdiction-specific labor compliance, lien waivers, accounting synchronization, bank feeds, online payments, e-signatures, offline mode, real-time chat, advanced cost-code accounting, multi-tier potential changes, or a client portal. These require separate approved phases and must not drive speculative abstractions in this plan.
