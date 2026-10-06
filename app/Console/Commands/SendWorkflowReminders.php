<?php

namespace App\Console\Commands;

use App\Services\WorkflowReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-workflow-reminders')]
#[Description('Send idempotent database reminders for pending workflows')]
class SendWorkflowReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WorkflowReminderService $service): int
    {
        $this->info("Sent {$service->send()} workflow reminders.");

        return self::SUCCESS;
    }
}
