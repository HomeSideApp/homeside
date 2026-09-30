<?php

use App\Http\Middleware\ApiPermissionsMiddleware;
use App\Http\Middleware\EnsureApprovedUser;
use App\Http\Middleware\EnsureIdempotentRequest;
use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureUserHasHousehold;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PermissionsMiddleware;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            HandlePrecognitiveRequests::class,
            EnsureApprovedUser::class,
        ]);

        $middleware->api(append: [EnsureApprovedUser::class]);

        $middleware->alias([
            'permission' => PermissionsMiddleware::class,
            'api.permission' => ApiPermissionsMiddleware::class,
            'household' => EnsureUserHasHousehold::class,
            'module' => EnsureModuleEnabled::class,
            'locale' => SetLocale::class,
            'idempotency' => EnsureIdempotentRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'validation_failed',
                'errors' => $exception->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'unauthenticated',
                'errors' => (object) [],
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage() ?: 'Forbidden.',
                'code' => 'forbidden',
                'errors' => (object) [],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Resource not found.',
                'code' => 'not_found',
                'errors' => (object) [],
            ], 404);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $headers = $exception->getHeaders();
            $retryAfter = isset($headers['Retry-After']) ? (int) $headers['Retry-After'] : null;
            $codes = [401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found', 409 => 'conflict', 429 => 'rate_limited'];

            return response()->json(array_filter([
                'message' => $status >= 500 ? 'Server error.' : ($exception->getMessage() ?: 'Request failed.'),
                'code' => $codes[$status] ?? 'http_error',
                'errors' => (object) [],
                'retry_after' => $retryAfter,
            ], fn ($value): bool => $value !== null), $status, $headers);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Server error.',
                'code' => 'server_error',
                'errors' => (object) [],
            ], 500);
        });
    })->create();
