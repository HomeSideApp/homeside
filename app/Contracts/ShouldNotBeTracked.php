<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Jobs implementing this interface are ignored by the job monitoring system.
 */
interface ShouldNotBeTracked {}
