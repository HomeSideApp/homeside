<?php

namespace App\Http\Controllers\Web;

use App\Actions\Categories\ListCategories;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Serves the list of categories.
 */
final class CategoryController extends Controller
{
    /**
     * List all categories.
     *
     * @param  ListCategories  $action  The action responsible for the operation.
     * @return JsonResponse The JSON response.
     */
    public function index(ListCategories $action): JsonResponse
    {
        return response()->json($action->execute());
    }
}
