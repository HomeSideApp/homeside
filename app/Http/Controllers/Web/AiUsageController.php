<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the AI usage dashboard.
 */
final class AiUsageController extends Controller
{
    /**
     * Show AI usage metrics, filters and recent runs.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Response The HTTP response.
     */
    public function index(Request $request): Response
    {
        $query = AiRun::query();

        // Filtros
        if ($request->filled('agent')) {
            $query->forAgent($request->input('agent'));
        }

        if ($request->filled('provider_name')) {
            $query->where('provider_name', $request->input('provider_name'));
        }

        if ($request->filled('model_name')) {
            $query->where('model_name', $request->input('model_name'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to').' 23:59:59');
        }

        // Métricas aggregadas
        $summary = (clone $query)->selectRaw('
            COUNT(*) as total_runs,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as success_runs,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as error_runs,
            SUM(input_tokens) as total_input_tokens,
            SUM(output_tokens) as total_output_tokens,
            SUM(CASE WHEN input_tokens IS NULL OR output_tokens IS NULL THEN 1 ELSE 0 END) as unknown_token_runs,
            AVG(duration_ms) as avg_duration_ms,
            MAX(duration_ms) as max_duration_ms
        ', ['ok', 'error'])->first();

        // Runs por día (últimos 30 días)
        $dailyUsage = (clone $query)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as runs')
            ->selectRaw('SUM(input_tokens) as input_tokens')
            ->selectRaw('SUM(output_tokens) as output_tokens')
            ->selectRaw('SUM(CASE WHEN input_tokens IS NULL OR output_tokens IS NULL THEN 1 ELSE 0 END) as unknown_token_runs')
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Runs recientes
        $recentRuns = $query->latest()
            ->limit(50)
            ->get()
            ->map(fn (AiRun $run) => [
                'id' => $run->id,
                'agent' => $run->agent,
                'provider_name' => $run->provider_name,
                'model_name' => $run->model_name,
                'input_tokens' => $run->input_tokens,
                'output_tokens' => $run->output_tokens,
                'duration_ms' => $run->duration_ms,
                'status' => $run->status,
                'error_code' => $run->error_code,
                'created_at' => $run->created_at?->toISOString(),
            ]);

        // Valores únicos para filtros
        $agents = AiRun::distinct()->whereNotNull('agent')->pluck('agent')->sort()->values();
        $providers = AiRun::distinct()->whereNotNull('provider_name')->pluck('provider_name')->sort()->values();
        $models = AiRun::distinct()->whereNotNull('model_name')->pluck('model_name')->sort()->values();

        return Inertia::render('admin/AiUsage', [
            'summary' => $summary,
            'dailyUsage' => $dailyUsage,
            'recentRuns' => $recentRuns,
            'filters' => $request->only(['agent', 'provider_name', 'model_name', 'status', 'date_from', 'date_to']),
            'filterOptions' => [
                'agents' => $agents,
                'providers' => $providers,
                'models' => $models,
            ],
        ]);
    }
}
