<?php

declare(strict_types=1);

namespace App\Images\Strategies;

use App\Images\Contracts\ImageTypeStrategy;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Strategy for AI-generated images.
 */
final class GeneratedImage implements ImageTypeStrategy
{
    /**
     * {@inheritdoc}
     */
    public function resolvePath(string $uuid): ?string
    {
        $path = "images/generated/{$uuid}";

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(User $user, string $uuid): bool
    {
        // For now, any authenticated user can view generated images.
        // Can be restricted to admin in the future.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function disk(): string
    {
        return 'local';
    }
}
