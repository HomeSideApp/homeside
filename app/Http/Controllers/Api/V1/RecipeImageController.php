<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageRecipeRequest;
use App\Http\Requests\UploadRecipeImageRequest;
use App\Http\Requests\UploadRecipeStepImageRequest;
use App\Http\Requests\ViewRecipeRequest;
use App\Models\Recipe;
use App\Models\RecipeStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves authorized recipe cover and step images through the API.
 */
final class RecipeImageController extends Controller
{
    public function store(Recipe $recipe, UploadRecipeImageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->replaceImage($recipe, 'cover_image_path', $validated['image']->store("recipes/{$recipe->id}", 'local'));

        return response()->json(['data' => [
            'image_url' => route('api.v1.recipes.image', $recipe),
            'updated_at' => $recipe->updated_at?->toISOString(),
        ]], 201);
    }

    public function destroy(Recipe $recipe, ManageRecipeRequest $request): Response
    {
        $this->deleteImage($recipe->cover_image_path);
        $recipe->update(['cover_image_path' => null]);

        return response()->noContent();
    }

    public function storeStep(Recipe $recipe, UploadRecipeStepImageRequest $request): JsonResponse
    {
        $step = $this->resolveStep($recipe, $request);
        $validated = $request->validated();

        $this->replaceImage($step, 'image_path', $validated['image']->store("recipes/{$recipe->id}/steps", 'local'));

        return response()->json(['data' => [
            'image_url' => route('api.v1.recipes.steps.image', [$recipe, $step]),
            'updated_at' => $step->updated_at?->toISOString(),
        ]], 201);
    }

    public function destroyStep(Recipe $recipe, ManageRecipeRequest $request): Response
    {
        $step = $this->resolveStep($recipe, $request);
        $this->deleteImage($step->image_path);
        $step->update(['image_path' => null]);

        return response()->noContent();
    }

    /**
     * Serve a recipe cover image or one of its step images.
     */
    public function show(Recipe $recipe, ViewRecipeRequest $request): Response
    {

        $stepId = $request->route('step');

        if (is_string($stepId)) {
            $imagePath = $recipe->steps()
                ->whereKey($stepId)
                ->whereNotNull('image_path')
                ->value('image_path');
        } else {
            $imagePath = $recipe->cover_image_path;
        }

        abort_unless(is_string($imagePath) && Storage::exists($imagePath), 404);

        return response()->file(Storage::path($imagePath));
    }

    private function resolveStep(Recipe $recipe, Request $request): RecipeStep
    {
        return $recipe->steps()->whereKey($request->route('step'))->firstOrFail();
    }

    private function replaceImage(Recipe|RecipeStep $model, string $attribute, string $path): void
    {
        $this->deleteImage($model->getAttribute($attribute));
        $model->update([$attribute => $path]);
    }

    private function deleteImage(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
