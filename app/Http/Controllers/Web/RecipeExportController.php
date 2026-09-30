<?php

namespace App\Http\Controllers\Web;

use App\Actions\Recipes\GetRecipe;
use App\Data\Recipes\RecipeData;
use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Services\Recipes\Exporter\CooklangRecipeExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class RecipeExportController extends Controller
{
    public function cooklang(Recipe $recipe, GetRecipe $action, CooklangRecipeExporter $exporter, Request $request): Response
    {
        abort_unless($this->authenticatedUser($request)->can('export', $recipe), 403);

        $recipeData = $action->execute($recipe)['recipe'];

        $recipeDataDto = RecipeData::fromModel($recipeData);

        $content = $exporter->export($recipeDataDto);

        $filename = Str::slug($recipeDataDto->name).'.cook';

        return response($content, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="'.($filename === '.cook' ? 'recipe.cook' : $filename).'"');
    }
}
