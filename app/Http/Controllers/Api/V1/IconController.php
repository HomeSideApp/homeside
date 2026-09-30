<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Icons\ListIcons;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Class IconController
 *
 * This controller handles the API endpoints related to icons, exposing the
 * list of available icons.
 */
final class IconController extends Controller
{
    /**
     * Return the list of available icons.
     */
    public function index(ListIcons $action): JsonResponse
    {
        return response()->json($action->execute());
    }
}
