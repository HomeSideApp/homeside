<?php

declare(strict_types=1);

namespace App\Contracts;

interface HasUserContext
{
    /**
     * Get the user ID associated with this job.
     */
    public function getUserId(): ?string;
}
