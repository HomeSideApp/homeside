<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use HomeSide\AiAgents\Models\AiRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminAiUsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'agent' => ['sometimes', 'string', 'max:255'], 'provider_name' => ['sometimes', 'string', 'max:255'],
            'model_name' => ['sometimes', 'string', 'max:255'], 'status' => ['sometimes', Rule::in(['queued', 'running', 'ok', 'success', 'error', 'cancelled'])],
            'date_from' => ['sometimes', 'date_format:Y-m-d'], 'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['sometimes', 'integer', 'min:1'], 'perPage' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = AiRun::query()
            ->when($validated['agent'] ?? null, fn ($query, string $value) => $query->where('agent', $value))
            ->when($validated['provider_name'] ?? null, fn ($query, string $value) => $query->where('provider_name', $value))
            ->when($validated['model_name'] ?? null, fn ($query, string $value) => $query->where('model_name', $value))
            ->when($validated['status'] ?? null, fn ($query, string $value) => $query->where('status', $value))
            ->when($validated['date_from'] ?? null, fn ($query, string $value) => $query->whereDate('created_at', '>=', $value))
            ->when($validated['date_to'] ?? null, fn ($query, string $value) => $query->whereDate('created_at', '<=', $value));
        $summaryRow = (clone $query)->selectRaw('COUNT(*) total_runs, SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) success_runs, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) error_runs, COALESCE(SUM(input_tokens), 0) input_tokens, COALESCE(SUM(output_tokens), 0) output_tokens, SUM(CASE WHEN input_tokens IS NULL OR output_tokens IS NULL THEN 1 ELSE 0 END) unknown_token_runs, COALESCE(AVG(duration_ms), 0) avg_duration_ms, COALESCE(MAX(duration_ms), 0) max_duration_ms', ['ok', 'success', 'error'])->first();
        $summary = [
            'total_runs' => (int) $summaryRow?->total_runs,
            'success_runs' => (int) $summaryRow?->success_runs,
            'error_runs' => (int) $summaryRow?->error_runs,
            'input_tokens' => (int) $summaryRow?->input_tokens,
            'unknown_token_runs' => (int) $summaryRow?->unknown_token_runs,
            'output_tokens' => (int) $summaryRow?->output_tokens,
            'avg_duration_ms' => (int) round((float) $summaryRow?->avg_duration_ms),
            'max_duration_ms' => (int) $summaryRow?->max_duration_ms,
        ];
        $daily = (clone $query)
            ->selectRaw('DATE(created_at) date, COUNT(*) runs, COALESCE(SUM(input_tokens), 0) input_tokens, COALESCE(SUM(output_tokens), 0) output_tokens, SUM(CASE WHEN input_tokens IS NULL OR output_tokens IS NULL THEN 1 ELSE 0 END) unknown_token_runs, COALESCE(AVG(duration_ms), 0) avg_duration_ms')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn (AiRun $row): array => [
                'date' => $row->getAttribute('date'),
                'runs' => (int) $row->getAttribute('runs'),
                'input_tokens' => (int) $row->input_tokens,
                'unknown_token_runs' => (int) $row->unknown_token_runs,
                'output_tokens' => (int) $row->output_tokens,
                'avg_duration_ms' => (int) round((float) $row->getAttribute('avg_duration_ms')),
            ]);
        $runs = $query->select(['id', 'agent', 'provider_name', 'model_name', 'input_tokens', 'output_tokens', 'duration_ms', 'status', 'error_code', 'created_at'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($validated['perPage'] ?? 25)->withQueryString();

        return response()->json(['data' => [
            'summary' => $summary,
            'daily_usage' => $daily,
            'runs' => ['data' => $runs->items(), 'links' => ['prev' => $runs->previousPageUrl(), 'next' => $runs->nextPageUrl()], 'meta' => ['current_page' => $runs->currentPage(), 'last_page' => $runs->lastPage(), 'per_page' => $runs->perPage(), 'total' => $runs->total()]],
            'filter_options' => [
                'agents' => AiRun::query()->whereNotNull('agent')->distinct()->orderBy('agent')->pluck('agent'),
                'providers' => AiRun::query()->whereNotNull('provider_name')->distinct()->orderBy('provider_name')->pluck('provider_name'),
                'models' => AiRun::query()->whereNotNull('model_name')->distinct()->orderBy('model_name')->pluck('model_name'),
            ],
        ]]);
    }
}
