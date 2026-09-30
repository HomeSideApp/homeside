<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\JobMonitor;
use Illuminate\Queue\Events\JobProcessed;

class JobProcessedListener
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
    public function handle(JobProcessed $event): void
    {
        $this->jobMonitor->recordJobSuccess($event);
    }
}
