<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `project_user` assignment. It is the second access boundary alongside a user's global
 * role: the flags here decide whether an assigned user may see the project's money or manage
 * its documents.
 *
 * @property int $project_id
 * @property int $user_id
 * @property ProjectRole $project_role
 * @property bool $can_view_financials
 * @property bool $can_manage_documents
 */
class ProjectAssignment extends Pivot
{
    protected $table = 'project_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'project_role' => ProjectRole::class,
            'can_view_financials' => 'boolean',
            'can_manage_documents' => 'boolean',
        ];
    }
}
