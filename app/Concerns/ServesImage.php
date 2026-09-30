<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\ImageType;
use App\Images\ImageResolver;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Trait for serving images through a unified system.
 */
trait ServesImage
{
    /**
     * Serve an image based on its type and UUID.
     *
     * @param  ImageType  $type  The type of image.
     * @param  string  $uuid  The UUID of the model or identifier.
     * @param  string|null  $stepId  Optional step ID for recipe step images.
     */
    protected function serveImage(
        ImageType $type,
        string $uuid,
        ?string $stepId = null
    ): Response {
        $resolver = app(ImageResolver::class);
        $strategy = $resolver->resolve($type, $stepId);

        // Resolve path
        $path = $strategy->resolvePath($uuid);
        abort_unless($path, 404);

        // Authorize access
        $disk = $strategy->disk();
        $user = $this->authenticatedUser(request());
        abort_unless($strategy->authorize($user, $uuid), 403);

        // Serve file - let Laravel handle 404 if file doesn't exist
        try {
            return Storage::disk($disk)->download($path);
        } catch (\Exception $e) {
            abort(404);
        }
    }
}
