<?php

namespace App\Support\Auth\GoogleSimulator;

use RuntimeException;

/**
 * File-backed state for the standalone Google simulator.
 *
 * The simulator runs as its own `php -S` process, and PHP's built-in server
 * executes the router script from scratch on every request, so nothing can be
 * kept in process memory. A small JSON file, guarded with `flock`, carries the
 * scenario identity, the outstanding authorization codes, the issued access
 * tokens and the call counters between requests.
 */
final class SimulatorState
{
    /**
     * Create the state store.
     *
     * @param  string  $path  The JSON file that holds the state.
     */
    public function __construct(private readonly string $path) {}

    /**
     * Clear the scenario, every code, every token and the call counters.
     */
    public function reset(): void
    {
        $this->update(function (array $state): array {
            $state['scenario'] = null;
            $state['codes'] = [];
            $state['tokens'] = [];
            $state['calls'] = $this->emptyCounters();

            return $state;
        });
    }

    /**
     * Set the identity the simulator will assert on the next consent.
     *
     * @param  array<string, mixed>  $identity  The identity payload (email, sub, name, email_verified).
     */
    public function setScenario(array $identity): void
    {
        $this->update(function (array $state) use ($identity): array {
            $state['scenario'] = $identity;

            return $state;
        });
    }

    /**
     * Read the current scenario identity.
     *
     * @return array<string, mixed>|null The identity, or null when none was configured.
     */
    public function scenario(): ?array
    {
        $scenario = $this->read()['scenario'];

        return is_array($scenario) ? $scenario : null;
    }

    /**
     * Store an authorization code and return it.
     *
     * @param  array<string, mixed>  $payload  The code payload (scenario, redirect_uri, client_id).
     * @return string The generated code.
     */
    public function issueCode(array $payload): string
    {
        $code = 'e2e-code-'.bin2hex(random_bytes(16));

        $this->update(function (array $state) use ($code, $payload): array {
            $state['codes'][$code] = ['payload' => $payload, 'used' => false, 'issued_at' => time()];

            return $state;
        });

        return $code;
    }

    /**
     * Consume an authorization code exactly once.
     *
     * @param  string  $code  The code to consume.
     * @return array<string, mixed>|null The payload, or null when the code is unknown or already used.
     */
    public function consumeCode(string $code): ?array
    {
        $result = null;

        $this->update(function (array $state) use ($code, &$result): array {
            $entry = $state['codes'][$code] ?? null;

            if (! is_array($entry) || ($entry['used'] ?? false) === true) {
                return $state;
            }

            $state['codes'][$code]['used'] = true;
            $result = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];

            return $state;
        });

        return $result;
    }

    /**
     * Store an access token for an identity and return it.
     *
     * @param  array<string, mixed>  $identity  The identity the token belongs to.
     * @return string The generated access token.
     */
    public function issueToken(array $identity): string
    {
        $token = 'e2e-token-'.bin2hex(random_bytes(16));

        $this->update(function (array $state) use ($token, $identity): array {
            $state['tokens'][$token] = ['identity' => $identity, 'issued_at' => time()];

            return $state;
        });

        return $token;
    }

    /**
     * Resolve an access token to its identity.
     *
     * @param  string  $token  The bearer token.
     * @return array<string, mixed>|null The identity, or null when the token is unknown.
     */
    public function resolveToken(string $token): ?array
    {
        $entry = $this->read()['tokens'][$token] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        return is_array($entry['identity'] ?? null) ? $entry['identity'] : null;
    }

    /**
     * Increment a counter and record the context of the call.
     *
     * @param  string  $name  The endpoint name (`auth`, `consent`, `token`, `userinfo`, `denied`).
     * @param  array<string, mixed>  $context  The call context, exposed as `last_<name>`.
     */
    public function recordCall(string $name, array $context = []): void
    {
        $this->update(function (array $state) use ($name, $context): array {
            $state['calls'][$name] = ((int) ($state['calls'][$name] ?? 0)) + 1;
            $state['calls']['last_'.$name] = $context + ['at' => time()];

            return $state;
        });
    }

    /**
     * Read the whole snapshot for the assertion endpoint.
     *
     * @return array<string, mixed> The snapshot.
     */
    public function snapshot(): array
    {
        $state = $this->read();
        $state['pending_codes'] = array_keys(array_filter(
            (array) ($state['codes'] ?? []),
            fn (mixed $entry): bool => is_array($entry) && ($entry['used'] ?? false) === false,
        ));
        $state['issued_tokens'] = array_keys((array) ($state['tokens'] ?? []));

        unset($state['codes'], $state['tokens']);

        return $state;
    }

    /**
     * Read the state under a lock.
     *
     * @return array<string, mixed> The decoded state.
     */
    private function read(): array
    {
        if (! is_file($this->path)) {
            return $this->fresh();
        }

        return $this->withFile(fn (array $state): array => $state);
    }

    /**
     * Apply a mutation to the state atomically.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutator  The mutation.
     */
    private function update(callable $mutator): void
    {
        $this->withFile(function (array $state) use ($mutator): array {
            $updated = $mutator($state);

            return is_array($updated) ? $updated : $state;
        }, write: true);
    }

    /**
     * Run a callback against the locked file, optionally writing the result back.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback  The work.
     * @param  bool  $write  Whether to persist the returned state.
     * @return array<string, mixed> The (possibly written) state.
     */
    private function withFile(callable $callback, bool $write = false): array
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create the simulator state directory [{$directory}].");
        }

        $handle = fopen($this->path, $write ? 'c+' : 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open the simulator state file [{$this->path}].");
        }

        try {
            flock($handle, $write ? LOCK_EX : LOCK_SH);

            $contents = (string) stream_get_contents($handle);
            $decoded = $contents === '' ? $this->fresh() : json_decode($contents, true);
            $state = is_array($decoded) ? $decoded : $this->fresh();

            $result = $callback($state);

            if ($write) {
                $encoded = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                if ($encoded === false) {
                    throw new RuntimeException('Could not encode the simulator state.');
                }

                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, $encoded);
                fflush($handle);
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * The empty state.
     *
     * @return array<string, mixed> A fresh state document.
     */
    private function fresh(): array
    {
        return [
            'scenario' => null,
            'codes' => [],
            'tokens' => [],
            'calls' => $this->emptyCounters(),
        ];
    }

    /**
     * The zeroed call counters.
     *
     * @return array<string, int|array<string, mixed>> The counters.
     */
    private function emptyCounters(): array
    {
        return [
            'auth' => 0,
            'consent' => 0,
            'token' => 0,
            'userinfo' => 0,
            'denied' => 0,
        ];
    }
}
