<?php

namespace App\Actions\Sync;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route as RouteRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Executes one offline push batch.
 *
 * Each operation is re-dispatched through the HTTP kernel, so it runs exactly the
 * middleware stack a direct call would run: `auth:sanctum`, `locale`,
 * `api.permission`, the policies, `idempotency` and `not-stale`. Validation and
 * authorization are therefore never reimplemented here — duplicating them is how
 * a batch path drifts from the direct one.
 *
 * The security boundary is the route-name allowlist below. An operation is only
 * dispatched when its `method` + `path` resolve to a route whose **name** is
 * listed; a prefix or pattern check would let an admin route, a read route or a
 * household deletion in. Everything else is answered `rejected` without being
 * dispatched at all, so it never touches data.
 */
final class PushSyncOperations
{
    /**
     * The maximum number of operations one request may carry.
     */
    public const int MAX_OPERATIONS = 50;

    /**
     * The only route names the batch may reach: the offline write surface of the
     * personal and household lists, their items, and the economy transactions of
     * both scopes.
     *
     * @var array<int, string>
     */
    public const array ALLOWED_ROUTE_NAMES = [
        // Personal lists and their items.
        'api.v1.lists.store',
        'api.v1.lists.update',
        'api.v1.lists.destroy',
        'api.v1.lists.items.store',
        'api.v1.lists.items.quick-create',
        'api.v1.lists.items.update',
        'api.v1.lists.items.destroy',
        // Household lists and their items.
        'api.v1.households.lists.store',
        'api.v1.households.lists.update',
        'api.v1.households.lists.destroy',
        'api.v1.households.lists.items.store',
        'api.v1.households.lists.items.quick-create',
        'api.v1.households.lists.items.update',
        'api.v1.households.lists.items.destroy',
        // Personal economy transactions.
        'api.v1.economy.me.transactions.store',
        'api.v1.economy.me.transactions.update',
        'api.v1.economy.me.transactions.patch',
        'api.v1.economy.me.transactions.destroy',
        // Household economy transactions.
        'api.v1.households.economy.transactions.store',
        'api.v1.households.economy.transactions.update',
        'api.v1.households.economy.transactions.patch',
        'api.v1.households.economy.transactions.destroy',
    ];

    /**
     * The only verbs the batch may reach.
     *
     * @var array<int, string>
     */
    public const array ALLOWED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private readonly Kernel $kernel) {}

    /**
     * Run every queued operation and answer one result per operation, in order.
     *
     * @param  Request  $request  The caller's batch request, used for its token and identity.
     * @param  array<int, array{op_id: string, method: string, path: string, idempotency_key: string, body?: array<string, mixed>}>  $operations  The queued operations.
     * @return array<int, array{op_id: string, status: string, status_code: int, body: mixed}> The results, in request order.
     */
    public function execute(Request $request, array $operations): array
    {
        $token = $request->bearerToken();
        $caller = $request->user();
        $results = [];

        try {
            foreach ($operations as $operation) {
                $results[] = $this->run($operation, $token);
            }
        } finally {
            // A sub-request replaced the container's `request` binding and the
            // resolved guard user while it ran; the outer request must keep its own.
            app()->instance('request', $request);
            Facade::clearResolvedInstance('request');

            if ($caller !== null) {
                Auth::guard('sanctum')->setUser($caller);
            }
        }

        return $results;
    }

    /**
     * Execute one operation and map its answer onto a batch result.
     *
     * @param  array{op_id: string, method: string, path: string, idempotency_key: string, body?: array<string, mixed>}  $operation  The queued operation.
     * @param  string|null  $token  The caller's bearer token, forwarded to the sub-request.
     * @return array{op_id: string, status: string, status_code: int, body: mixed} The result of this operation.
     */
    private function run(array $operation, ?string $token): array
    {
        $method = strtoupper((string) $operation['method']);
        $path = (string) $operation['path'];

        try {
            $subRequest = $this->subRequest($operation, $method, $path, $token);
        } catch (Throwable $exception) {
            report($exception);

            return $this->result((string) $operation['op_id'], 422, 'rejected', $this->notAllowedBody());
        }

        if ($this->isAllowedOperation($subRequest) === false) {
            return $this->result((string) $operation['op_id'], 422, 'rejected', $this->notAllowedBody());
        }

        try {
            $response = $this->kernel->handle($subRequest);
        } catch (Throwable $exception) {
            report($exception);

            return $this->result((string) $operation['op_id'], 500, 'failed', $this->serverErrorBody());
        }

        $statusCode = $response->getStatusCode();

        return $this->result(
            (string) $operation['op_id'],
            $statusCode,
            $this->statusFor($statusCode),
            $this->decode($response),
        );
    }

    /**
     * Decide whether the request the kernel is about to run is an allowed write.
     *
     * The probe is the request that will be dispatched, not a reconstruction of
     * it, so the route name checked here is the route the kernel will run: no
     * verb override, header or path normalisation can drift the two apart.
     *
     * @param  Request  $request  The sub-request built for the operation.
     * @return bool True only for an allowlisted write route with an allowed verb.
     */
    private function isAllowedOperation(Request $request): bool
    {
        if (! in_array($request->getMethod(), self::ALLOWED_METHODS, true)) {
            return false;
        }

        try {
            $route = RouteRegistrar::getRoutes()->match($request);
        } catch (Throwable) {
            return false;
        }

        $name = $route->getName();

        return is_string($name) && in_array($name, self::ALLOWED_ROUTE_NAMES, true);
    }

    /**
     * The batch-level answer for an operation that is not allowed.
     *
     * @return array<string, mixed> The rejection body.
     */
    private function notAllowedBody(): array
    {
        return [
            'message' => 'This operation is not allowed through the sync push batch.',
            'code' => 'sync_operation_not_allowed',
            'errors' => ['path' => ['This operation is not allowed through the sync push batch.']],
        ];
    }

    /**
     * Build the sub-request that re-dispatches the operation.
     *
     * @param  array{op_id: string, method: string, path: string, idempotency_key: string, body?: array<string, mixed>}  $operation  The queued operation.
     * @param  string  $method  The upper-cased operation verb.
     * @param  string  $path  The API-relative operation path.
     * @param  string|null  $token  The caller's bearer token.
     * @return Request The request to dispatch through the kernel.
     */
    private function subRequest(array $operation, string $method, string $path, ?string $token): Request
    {
        $body = $operation['body'] ?? null;

        $request = Request::create(
            $this->uriFor($path),
            $method,
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
        );

        $request->headers->set('Accept', 'application/json');
        // The key the device minted per logical operation, not per retry, so the
        // re-dispatched write replays instead of running twice.
        $request->headers->set('Idempotency-Key', (string) $operation['idempotency_key']);

        if ($token !== null) {
            $request->headers->set('Authorization', 'Bearer '.$token);
        }

        return $request;
    }

    /**
     * The URI the router has to see for a queued operation path.
     *
     * Devices queue the API-relative path (`/v1/lists/...`), while the route table
     * registers it behind the `api` prefix.
     *
     * @param  string  $path  The API-relative operation path.
     * @return string The URI to match and dispatch.
     */
    private function uriFor(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        return preg_match('#^/v1(/|\?|$)#', $path) === 1 ? '/api'.$path : $path;
    }

    /**
     * Classify a status code into the batch's status vocabulary.
     *
     * A 5xx is `failed`: never a silent success, and never confused with a
     * validation rejection the client must not retry.
     *
     * @param  int  $statusCode  The operation's own answer code.
     * @return string One of `applied`, `conflict`, `rejected` or `failed`.
     */
    private function statusFor(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => 'applied',
            $statusCode === 409 => 'conflict',
            $statusCode >= 400 && $statusCode < 500 => 'rejected',
            default => 'failed',
        };
    }

    /**
     * Decode the operation's own answer body.
     *
     * @param  Response  $response  The operation's own response.
     * @return mixed The decoded JSON body, the raw body when it is not JSON, or null when empty.
     */
    private function decode(Response $response): mixed
    {
        $content = $response->getContent();

        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
    }

    /**
     * The answer used when an allowed operation fails outside the HTTP layer.
     *
     * @return array<string, mixed> The batch-level server-error body.
     */
    private function serverErrorBody(): array
    {
        return [
            'message' => 'Server error.',
            'code' => 'server_error',
            'errors' => (object) [],
        ];
    }

    /**
     * @return array{op_id: string, status: string, status_code: int, body: mixed} One batch result.
     */
    private function result(string $opId, int $statusCode, string $status, mixed $body): array
    {
        return [
            'op_id' => $opId,
            'status' => $status,
            'status_code' => $statusCode,
            'body' => $body,
        ];
    }
}
