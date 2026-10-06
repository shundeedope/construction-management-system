<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records create/update/delete history for a domain model, limited to the attributes the
 * model lists in `$activityLogAttributes`. Attributes are opt-in so that secrets and
 * credentials can never reach the activity log by being added to a table later.
 */
trait LogsDomainActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(ActivityLogger::LOG_NAME)
            ->logOnly($this->activityLogAttributes)
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
