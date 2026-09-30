<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EnsureIdempotentRequest
{
    private const int TTL_SECONDS = 86400;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey === null && $mode === 'optional') {
            return $next($request);
        }

        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 255) {
            return response()->json([
                'message' => 'The Idempotency-Key header is required.',
                'code' => 'idempotency_key_required',
                'errors' => ['Idempotency-Key' => ['The Idempotency-Key header is required.']],
            ], 422);
        }

        $userId = (string) $request->user()?->getAuthIdentifier();
        $cacheKey = 'idempotency:'.hash('sha256', $userId.'|'.$request->route()?->getName().'|'.$idempotencyKey);
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.json_encode(
            $this->canonicalize($request->all()),
            JSON_THROW_ON_ERROR,
        ));

        return Cache::lock($cacheKey.':lock', 10)->block(5, function () use ($request, $next, $cacheKey, $fingerprint): Response {
            $stored = Cache::get($cacheKey);

            if (is_array($stored)) {
                if (($stored['fingerprint'] ?? null) !== $fingerprint) {
                    return response()->json([
                        'message' => 'This idempotency key was already used with a different request.',
                        'code' => 'idempotency_key_conflict',
                        'errors' => (object) [],
                    ], 409);
                }

                return response($stored['content'], $stored['status'], $stored['headers'])
                    ->header('Idempotency-TTL', (string) self::TTL_SECONDS);
            }

            $response = $next($request);
            $response->headers->set('Idempotency-TTL', (string) self::TTL_SECONDS);

            if ($response->getStatusCode() < 500 && ! $response instanceof StreamedResponse) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'content' => $response->getContent(),
                    'status' => $response->getStatusCode(),
                    'headers' => $response->headers->all(),
                ], self::TTL_SECONDS);
            }

            return $response;
        });
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'original_name' => $value->getClientOriginalName(),
                'size' => $value->getSize(),
                'mime_type' => $value->getMimeType(),
                'sha256' => $value->isValid() ? hash_file('sha256', $value->getRealPath()) : null,
            ];
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
