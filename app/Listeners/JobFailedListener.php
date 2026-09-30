<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\JobMonitor;
use Illuminate\Queue\Events\JobFailed;

class JobFailedListener
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected JobMonitor $jobMonitor
    ) {}

    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        $this->jobMonitor->recordJobFailure($event);
    }
}
