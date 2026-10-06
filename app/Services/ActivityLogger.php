<?php

namespace App\Services;

use App\Models\Project;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

/**
 * Records workflow transitions (approvals, rejections, execution, voiding, payments, document
 * actions) with the actor, the subject, the old and new state, and the project context.
 *
 * Transitions are logged through this service rather than the model's attribute-level logging
 * so that a retried, already-applied transition does not produce a second log entry.
 */
class ActivityLogger
{
    public const LOG_NAME = 'domain';

    /**
     * Log a state transition. Returns null when the state did not actually change, which keeps
     * idempotent retries of a workflow action from duplicating history.
     *
     * @param  array<string, mixed>  $context
     */
    public function logTransition(
        Model $subject,
        string $event,
        BackedEnum|string|null $from,
        BackedEnum|string|null $to,
        array $context = [],
    ): ?Activity {
        $fromValue = $this->scalar($from);
        $toValue = $this->scalar($to);

        if ($fromValue === $toValue) {
            return null;
        }

        return $this->log($subject, $event, [
            'old' => ['status' => $fromValue],
            'attributes' => ['status' => $toValue],
            ...$context,
        ]);
    }

    /**
     * Log a domain action that is not a state transition, such as recording a payment or
     * downloading a document.
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(Model $subject, string $event, array $properties = []): Activity
    {
        $activity = activity(self::LOG_NAME)
            ->performedOn($subject)
            ->withProperties([
                ...$properties,
                'project_id' => $this->projectIdFor($subject),
            ]);

        if (($causer = Auth::user()) instanceof Model) {
            $activity->causedBy($causer);
        }

        /** @var Activity */
        return $activity->event($event)->log($event);
    }

    /**
     * @return Builder<Activity>
     */
    public function queryFor(Model $subject): Builder
    {
        return Activity::query()
            ->where('log_name', self::LOG_NAME)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    /**
     * All domain activity recorded against any record belonging to the project.
     *
     * @return Builder<Activity>
     */
    public function queryForProject(Project $project): Builder
    {
        return Activity::query()
            ->where('log_name', self::LOG_NAME)
            ->where(fn (Builder $query): Builder => $query
                ->where('properties->project_id', $project->getKey())
                ->orWhere(fn (Builder $subjects): Builder => $subjects
                    ->where('subject_type', $project->getMorphClass())
                    ->where('subject_id', $project->getKey())));
    }

    private function projectIdFor(Model $subject): ?int
    {
        if ($subject instanceof Project) {
            return $subject->getKey();
        }

        $projectId = $subject->getAttribute('project_id');

        return is_numeric($projectId) ? (int) $projectId : null;
    }

    private function scalar(BackedEnum|string|null $state): ?string
    {
        return $state instanceof BackedEnum ? (string) $state->value : $state;
    }
}
