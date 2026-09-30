<?php

declare(strict_types=1);

namespace App\Images\Strategies;

use App\Images\Contracts\ImageTypeStrategy;
use App\Models\Household;
use App\Models\User;

/**
 * Strategy for household images.
 */
final class HouseholdImage implements ImageTypeStrategy
{
    /**
     * {@inheritdoc}
     */
    public function resolvePath(string $uuid): ?string
    {
        return Household::where('id', $uuid)->value('image_url');
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(User $user, string $uuid): bool
    {
        $household = Household::find($uuid);

        return $household && $household->isMember($user);
    }

    /**
     * {@inheritdoc}
     */
    public function disk(): string
    {
        return 'local';
    }
}
