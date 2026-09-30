<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\JobMonitor;
use Illuminate\Queue\Events\JobProcessing;

class JobProcessingListener
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
    public function handle(JobProcessing $event): void
    {
        $this->jobMonitor->recordJobStart($event);
    }
}
