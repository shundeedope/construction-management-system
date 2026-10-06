# Construction Management MVP with Laravel 13 and Filament 5

## Purpose

Build a production-style demo application for a general contractor using Laravel 13 and Filament 5.

The application should demonstrate how Filament can be used for more than simple CRUD panels. It should include:

- project-centric navigation;
- contract and subcontract management;
- financial calculations;
- approval workflows;
- progress invoicing;
- partial payments;
- document management;
- employee time tracking;
- compliance reminders;
- role-based access;
- automated tests.

The project will be built behind the scenes and later demonstrated in a YouTube video. The video will focus on reviewing the finished codebase and explaining which Filament features were used for each business requirement.

The target is an impressive but controlled MVP, not a complete Procore replacement.

---

# Starting Point

Assume the following already exists:

- fresh Laravel 13 application;
- Filament 5 installed;
- Filament admin panel configured;
- database connection configured;
- local development environment working.

Use the current Laravel and Filament conventions already present in the project.

Before implementing, inspect:

- `composer.json`;
- existing panel provider;
- database driver;
- testing configuration;
- coding style configuration;
- installed Laravel and Filament packages.

Do not replace existing project conventions unless necessary.

---

# Technical Expectations

## General

Use:

- Laravel 13;
- Filament 5;
- PHP enums for statuses and types;
- Eloquent policies for authorization;
- Pest for automated tests;
- factories and seeders for realistic demo data;
- integer minor units for monetary values;
- database transactions for important financial operations;
- service classes for non-trivial calculations and workflows;
- activity logging for important state changes.

Avoid:

- storing monetary values as floats;
- large amounts of business logic inside Filament resources;
- `count() + 1` for automatic numbering;
- silently editing finalized financial records;
- overly generic polymorphic abstractions when they reduce clarity;
- integrating external accounting, payroll, or payment gateways in the MVP.

## Money

Store monetary amounts as integer cents.

Example:

```php
500_000_00 // $500,000.00
```

Create reusable formatting and conversion helpers where appropriate.

## Code Quality

The implementation should:

- have clear model relationships;
- separate business rules from presentation code;
- use typed methods and return types;
- use transactions around state transitions;
- include validation for financial constraints;
- include comments only where the intent is not obvious;
- follow existing Laravel and Filament conventions.

---

# Core Domain

The central entity is a project.

```text
Project
├── Client
├── Project team
├── Owner contract
├── Subcontracts
├── Change orders
├── Client invoices
├── Subcontractor invoices
├── Payments
├── Documents
├── Time entries
├── Compliance records
└── Activity history
```

The financial model has two sides.

```text
Revenue / upstream
Owner contract
→ owner change orders
→ client invoices
→ incoming payments

Cost / downstream
Subcontracts
→ subcontract change orders
→ subcontractor invoices
→ outgoing payments
```

The project dashboard must summarize both sides.

---

# Main User Roles

Implement roles with policies and project-level access rules.

Suggested roles:

- Administrator
- Project Manager
- Accounting
- Site Supervisor
- Employee
- Executive / Read Only

Expected rules:

## Administrator

- full access;
- manages users, settings, sequences, and permissions.

## Project Manager

- sees assigned projects;
- manages project details;
- manages contracts, change orders, documents, and invoices;
- can approve selected project workflows;
- can view project financials.

## Accounting

- sees all financial records;
- manages invoices and payments;
- can view all projects;
- cannot necessarily change operational project details.

## Site Supervisor

- sees assigned projects;
- manages time entries, documents, and selected project data;
- cannot see confidential financial information unless explicitly allowed.

## Employee

- sees only assigned projects;
- submits own time entries;
- has limited document access.

## Executive / Read Only

- sees dashboards and reports;
- cannot modify records.

Use project assignment as an additional authorization layer.

Suggested pivot:

```text
project_user
- project_id
- user_id
- project_role
- can_view_financials
- can_manage_documents
- timestamps
```

---

# Suggested Navigation

```text
Dashboard

PROJECT MANAGEMENT
- Projects
- Clients
- Time Entries

FINANCIALS
- Owner Contracts
- Subcontracts
- Change Orders
- Client Invoices
- Subcontractor Invoices
- Payments

PEOPLE
- Employees
- Subcontractors
- Compliance Records
- Sick Leave

DOCUMENTS
- Documents

ADMINISTRATION
- Users
- Roles and Permissions
- Number Sequences
- Company Settings
```

Most work should still happen from inside the Project resource.

The Project view page should be the main workspace.

Suggested project sections:

```text
Overview
Team
Owner Contract
Subcontracts
Change Orders
Client Invoices
Subcontractor Invoices
Payments
Documents
Labor
Compliance
Activity
```

Use relation managers for smaller related datasets.

Use dedicated or nested resource pages for complex records such as:

- contracts;
- change orders;
- invoices;
- invoice line items.

---

# Proposed Database Structure

The exact schema may be refined during implementation, but use this as the baseline.

## Users and People

```text
users
employees
clients
client_contacts
vendors
projects
project_user
```

A vendor represents a subcontractor or supplier.

## Financial Records

```text
contracts
contract_line_items
change_orders
change_order_line_items
invoices
invoice_line_items
payments
```

Suggested contract types:

```text
owner
subcontract
purchase_order
```

Suggested invoice types:

```text
client
subcontractor
```

Suggested payment directions:

```text
incoming
outgoing
```

## Documents and Labor

```text
documents
time_entries
leave_transactions
compliance_requirements
compliance_records
```

## System Records

```text
number_sequences
company_settings
activity_log
```

---

# Important Enums

Create enums as needed.

Suggested enums:

```text
ProjectStatus
- Planning
- Active
- OnHold
- Completed
- Cancelled

ContractType
- Owner
- Subcontract
- PurchaseOrder

ContractStatus
- Draft
- PendingApproval
- Executed
- Closed
- Cancelled

ChangeOrderType
- Owner
- Subcontract

ChangeOrderStatus
- Draft
- Submitted
- Approved
- Rejected
- Cancelled

InvoiceType
- Client
- Subcontractor

InvoiceStatus
- Draft
- Submitted
- Approved
- Sent
- PartiallyPaid
- Paid
- Rejected
- Void

PaymentDirection
- Incoming
- Outgoing

ComplianceStatus
- Valid
- ExpiringSoon
- Expired
- Missing

TimeEntryStatus
- Draft
- Submitted
- Approved
- Rejected
```

Enums should provide Filament-friendly labels and colors where useful.

---

# Automatic Numbering

Implement configurable, concurrency-safe numbering.

Suggested formats:

```text
Project:         JOB-{YEAR}-{SEQUENCE}
Owner contract:  OC-{PROJECT}-{SEQUENCE}
Subcontract:     SC-{PROJECT}-{SEQUENCE}
Change order:    CO-{PROJECT}-{SEQUENCE}
Client invoice:  INV-{PROJECT}-{SEQUENCE}
Vendor invoice:  VINV-{PROJECT}-{SEQUENCE}
```

Create a numbering service that:

- uses a database record per sequence type;
- uses row locking inside a transaction;
- guarantees unique numbers;
- supports yearly reset where configured;
- can interpolate project number and year;
- is covered by tests.

Do not use record counts to produce sequence numbers.

---

# Phase 1: Foundation

## Goal

Prepare the domain foundation, permissions, enums, money handling, and demo data infrastructure.

## Tasks

1. Inspect the existing project.
2. Configure Pest if not already configured.
3. Add or implement role and permission support.
4. Add activity logging.
5. Create enums.
6. Create money formatting helpers or value objects.
7. Create number sequence infrastructure.
8. Create factories.
9. Create realistic seeders.
10. Add project-level authorization rules.

## Seeded Demo Users

Create example users:

- admin@example.com
- manager@example.com
- accounting@example.com
- supervisor@example.com
- employee@example.com
- executive@example.com

Use a known local development password and document it in the seeder output or README.

## Acceptance Criteria

- each role can log in;
- navigation changes based on authorization;
- project assignments can restrict project access;
- monetary values display correctly;
- automatic numbering is concurrency-safe;
- seeded users and demo records are available;
- foundation tests pass.

---

# Phase 2: Clients, Vendors, and Projects

## Goal

Build the core project workspace.

## Client Resource

Fields:

- company name;
- primary contact;
- email;
- phone;
- billing address;
- tax or registration identifier;
- notes;
- status.

Relations:

- contacts;
- projects;
- owner contracts;
- client invoices;
- incoming payments.

## Vendor Resource

Fields:

- company name;
- vendor type;
- primary contact;
- email;
- phone;
- billing address;
- tax identifier;
- insurance expiration date;
- active status;
- notes.

Relations:

- subcontracts;
- purchase orders;
- subcontractor invoices;
- outgoing payments;
- compliance records.

## Project Resource

Fields:

- generated job number;
- name;
- client;
- description;
- address;
- project manager;
- start date;
- expected completion date;
- actual completion date;
- status;
- internal notes.

## Project Table

Include:

- job number;
- name;
- client;
- project manager;
- status;
- start date;
- expected completion;
- revised owner contract value;
- projected margin;
- warning indicator.

Provide filters for:

- status;
- project manager;
- client;
- overdue or warning state.

## Project View Page

Create a rich project overview.

Suggested widgets:

- original owner contract value;
- approved owner changes;
- revised contract value;
- client invoiced;
- client payments received;
- committed subcontract cost;
- approved subcontract changes;
- subcontractor invoiced;
- subcontractor paid;
- projected gross margin;
- projected margin percentage.

Also show an attention panel with:

- change orders awaiting approval;
- overdue invoices;
- expired or expiring compliance records;
- pending timesheets;
- unsigned contracts.

## Acceptance Criteria

- user can create a client;
- user can create a project;
- job number is generated automatically;
- project manager can be assigned;
- project team can be managed;
- unauthorized users cannot see unrelated projects;
- project overview renders with zero-state financial figures;
- project tests pass.

---

# Phase 3: Owner Contracts and Subcontracts

## Goal

Implement the core financial commitments.

## Contract Model

Use a shared contracts table with a type field.

Suggested fields:

```text
project_id
client_id nullable
vendor_id nullable
type
number
title
description
status
original_amount
retention_percentage
executed_at
start_date
completion_date
created_by
approved_by nullable
timestamps
```

Use constraints or validation so:

- owner contracts belong to clients;
- subcontracts and purchase orders belong to vendors;
- contracts belong to projects.

## Contract Line Items

Implement a Schedule of Values.

Suggested fields:

```text
contract_id
cost_code nullable
description
quantity nullable
unit nullable
unit_price nullable
amount
sort_order
```

The contract original amount should either:

- be derived from line items; or
- be validated to match the line item total.

Prefer a single source of truth.

## Owner Contract Workflow

Suggested workflow:

```text
Draft
→ Pending Approval
→ Executed
→ Closed
```

Rules:

- draft contracts are editable;
- executed contracts lock original financial fields;
- original value cannot be changed after execution;
- later financial changes occur through change orders;
- only authorized roles can execute a contract.

## Subcontract Workflow

Use the same broad workflow, but keep policy and service logic separate where business behavior differs.

## Filament Interface

Create:

- Owner Contract resource or project nested resource;
- Subcontract resource or project nested resource;
- contract overview page;
- Schedule of Values manager;
- related change orders;
- related invoices;
- related payments;
- documents.

Use full pages for complex contract editing rather than putting all financial data into one modal.

## Acceptance Criteria

- owner contract can be created for a project;
- subcontract can be created for a vendor;
- Schedule of Values can be managed;
- line item totals calculate correctly;
- executed contracts become locked;
- unauthorized users cannot execute contracts;
- project dashboard reflects original contract and subcontract values;
- contract workflow tests pass.

---

# Phase 4: Change Orders

## Goal

Implement a meaningful approval workflow that changes financial values only after approval.

## Change Order Fields

```text
project_id
contract_id
type
number
title
description
status
requested_at
submitted_at
approved_at
rejected_at
schedule_impact_days
created_by
approved_by nullable
rejection_reason nullable
timestamps
```

## Change Order Line Items

```text
change_order_id
contract_line_item_id nullable
cost_code nullable
description
amount
sort_order
```

## Workflow

```text
Draft
→ Submitted
→ Approved
or
→ Rejected
```

Rules:

- draft records are editable;
- submitted records are locked except for review fields;
- only approved change orders affect revised contract values;
- rejected change orders do not affect financial totals;
- approval must be executed in a database transaction;
- approval should create an activity log entry;
- approval should be idempotent;
- an approved change order cannot be approved twice;
- approved records should not be silently edited.

## Financial Calculations

Owner contract:

```text
Revised owner contract value
= Original owner contract value
+ Approved owner change orders
```

Subcontract:

```text
Revised subcontract value
= Original subcontract value
+ Approved subcontract change orders
```

Project margin:

```text
Projected gross margin
= Revised owner contract value
- Total revised subcontract commitments
```

```text
Projected margin percentage
= Projected gross margin / Revised owner contract value
```

Handle division by zero safely.

## Filament Features

Use:

- status badges;
- conditional actions;
- submit action;
- approve action;
- reject action with required reason;
- confirmation modals;
- line-item repeater or table;
- authorization per action;
- notifications;
- activity history.

## Acceptance Criteria

- draft change order can be edited;
- submitted change order cannot be freely edited;
- approval changes revised contract value;
- rejection does not change contract value;
- project margin updates after approval;
- approval action is idempotent;
- financial calculations are tested;
- workflow authorization is tested.

---

# Phase 5: Client Invoicing

## Goal

Implement construction-style progress invoicing against the owner contract Schedule of Values.

## Invoice Fields

```text
project_id
contract_id
type
number
status
billing_period_start
billing_period_end
issue_date
due_date
subtotal
retention_amount
total
sent_at nullable
approved_at nullable
created_by
approved_by nullable
notes nullable
timestamps
```

## Invoice Line Items

Each line should reference a contract line item.

Suggested fields:

```text
invoice_id
contract_line_item_id
scheduled_value
previously_billed
work_completed_this_period
materials_stored
retention_amount
current_due
sort_order
```

Derived values should be calculated consistently.

## Validation Rules

- invoice must belong to the same project as the contract;
- client invoice must target an owner contract;
- invoice line item cannot exceed the remaining billable contract amount;
- negative billing is prohibited unless explicitly supported;
- duplicate billing of the same amount must be prevented;
- finalized invoice values should be locked;
- voiding an invoice should preserve history.

## Workflow

```text
Draft
→ Approved
→ Sent
→ Partially Paid
→ Paid
```

For the MVP:

- invoices can be generated;
- invoices can be marked as sent;
- payments are entered manually;
- invoice status updates based on payment totals;
- PDF invoice can be generated.

## PDF

Generate a professional PDF including:

- company information;
- client information;
- project information;
- invoice number;
- billing period;
- Schedule of Values rows;
- totals;
- retention;
- amount due;
- payment instructions;
- notes.

## Acceptance Criteria

- client invoice can be created from an owner contract;
- previous billing is calculated correctly;
- invoice cannot exceed remaining contract value;
- invoice PDF is generated;
- sent timestamp can be recorded;
- project dashboard updates invoiced totals;
- invoice workflow tests pass.

---

# Phase 6: Subcontractor Invoicing

## Goal

Implement the cost-side invoice workflow.

## First MVP

Internal employees enter invoices received from subcontractors.

Fields should be similar to client invoices, but the invoice type is subcontractor and it targets a subcontract or purchase order.

## Workflow

```text
Draft
→ Submitted
→ Approved
or
→ Rejected
→ Partially Paid
→ Paid
```

Rules:

- invoice cannot exceed the remaining subcontract value;
- project manager can review;
- accounting can approve or record payment;
- rejection requires a reason;
- approved values become locked;
- activity is logged.

## Optional Advanced Feature

Create line-level review:

- approve or reject individual invoice lines;
- reviewer comments;
- bulk approve;
- resubmission.

Only implement this after the invoice-level workflow is stable.

## Acceptance Criteria

- vendor invoice can be entered against a subcontract;
- invoice cannot exceed the revised subcontract value;
- invoice can be submitted, approved, or rejected;
- rejection reason is stored;
- project cost totals update;
- authorization tests pass.

---

# Phase 7: Payments

## Goal

Support partial and full payments without reducing payment state to a single invoice status field.

## Payment Fields

```text
invoice_id
project_id
direction
amount
payment_date
method
reference
notes
recorded_by
timestamps
```

## Rules

- incoming payments belong to client invoices;
- outgoing payments belong to subcontractor invoices;
- payment amount must be positive;
- cumulative payments cannot exceed invoice total unless overpayment support is intentionally implemented;
- creating or deleting a payment recalculates invoice status;
- all changes are logged.

## Status Calculation

```text
No payments
→ Sent or Approved

Paid amount > 0 and < invoice total
→ Partially Paid

Paid amount >= invoice total
→ Paid
```

## Dashboard Calculations

Display:

```text
Client invoices issued
Client payments received
Client accounts receivable
Remaining amount to bill

Subcontractor invoices approved
Subcontractor payments made
Outstanding payables
Remaining subcontract commitments
```

## Acceptance Criteria

- partial payment can be recorded;
- invoice becomes partially paid;
- final payment changes invoice to paid;
- overpayment is blocked;
- project dashboard updates immediately;
- payment tests pass.

---

# Phase 8: Documents and Google Drive

## Goal

Attach documents to projects and financial records, with optional Google Drive synchronization.

## Document Model

Use a polymorphic relationship where appropriate.

Suggested fields:

```text
project_id
documentable_type nullable
documentable_id nullable
name
description nullable
category
storage_disk
storage_path nullable
google_drive_file_id nullable
google_drive_folder_id nullable
external_url nullable
mime_type nullable
file_size nullable
uploaded_by
timestamps
```

## Suggested Categories

```text
Contract
Change Order
Client Invoice
Subcontractor Invoice
Plan
Compliance
Photo
General
```

## MVP Storage Strategy

Implement local or S3-compatible storage first.

Then add a Google Drive integration layer.

## Google Drive Behavior

When a project is created, optionally create:

```text
/Construction Projects
    /{PROJECT_NUMBER} {PROJECT_NAME}
        /01 Contracts
        /02 Change Orders
        /03 Client Invoices
        /04 Subcontractor Invoices
        /05 Plans
        /06 Compliance
```

Store Drive identifiers locally.

Provide Filament actions:

- Upload document;
- Open file;
- Open in Google Drive;
- Copy link;
- Replace file;
- Delete document.

Use queued jobs for Drive uploads where useful.

If credentials are not present, the application should continue working using local storage.

## Security

- verify project access before download;
- do not expose private storage URLs directly;
- authorize every document action;
- restrict sensitive financial documents based on permissions.

## Acceptance Criteria

- document can be attached to a project;
- document can be attached to a contract, change order, or invoice;
- unauthorized users cannot access files;
- local storage works without Google Drive;
- Google Drive adapter is isolated behind a service;
- document tests pass.

---

# Phase 9: Time Entries

## Goal

Support employee labor tracking and approval.

## Time Entry Fields

```text
employee_id
project_id
work_date
regular_hours
overtime_hours
is_billable
description
status
submitted_at nullable
approved_at nullable
approved_by nullable
rejection_reason nullable
timestamps
```

## Workflow

```text
Draft
→ Submitted
→ Approved
or
→ Rejected
```

## Filament Interface

Create:

- employee time entry resource;
- weekly timesheet page;
- manager approval queue;
- project labor summary;
- filters by employee, project, date, and status.

Employees should only manage their own time entries unless authorized otherwise.

## Validation

- hours cannot be negative;
- total daily hours should have a sensible configurable maximum;
- approved entries should be locked;
- employee must have access to the selected project;
- duplicate entries should be prevented or clearly handled.

## Acceptance Criteria

- employee can submit own time;
- manager can approve or reject;
- approved entries are locked;
- project labor totals are visible;
- unauthorized employees cannot submit time to unrelated projects;
- time entry tests pass.

---

# Phase 10: Sick Leave and Compliance

## Goal

Implement a limited, clearly non-jurisdiction-specific compliance module.

## Sick Leave Ledger

Suggested fields:

```text
employee_id
type
hours
effective_date
notes
created_by
timestamps
```

Types:

```text
Accrual
Usage
Adjustment
```

Balance:

```text
Accruals + Adjustments - Usage
```

Do not claim jurisdiction-specific legal compliance.

## Compliance Requirements

Examples:

- employee safety training;
- equipment certification;
- vendor insurance certificate;
- business license;
- safety documentation.

## Compliance Record Fields

```text
compliance_requirement_id
employee_id nullable
vendor_id nullable
project_id nullable
document_id nullable
issued_at nullable
expires_at nullable
status
notes
verified_by nullable
verified_at nullable
timestamps
```

## Dashboard Warnings

Show:

- expired records;
- records expiring within 30 days;
- missing required records;
- vendors blocked from project work due to missing compliance.

## Acceptance Criteria

- compliance records can be created;
- expiration status is calculated;
- dashboard displays warning counts;
- sick leave balance is calculated;
- sensitive HR data is permission-restricted;
- compliance tests pass.

---

# Phase 11: Dashboard and Reporting

## Goal

Make the finished application visually strong and useful for the video demonstration.

## Main Dashboard Widgets

Include:

- active projects;
- total revised contract value;
- outstanding receivables;
- outstanding payables;
- projected gross margin;
- overdue invoices;
- pending change orders;
- pending timesheets;
- expiring compliance records.

## Project Dashboard

Display:

```text
Original owner contract
Approved owner changes
Revised owner contract
Client invoices
Client payments
Accounts receivable
Remaining to bill

Original subcontract commitments
Approved subcontract changes
Revised commitments
Subcontractor invoices
Subcontractor payments
Accounts payable
Remaining commitments

Projected gross margin
Projected margin percentage
```

## Optional Charts

Keep charts simple and useful:

- invoiced versus paid by month;
- project revenue versus committed cost;
- contract value changes over time;
- outstanding invoices by aging bucket.

## Reporting Tables

Provide:

- overdue invoices;
- pending approvals;
- upcoming compliance expirations;
- projects by status;
- projects with lowest projected margin.

## Acceptance Criteria

- dashboard figures match underlying records;
- project financial calculations are consistent;
- no N+1 query problems in major dashboard widgets;
- widgets respect user permissions;
- dashboard tests pass.

---

# Phase 12: Optional Subcontractor Portal

## Goal

Add a second Filament panel for external subcontractors.

Only implement after the internal MVP is stable.

Suggested path:

```text
/subcontractor
```

## Portal Features

A subcontractor can:

- view their own company profile;
- view assigned projects;
- view their subcontracts;
- view approved change orders;
- submit invoices;
- upload supporting documents;
- view rejected invoices;
- resubmit corrected invoices;
- view payment status.

A subcontractor cannot:

- view other vendors;
- view project revenue;
- view owner contracts;
- view client invoices;
- see internal margin data;
- modify executed contracts.

## Acceptance Criteria

- vendor user sees only their own records;
- cross-vendor access is impossible;
- invoice submission works;
- document upload works;
- rejection and resubmission work;
- portal authorization tests pass.

---

# Filament Feature Mapping

Use the finished application to demonstrate these Filament concepts.

## Resources

Use for:

- clients;
- vendors;
- projects;
- contracts;
- change orders;
- invoices;
- payments;
- users;
- compliance records.

## Relation Managers

Use for:

- project team;
- client contacts;
- contract line items;
- project documents;
- project time entries;
- compliance records;
- small related datasets.

## Nested or Dedicated Resource Pages

Use for:

- owner contracts;
- subcontracts;
- change orders;
- client invoices;
- subcontractor invoices.

## Custom Pages

Use for:

- project financial overview;
- weekly timesheet entry;
- invoice review;
- payment recording;
- dashboard reporting.

## Widgets

Use for:

- project financial KPIs;
- overdue invoices;
- pending approvals;
- expiring compliance;
- revenue and cost summaries.

## Actions

Use for:

- submit for approval;
- approve;
- reject;
- execute contract;
- mark invoice as sent;
- record payment;
- generate PDF;
- upload to Google Drive;
- copy external link;
- close project.

## Notifications

Use for:

- successful approvals;
- rejected records;
- overdue invoice reminders;
- compliance expiration warnings;
- pending timesheet reminders.

## Infolists

Use on read-only financial detail pages and summary sections.

## Tables

Use:

- badges;
- filters;
- grouped records;
- summaries;
- money formatting;
- conditional actions;
- bulk actions where safe.

---

# Service Layer

Create focused services rather than placing all logic inside Filament actions.

Suggested services:

```text
NumberSequenceService
ContractCalculationService
ChangeOrderApprovalService
InvoiceCalculationService
InvoiceWorkflowService
PaymentService
ProjectFinancialSummaryService
DocumentStorageService
GoogleDriveService
TimeEntryApprovalService
ComplianceStatusService
```

Each service should:

- have a narrow responsibility;
- use transactions for state changes;
- be independently testable;
- throw clear domain exceptions;
- avoid depending directly on Filament.

Filament actions should call these services and display the result.

---

# Key Business Rules

The implementation must preserve these rules.

1. Draft financial records may be edited.
2. Executed or approved records are locked.
3. Original contract values cannot be silently changed after execution.
4. Only approved change orders affect revised contract totals.
5. Invoice totals cannot exceed the remaining billable contract amount.
6. Subcontractor invoices cannot exceed the revised subcontract commitment.
7. Payments can be partial.
8. Cumulative payments cannot exceed the invoice total.
9. Financial state changes must be logged.
10. Authorization must be enforced by policies, not only hidden UI elements.
11. Project access must be scoped by assignment where required.
12. External vendors must never see unrelated vendor or owner financial data.
13. Number generation must be concurrency-safe.
14. Dashboard figures must come from shared calculation services.
15. Money must not use floating-point arithmetic.

---

# Required Automated Tests

Prioritize domain and authorization tests over superficial table-column tests.

## Authorization

```text
Employee cannot view an unassigned project.
Site supervisor cannot see confidential financial widgets.
Project manager can access assigned project financials.
Accounting can manage invoices and payments.
Vendor user cannot see another vendor's records.
Read-only executive cannot modify records.
```

## Numbering

```text
Projects receive unique sequential numbers.
Concurrent number generation cannot produce duplicates.
Sequence can reset by year when configured.
Contract and invoice formats include project number.
```

## Contracts

```text
Schedule of Values totals correctly.
Executed contract cannot change original value.
Only authorized user can execute contract.
Subcontract must belong to a vendor.
Owner contract must belong to a client.
```

## Change Orders

```text
Draft change order does not affect revised value.
Submitted change order does not affect revised value.
Approved change order updates revised value.
Rejected change order does not affect revised value.
Approval is idempotent.
Unauthorized user cannot approve.
```

## Invoices

```text
Invoice previous billing is calculated correctly.
Invoice cannot exceed remaining contract value.
Subcontractor invoice cannot exceed revised commitment.
Approved invoice values are locked.
Void preserves historical record.
```

## Payments

```text
Partial payment changes status to Partially Paid.
Final payment changes status to Paid.
Overpayment is rejected.
Deleting a payment recalculates invoice status.
Incoming and outgoing directions are validated.
```

## Project Dashboard

```text
Projected gross margin is correct.
Margin percentage is correct.
Zero owner contract value does not cause division error.
Outstanding receivable is correct.
Outstanding payable is correct.
Only approved financial records are included.
```

## Documents

```text
User without project access cannot download document.
Document can belong to project.
Document can belong to contract.
Local storage works without Drive credentials.
```

## Time and Compliance

```text
Employee can submit own time.
Employee cannot submit time for unrelated project.
Approved time entry is locked.
Expired compliance status is detected.
Expiring-soon status is detected.
Sick leave balance is calculated correctly.
```

---

# Demo Data

Create a high-quality demo scenario.

## Company

```text
Summit General Contractors
```

## Client

```text
Riverside Property Group
```

## Project

```text
JOB-2026-0042
Riverside Office Renovation
Status: Active
```

## Owner Contract

```text
Original value: $500,000
```

Schedule of Values:

```text
General Conditions       $40,000
Demolition               $45,000
Concrete                 $90,000
Electrical               $110,000
Plumbing                  $75,000
Finishes                 $120,000
Closeout                  $20,000
```

## Subcontracts

```text
Electrical subcontract   $75,000
Plumbing subcontract     $50,000
Flooring subcontract     $60,000
```

## Change Orders

```text
Approved owner change order:
Upgraded flooring        +$15,000

Approved flooring subcontract change:
Upgraded flooring        +$10,000

Submitted owner change order:
Additional lighting      +$8,000
```

Only approved changes should affect financial totals.

## Invoices

```text
Client invoices issued:          $300,000
Client payments received:        $250,000

Subcontractor invoices approved: $180,000
Subcontractor payments made:     $150,000
```

## Expected Summary

```text
Original owner contract:       $500,000
Approved owner changes:         $15,000
Revised owner contract:        $515,000

Original subcontract cost:     $185,000
Approved subcontract changes:   $10,000
Revised committed cost:        $195,000

Projected gross margin:        $320,000
Projected margin percentage:     62.14%
```

The exact demo values may be refined, but all seeded figures must reconcile.

Also seed:

- one overdue client invoice;
- one partially paid invoice;
- one pending change order;
- one expired vendor insurance document;
- one compliance record expiring soon;
- several pending timesheets.

---

# Suggested Implementation Order

Implement in this order:

1. Foundation, permissions, enums, activity log.
2. Number sequence service.
3. Clients and vendors.
4. Projects and project assignments.
5. Project overview shell.
6. Owner contracts.
7. Subcontracts.
8. Schedule of Values.
9. Change orders.
10. Project financial summary service.
11. Client invoices.
12. Subcontractor invoices.
13. Payments.
14. PDF generation.
15. Documents.
16. Google Drive integration.
17. Time entries.
18. Sick leave.
19. Compliance.
20. Main dashboard and reporting.
21. Optional subcontractor portal.
22. Final seed data and polish.
23. Full test suite.
24. README and architecture documentation.

Do not begin optional portal work before the internal financial workflows are stable and tested.

---

# Scope to Exclude

Do not implement these in the primary MVP:

- Gantt charts;
- detailed project scheduling;
- RFIs;
- submittals;
- drawing markup;
- estimating;
- bidding;
- payroll;
- certified payroll;
- jurisdiction-specific labor compliance;
- lien waivers;
- accounting platform synchronization;
- bank feeds;
- Stripe or online checkout;
- electronic signatures;
- offline mobile mode;
- real-time chat;
- advanced cost-code accounting;
- multiple tiers of potential change orders;
- complete client portal.

Leave clean extension points where reasonable, but do not build speculative abstractions.

---

# Final Deliverables

The completed project should include:

1. Working Laravel 13 and Filament 5 application.
2. Database migrations.
3. Models and relationships.
4. Enums.
5. Policies.
6. Filament resources, pages, relation managers, widgets, and actions.
7. Domain services.
8. Factories and realistic seeders.
9. Pest test suite.
10. PDF invoice generation.
11. Local document storage.
12. Optional Google Drive integration.
13. Project dashboard.
14. Main management dashboard.
15. README with setup instructions.
16. Architecture overview.
17. Demo credentials.
18. Explanation of intentionally excluded scope.

---

# README Requirements

Document:

- installation;
- environment variables;
- migrations;
- seeding;
- demo credentials;
- queue worker requirements;
- PDF dependencies;
- Google Drive setup;
- storage setup;
- test command;
- code quality commands;
- role descriptions;
- important workflows;
- known limitations.

Include a section titled:

```text
How This Project Demonstrates Filament
```

Map each major business feature to the Filament feature used.

---

# Completion Checklist

The project is complete when this vertical slice works end to end:

```text
Create client
→ Create project
→ Generate job number
→ Assign project manager
→ Create owner contract
→ Add Schedule of Values
→ Create two subcontracts
→ Approve owner change order
→ Approve subcontract change order
→ Generate progress invoice
→ Record partial client payment
→ Enter subcontractor invoice
→ Record outgoing payment
→ Upload supporting document
→ Submit employee time
→ Approve timesheet
→ View updated project financial dashboard
```

The dashboard must clearly show:

```text
Original contract
Approved changes
Revised contract
Client invoiced
Client paid
Accounts receivable
Committed subcontract cost
Subcontractor invoiced
Subcontractor paid
Accounts payable
Projected gross margin
Projected margin percentage
```

All important business rules and authorization boundaries must be covered by automated tests.

---

# Agent Working Method

For each phase:

1. inspect the existing implementation;
2. propose the schema and affected files;
3. implement the smallest complete vertical slice;
4. add factories and seed data;
5. add automated tests;
6. run relevant tests;
7. run formatting and static analysis tools available in the project;
8. fix failures;
9. summarize what was implemented;
10. document any deliberate deviations from this specification.

Do not generate all resources first and postpone business logic.

Prioritize complete, tested workflows over a large number of unfinished CRUD screens.
