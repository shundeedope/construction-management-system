<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Administrator = 'Administrator';
    case ProjectManager = 'Project Manager';
    case Accounting = 'Accounting';
    case SiteSupervisor = 'Site Supervisor';
    case Employee = 'Employee';
    case Executive = 'Executive';

    public function getLabel(): string
    {
        return $this->value;
    }

    /**
     * The capability matrix seeded for this role. Administrators are granted every
     * capability through a `Gate::before` rule, so their list is exhaustive by design.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrator => Permission::cases(),

            self::ProjectManager => [
                Permission::ViewAnyProject,
                Permission::CreateProject,
                Permission::UpdateProject,
                Permission::ManageProjectTeam,
                Permission::ViewOrganizations,
                Permission::ManageOrganizations,
                Permission::ViewFinancials,
                Permission::ManageContracts,
                Permission::ExecuteContracts,
                Permission::ManageChangeOrders,
                Permission::ApproveChangeOrders,
                Permission::ManageInvoices,
                Permission::ViewDocuments,
                Permission::ManageDocuments,
                Permission::DeleteDocuments,
                Permission::ManageOwnTimeEntries,
                Permission::ViewAllTimeEntries,
                Permission::ApproveTimeEntries,
                Permission::ViewOwnLeave,
                Permission::ViewCompliance,
                Permission::ManageCompliance,
            ],

            // Accounting owns money end-to-end across every project, but no operational
            // project editing: it cannot create, update, or staff a project.
            self::Accounting => [
                Permission::ViewAnyProject,
                Permission::ViewAllProjects,
                Permission::ViewOrganizations,
                Permission::ManageOrganizations,
                Permission::ViewFinancials,
                Permission::ViewAllFinancials,
                Permission::ManageContracts,
                Permission::ManageInvoices,
                Permission::ApproveInvoices,
                Permission::VoidInvoices,
                Permission::ManagePayments,
                Permission::ViewDocuments,
                Permission::ManageDocuments,
                Permission::ManageOwnTimeEntries,
                Permission::ViewOwnLeave,
                Permission::ViewCompliance,
            ],

            // Financial visibility is possible but only on projects where the assignment
            // carries the `can_view_financials` flag.
            self::SiteSupervisor => [
                Permission::ViewAnyProject,
                Permission::ViewOrganizations,
                Permission::ViewFinancials,
                Permission::ViewDocuments,
                Permission::ManageDocuments,
                Permission::ManageOwnTimeEntries,
                Permission::ViewAllTimeEntries,
                Permission::ApproveTimeEntries,
                Permission::ViewOwnLeave,
                Permission::ViewCompliance,
                Permission::ManageCompliance,
            ],

            self::Employee => [
                Permission::ViewAnyProject,
                Permission::ViewDocuments,
                Permission::ManageDocuments,
                Permission::ManageOwnTimeEntries,
                Permission::ViewOwnLeave,
            ],

            // Read only: every viewing capability, no mutating capability.
            self::Executive => [
                Permission::ViewAnyProject,
                Permission::ViewAllProjects,
                Permission::ViewOrganizations,
                Permission::ViewFinancials,
                Permission::ViewAllFinancials,
                Permission::ViewDocuments,
                Permission::ViewAllTimeEntries,
                Permission::ViewCompliance,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return array_map(fn (Permission $permission): string => $permission->value, $this->permissions());
    }
}
