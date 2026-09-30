<?php

namespace App\Services\Recipes\Import;

class DurationParser
{
    /**
     * Parse an ISO 8601 duration string to seconds.
     *
     * Examples: PT15M → 900, PT1H30M → 5400, P1D → 86400
     */
    public function parseIso8601(string $duration): int
    {
        $match = [];
        if (! preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $match)) {
            throw new \InvalidArgumentException("Invalid ISO 8601 duration: {$duration}");
        }

        $days = (int) ($match[1] ?? 0);
        $hours = (int) ($match[2] ?? 0);
        $minutes = (int) ($match[3] ?? 0);
        $seconds = (int) ($match[4] ?? 0);

        return ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    /**
     * Parse a human-readable duration string to seconds.
     *
     * Examples: "15 minutes" → 900, "1 hour 30 minutes" → 5400, "30 seconds" → 30
     */
    public function parseHuman(string $duration): int
    {
        $totalSeconds = 0;

        // Match patterns like "2 hours", "30 minutes", "15 seconds"
        if (preg_match_all('/(\d+)\s*(hours?|hrs?|minutes?|mins?|seconds?|secs?)/i', $duration, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $value = (int) $match[1];
                $unit = strtolower($match[2]);

                if (str_starts_with($unit, 'hour') || str_starts_with($unit, 'hr')) {
                    $totalSeconds += $value * 3600;
                } elseif (str_starts_with($unit, 'min')) {
                    $totalSeconds += $value * 60;
                } elseif (str_starts_with($unit, 'sec')) {
                    $totalSeconds += $value;
                }
            }
        }

        if ($totalSeconds === 0) {
            throw new \InvalidArgumentException("Cannot parse duration: {$duration}");
        }

        return $totalSeconds;
    }

    /**
     * Auto-detect format and parse to seconds.
     */
    public function parse(string $duration): int
    {
        // Try ISO 8601 first
        if (str_starts_with(strtoupper($duration), 'P')) {
            try {
                return $this->parseIso8601($duration);
            } catch (\InvalidArgumentException) {
                // Fall through to human parser
            }
        }

        return $this->parseHuman($duration);
    }
}
