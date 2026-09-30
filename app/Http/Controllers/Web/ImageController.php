<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ImageType;
use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\ListItem;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unified controller for serving images.
 */
final class ImageController extends Controller
{
    /**
     * Serve an image by type and UUID.
     */
    public function show(Request $request): StreamedResponse
    {
        /** @var string $typeValue */
        $typeValue = $request->route('type');
        /** @var string $uuid */
        $uuid = $request->route('uuid');

        $type = ImageType::from($typeValue);
        $stepId = $request->route('step');

        return match ($type) {
            ImageType::ListItem => $this->serveListItem($uuid),
            ImageType::Household => $this->serveHousehold($uuid),
            ImageType::Recipe, ImageType::RecipeStep => $this->serveRecipe($uuid, $stepId),
            ImageType::Generated => $this->serveGenerated($uuid),
        };
    }

    private function serveListItem(string $uuid): StreamedResponse
    {
        $item = ListItem::find($uuid);
        abort_unless($item, 404);

        $user = $this->authenticatedUser(request());
        $list = $item->list;
        abort_unless($list && $list->created_by === $user->id, 403);

        $path = $item->image_url;
        abort_unless($path, 404);

        return Storage::disk('local')->response($path);
    }

    private function serveHousehold(string $uuid): StreamedResponse
    {
        $household = Household::find($uuid);
        abort_unless($household, 404);

        $user = $this->authenticatedUser(request());
        abort_unless($household->isMember($user), 403);

        $path = $household->image_url;
        abort_unless($path, 404);

        return Storage::disk('local')->response($path);
    }

    private function serveRecipe(string $uuid, ?string $stepId): StreamedResponse
    {
        $recipe = Recipe::find($uuid);
        abort_unless($recipe, 404);

        $user = $this->authenticatedUser(request());
        abort_unless($user->can('view', $recipe), 403);

        if ($stepId) {
            $step = $recipe->steps()->where('id', $stepId)->first();
            abort_unless($step && $step->image_path, 404);
            $path = $step->image_path;
        } else {
            $path = $recipe->cover_image_path;
            abort_unless($path, 404);
        }

        return Storage::disk('local')->response($path);
    }

    private function serveGenerated(string $uuid): StreamedResponse
    {
        $path = "images/generated/{$uuid}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
