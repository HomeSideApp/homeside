<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Recipes\PreviewJsonLdRecipeImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewJsonLdRecipeRequest;
use App\Http\Requests\ReviewRecipeImportRequest;
use App\Models\Product;
use App\Models\RecipeCookware;
use App\Services\Recipes\Import\CooklangRecipeImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class RecipeImportController extends Controller
{
    public function previewJsonLd(
        PreviewJsonLdRecipeRequest $request,
        PreviewJsonLdRecipeImport $import,
    ): JsonResponse {
        $url = $request->validated('url');
        $outcome = $import->execute($url);

        if ($outcome['error'] !== null) {
            Log::warning('API recipe import failed to fetch URL', [
                'url' => $this->urlWithoutQuery($url),
                'error' => $outcome['error'],
            ]);

            return $this->importError($outcome['error']);
        }

        $result = $outcome['result'];

        return response()->json([
            'data' => $result->recipe->toArray(),
            'warnings' => array_map(
                fn ($warning) => [
                    'code' => $warning->code,
                    'message' => $warning->message,
                    'field' => null,
                ],
                $result->warnings,
            ),
            'matches' => $result->candidates,
        ]);
    }

    public function previewCooklang(Request $request, CooklangRecipeImporter $importer): JsonResponse
    {
        $request->validate([
            'cooklang' => 'required|string|min:10',
        ]);

        $content = $request->input('cooklang');
        $result = $importer->import($content);

        return response()->json([
            'data' => $result->recipe->toArray(),
            'warnings' => array_map(fn ($warning) => [
                'code' => $warning->code,
                'message' => $warning->message,
                'field' => null,
            ], $result->warnings),
            'matches' => $result->candidates,
        ]);
    }

    public function review(ReviewRecipeImportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $request->validated('recipe'),
            'warnings' => [],
            'catalog' => [
                'products' => Product::query()->orderBy('name')->get(['id', 'name']),
                'cookware' => RecipeCookware::query()
                    ->where('type', 'tool')
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    private function importError(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => 'recipe_import_failed',
            'errors' => (object) [],
        ], 422);
    }

    private function urlWithoutQuery(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        return $parts['scheme'].'://'.$parts['host'].($parts['path'] ?? '');
    }
}
