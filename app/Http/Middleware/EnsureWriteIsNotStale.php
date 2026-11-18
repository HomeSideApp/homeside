<?php

namespace App\Http\Middleware;

use App\Actions\Economy\GetEconomicTransaction;
use App\Http\Resources\Economy\EconomicTransactionResource;
use App\Http\Resources\ShoppingLists\ListItemResource;
use App\Models\EconomicTransaction;
use App\Models\ListItem;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Refuses a write a device based on a version the row has already left behind.
 *
 * An offline client edits a copy, queues the write and pushes it later. The
 * version it edited from travels either in the `If-Unmodified-Since` header or
 * in an `updated_at` field (body or query). When the server's row is newer the
 * answer is 409 `stale_write` together with the live row, so the client can
 * merge instead of overwriting somebody else's change. A request without a
 * version is untrusted but unchanged: that is what every client sent before
 * this contract existed.
 *
 * Registered after `idempotency` on purpose: a replayed write answers with its
 * stored response and never reaches this comparison.
 */
final class EnsureWriteIsNotStale
{
    /**
     * Route parameters this middleware guards, with the model they bind and the
     * resource that describes the live row to the client.
     *
     * @var array<string, array{0: class-string<Model>, 1: class-string<JsonResource>}>
     */
    private const array TARGETS = [
        'item' => [ListItem::class, ListItemResource::class],
        'transaction' => [EconomicTransaction::class, EconomicTransactionResource::class],
    ];

    public function __construct(private readonly GetEconomicTransaction $getEconomicTransaction) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        [$model, $resource] = $this->target($request);

        if (! $model instanceof Model) {
            return $next($request);
        }

        $version = $this->version($request);

        if ($version instanceof JsonResponse) {
            return $version;
        }

        if ($version === null) {
            return $next($request);
        }

        if ($model->updated_at !== null && $model->updated_at->greaterThan($version)) {
            return $this->stale($request, $model, $resource);
        }

        return $next($request);
    }

    /**
     * Resolve the guarded row of the current route.
     *
     * A row the caller may not write is left alone: the endpoint's own
     * authorization answers for it, so this middleware never discloses a
     * resource through the 409 body.
     *
     * @return array{0: Model|null, 1: class-string<JsonResource>|null}
     */
    private function target(Request $request): array
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return [null, null];
        }

        foreach (self::TARGETS as $parameter => [$modelClass, $resource]) {
            if (! $route->hasParameter($parameter)) {
                continue;
            }

            $bound = $route->parameter($parameter);
            $model = $bound instanceof $modelClass
                ? $bound
                : $modelClass::query()->whereKey($bound)->first();

            if (! $model instanceof $modelClass || ! $this->mayWrite($request, $model)) {
                return [null, null];
            }

            return [$model, $resource];
        }

        return [null, null];
    }

    /**
     * Determine whether the caller is allowed to write the resolved row.
     */
    private function mayWrite(Request $request, ListItem|EconomicTransaction $model): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        $ability = $request->isMethod('DELETE') ? 'delete' : 'update';

        // List items have no policy of their own: write access comes from the
        // list they belong to.
        return $model instanceof ListItem
            ? $model->list !== null && $user->can($ability, $model->list)
            : $user->can($ability, $model);
    }

    /**
     * Read the version the write is based on, if it sent one.
     *
     * A blank source is the same as no source. A source that is present but
     * cannot be read as a date is a 422: silently ignoring it would let the
     * write through on a version the client never actually had.
     */
    private function version(Request $request): Carbon|JsonResponse|null
    {
        $sources = [
            'If-Unmodified-Since' => $request->header('If-Unmodified-Since'),
            'updated_at' => $request->input('updated_at'),
        ];

        foreach ($sources as $source => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (! is_string($value)) {
                return $this->unreadableVersion($source);
            }

            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return $this->unreadableVersion($source);
            }
        }

        return null;
    }

    /**
     * Answer a stale write with the live row so the client can merge.
     *
     * @param  class-string<JsonResource>  $resource
     */
    private function stale(Request $request, Model $model, string $resource): JsonResponse
    {
        return response()->json([
            'message' => 'This record changed after the version this write is based on.',
            'code' => 'stale_write',
            'errors' => (object) [],
            'data' => $resource::make($this->loadForClient($model))->resolve($request),
        ], 409);
    }

    /**
     * Load the relations the resource needs to describe the live row.
     */
    private function loadForClient(Model $model): Model
    {
        if ($model instanceof EconomicTransaction) {
            return $this->getEconomicTransaction->execute($model);
        }

        /** @var ListItem $model */
        return $model->load(['product.category', 'store', 'category', 'addedByUser']);
    }

    private function unreadableVersion(string $source): JsonResponse
    {
        return response()->json([
            'message' => 'The version this write is based on could not be read as a date.',
            'code' => 'validation_failed',
            'errors' => [$source => ['The version must be a date.']],
        ], 422);
    }
}
