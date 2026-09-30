<?php

namespace App\Jobs;

use App\Services\Recipes\ReconcileRecipeReferencePaths;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Class ReconcileRecipeReferences
 *
 * This job reconciles the Cooklang links between recipes, so that recipe references
 * point to the correct recipes. It is unique, queued and re-queued with a backoff.
 */
final class ReconcileRecipeReferences implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public ?string $referencedRecipeId = null) {}

    /**
     * Reconcile the recipe references.
     *
     * @param  ReconcileRecipeReferencePaths  $reconciler  The reconciler value.
     */
    public function handle(ReconcileRecipeReferencePaths $reconciler): void
    {
        $reconciler->execute($this->referencedRecipeId);
    }

    /**
     * Get the unique id of the job.
     *
     * @return string A string value.
     */
    public function uniqueId(): string
    {
        return $this->referencedRecipeId ?? 'all';
    }

    /**
     * Log the job failure.
     *
     * @param  ?Throwable  $exception  The exception value.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to reconcile recipe references.', [
            'referenced_recipe_id' => $this->referencedRecipeId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
