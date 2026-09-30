<?php

namespace App\Http\Controllers\Web;

use App\Actions\Economy\GetPersonalTotalsStats;
use App\Actions\Economy\ListEconomicTransactions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Economy\EconomicTransactionResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PersonalEconomyOverviewController extends Controller
{
    /**
     * Display the authenticated user's private transactions and cross-household totals.
     *
     * @param  Request  $request  The incoming HTTP request containing optional transaction filters.
     * @param  ListEconomicTransactions  $list  The action used to query the user's visible transactions.
     * @param  GetPersonalTotalsStats  $totals  The action used to calculate the user's personal totals.
     * @return Response The Inertia personal economy overview response.
     */
    public function index(Request $request, ListEconomicTransactions $list, GetPersonalTotalsStats $totals): Response
    {
        return Inertia::render('economy/PersonalIndex', [
            'transactions' => EconomicTransactionResource::collection(
                $list->executePersonal($request->user(), $request->all())
            ),
            'totals' => $totals->execute($request->user(), $request->input('period')),
            'filters' => $request->only(['type', 'from', 'to', 'search', 'perPage']),
        ]);
    }
}
