<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Granular capabilities checked by policies. Project-scoped capabilities still require
 * a `project_user` assignment unless the role also holds the matching `*Any` capability.
 */
enum Permission: string implements HasLabel
{
    // Projects
    case ViewAnyProject = 'view_any_project';
    case ViewAllProjects = 'view_all_projects';
    case CreateProject = 'create_project';
    case UpdateProject = 'update_project';
    case DeleteProject = 'delete_project';
    case ManageProjectTeam = 'manage_project_team';

    // Organizations
    case ViewOrganizations = 'view_organizations';
    case ManageOrganizations = 'manage_organizations';

    // Financials
    case ViewFinancials = 'view_financials';
    case ViewAllFinancials = 'view_all_financials';
    case ManageContracts = 'manage_contracts';
    case ExecuteContracts = 'execute_contracts';
    case ManageChangeOrders = 'manage_change_orders';
    case ApproveChangeOrders = 'approve_change_orders';
    case ManageInvoices = 'manage_invoices';
    case ApproveInvoices = 'approve_invoices';
    case VoidInvoices = 'void_invoices';
    case ManagePayments = 'manage_payments';

    // Documents
    case ViewDocuments = 'view_documents';
    case ManageDocuments = 'manage_documents';
    case DeleteDocuments = 'delete_documents';

    // Labor
    case ManageOwnTimeEntries = 'manage_own_time_entries';
    case ViewAllTimeEntries = 'view_all_time_entries';
    case ApproveTimeEntries = 'approve_time_entries';
    case ViewOwnLeave = 'view_own_leave';
    case ManageLeave = 'manage_leave';
    case ManageEmployees = 'manage_employees';

    // Compliance
    case ViewCompliance = 'view_compliance';
    case ManageCompliance = 'manage_compliance';
    case VerifyCompliance = 'verify_compliance';

    // Administration
    case ManageUsers = 'manage_users';
    case ManageSettings = 'manage_settings';

    public function getLabel(): string
    {
        return str($this->value)->replace('_', ' ')->ucfirst()->toString();
    }
}
