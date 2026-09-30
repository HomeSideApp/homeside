<?php

declare(strict_types=1);

namespace App\Images\Contracts;

use App\Models\User;

/**
 * Contract for image type strategies.
 *
 * Each image type implements this contract to provide
 * path resolution, authorization, and disk configuration.
 */
interface ImageTypeStrategy
{
    /**
     * Resolve the image path from the UUID.
     *
     * @param  string  $uuid  The UUID of the model or identifier.
     * @return string|null The path to the image file.
     */
    public function resolvePath(string $uuid): ?string;

    /**
     * Check if the user has permission to access this image.
     *
     * @param  User  $user  The authenticated user.
     * @param  string  $uuid  The UUID of the model or identifier.
     * @return bool True if authorized, false otherwise.
     */
    public function authorize(User $user, string $uuid): bool;

    /**
     * Get the disk where the image is stored.
     *
     * @return string The filesystem disk name.
     */
    public function disk(): string;
}
