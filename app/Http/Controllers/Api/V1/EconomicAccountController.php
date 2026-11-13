<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Economy\EconomicAccountResource;
use App\Models\EconomicAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cuentas de economía del usuario.
 *
 * Existían sólo como pantallas web (Inertia): la API aceptaba `account_id` al
 * crear un movimiento pero no ofrecía forma de listarlas, así que ningún cliente
 * móvil podía ofrecer la elección. Es el mismo criterio de visibilidad que la web:
 * cuentas propias y activas salvo que se pidan las archivadas.
 */
final class EconomicAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = EconomicAccount::query()
            ->ownedBy($this->authenticatedUser($request)->id)
            ->when($request->input('include_archived') !== '1', fn ($query) => $query->active())
            ->orderBy('name')
            ->get();

        return EconomicAccountResource::collection($accounts);
    }
}
