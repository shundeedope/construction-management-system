<ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
    @forelse ($activities as $activity)
        <li>{{ str($activity->event)->replace('_', ' ')->ucfirst() }} — {{ $activity->created_at->toFormattedDateString() }}</li>
    @empty
        <li>No workflow activity has been recorded.</li>
    @endforelse
</ul>
