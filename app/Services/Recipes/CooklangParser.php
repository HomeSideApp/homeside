<?php

namespace App\Services\Recipes;

/**
 * @phpstan-type Segment array{type: 'ingredient'|'cookware'|'timer'|'recipe', content: string, raw: string, start: int, end: int, quantity: ?float, unit: ?string, preparation: ?string, duration_raw: ?string, duration_seconds: ?int}
 * @phpstan-type PregMatch array<int, array{0: string|null, 1: int}>
 */
class CooklangParser
{
    private const REFERENCE_PATTERN = '/@((?:\.\.?\/)+[^@#~{}\n]+)\{([^}]*)\}|@((?:[^@#~{}\n]|\.?\/)+)\{([^}]*)\}(\([^)]*\))?|#([^@#~{\n]+)\{([^}]*)\}|~([^@#~{\n]*)\{([^}]*)\}|@([\p{L}][\p{L}\p{N}\-]*)|#([\p{L}\p{N}][\p{L}\p{N}\-]*)/u';

    public function hasCooklangSyntax(string $text): bool
    {
        return (bool) preg_match('/[@#~]\S/u', $text);
    }

    /**
     * @return array<int, array{type: 'ingredient'|'cookware'|'timer'|'recipe', content: string, raw: string, start: int, end: int, quantity: ?float, unit: ?string, preparation: ?string, duration_raw: ?string, duration_seconds: ?int}>
     */
    public function parseSegments(string $text): array
    {
        preg_match_all(
            self::REFERENCE_PATTERN,
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
        );

        $segments = [];

        foreach ($matches as $match) {
            $raw = $this->captureValue($match, 0);
            $start = $match[0][1];
            $segment = [
                'type' => 'ingredient',
                'content' => '',
                'raw' => $raw,
                'start' => $start,
                'end' => $start + strlen($raw),
                'quantity' => null,
                'unit' => null,
                'preparation' => null,
                'duration_raw' => null,
                'duration_seconds' => null,
            ];

            if ($this->captureMatched($match, 1)) {
                [$quantity, $unit] = $this->parseBracesContent($this->captureValue($match, 2));
                $segment['type'] = 'recipe';
                $segment['content'] = trim($this->captureValue($match, 1));
                $segment['quantity'] = $quantity;
                $segment['unit'] = $unit;
            } elseif ($this->captureMatched($match, 3)) {
                [$quantity, $unit] = $this->parseBracesContent($this->captureValue($match, 4));
                $preparation = $this->captureValue($match, 5);
                $segment['content'] = trim($this->captureValue($match, 3));
                $segment['quantity'] = $quantity;
                $segment['unit'] = $unit;
                $segment['preparation'] = $preparation !== '' ? trim($preparation, '()') : null;
            } elseif ($this->captureMatched($match, 6)) {
                [$quantity, $unit] = $this->parseBracesContent($this->captureValue($match, 7));
                $segment['type'] = 'cookware';
                $segment['content'] = trim($this->captureValue($match, 6));
                $segment['quantity'] = $quantity;
                $segment['unit'] = $unit;
            } elseif ($this->captureMatched($match, 8)) {
                $durationRaw = trim($this->captureValue($match, 9));
                if ($durationRaw === '') {
                    continue;
                }

                $segment['type'] = 'timer';
                $segment['content'] = trim($this->captureValue($match, 8));
                $segment['duration_raw'] = $durationRaw;
                $segment['duration_seconds'] = $this->parseDurationToSeconds($durationRaw);
            } elseif ($this->captureMatched($match, 10)) {
                $segment['content'] = trim($this->captureValue($match, 10));
            } else {
                $segment['type'] = 'cookware';
                $segment['content'] = trim($this->captureValue($match, 11));
            }

            $segments[] = $segment;
        }

        return $segments;
    }

    /**
     * @return array<int, array{ref_id: string, name: string, quantity: ?float, unit: ?string, preparation: ?string, start: int, end: int}>
     */
    public function parseIngredients(string $text): array
    {
        $ingredients = [];

        foreach ($this->parseSegments($text) as $segment) {
            if ($segment['type'] !== 'ingredient') {
                continue;
            }

            $refId = md5('ing_'.$segment['content']);
            $ingredients[$refId] ??= [
                'ref_id' => $refId,
                'name' => $segment['content'],
                'quantity' => $segment['quantity'],
                'unit' => $segment['unit'],
                'preparation' => $segment['preparation'],
                'start' => $segment['start'],
                'end' => $segment['end'],
            ];
        }

        return array_values($ingredients);
    }

    /**
     * @return array<int, array{ref_id: string, name: string, quantity: ?float, unit: ?string, start: int, end: int}>
     */
    public function parseCookware(string $text): array
    {
        $cookware = [];

        foreach ($this->parseSegments($text) as $segment) {
            if ($segment['type'] !== 'cookware') {
                continue;
            }

            $refId = md5('cw_'.$segment['content']);
            $cookware[$refId] ??= [
                'ref_id' => $refId,
                'name' => $segment['content'],
                'quantity' => $segment['quantity'],
                'unit' => $segment['unit'],
                'start' => $segment['start'],
                'end' => $segment['end'],
            ];
        }

        return array_values($cookware);
    }

    /**
     * @return array<int, array{name: ?string, duration_raw: string, duration_seconds: ?int, start: int, end: int}>
     */
    public function parseTimers(string $text): array
    {
        $timers = [];

        foreach ($this->parseSegments($text) as $segment) {
            if ($segment['type'] !== 'timer') {
                continue;
            }

            if ($segment['duration_raw'] === null) {
                continue;
            }

            $timers[] = [
                'name' => $segment['content'] !== '' ? $segment['content'] : null,
                'duration_raw' => $segment['duration_raw'],
                'duration_seconds' => $segment['duration_seconds'],
                'start' => $segment['start'],
                'end' => $segment['end'],
            ];
        }

        return $timers;
    }

    /**
     * @return array<int, array{path: string, quantity: ?float, unit: ?string, start: int, end: int}>
     */
    public function parseRecipeReferences(string $text): array
    {
        return array_values(array_map(
            fn (array $segment): array => [
                'path' => $segment['content'],
                'quantity' => $segment['quantity'],
                'unit' => $segment['unit'],
                'start' => $segment['start'],
                'end' => $segment['end'],
            ],
            array_filter($this->parseSegments($text), fn (array $segment): bool => $segment['type'] === 'recipe'),
        ));
    }

    /**
     * @return array{
     *     ingredients: array<int, array{ref_id: string, name: string, quantity: ?float, unit: ?string, preparation: ?string, start: int, end: int}>,
     *     cookware: array<int, array{ref_id: string, name: string, quantity: ?float, unit: ?string, start: int, end: int}>,
     *     timers: array<int, array{name: ?string, duration_raw: string, duration_seconds: ?int, start: int, end: int}>,
     *     recipes: array<int, array{path: string, quantity: ?float, unit: ?string, start: int, end: int}>
     * }
     */
    public function parseAll(string $text): array
    {
        return [
            'ingredients' => $this->parseIngredients($text),
            'cookware' => $this->parseCookware($text),
            'timers' => $this->parseTimers($text),
            'recipes' => $this->parseRecipeReferences($text),
        ];
    }

    public function toDisplayText(string $text): string
    {
        return $this->replaceSegments($text, function (array $segment): string {
            if ($segment['type'] === 'timer') {
                return '('.$segment['duration_raw'].')';
            }

            return $segment['content'];
        });
    }

    public function cleanText(string $text): string
    {
        $cleaned = $this->replaceSegments($text, function (array $segment): string {
            if ($segment['type'] === 'timer') {
                return '';
            }

            if ($segment['type'] === 'cookware' && str_contains($segment['raw'], '{')) {
                return '';
            }

            return $segment['content'];
        });

        return trim((string) preg_replace('/\s{2,}/', ' ', $cleaned));
    }

    public function parseDurationToSeconds(string $durationRaw): ?int
    {
        $parts = preg_split('/[%\s]+/', trim($durationRaw), 2);
        $value = (int) ($parts[0] ?? 0);
        $unit = strtolower(trim($parts[1] ?? ''));

        return match ($unit) {
            's', 'sec', 'second', 'seconds', 'segundo', 'segundos' => $value,
            'm', 'min', 'minute', 'minutes', 'minuto', 'minutos' => $value * 60,
            'h', 'hr', 'hour', 'hours', 'hora', 'horas' => $value * 3600,
            default => $value * 60,
        };
    }

    /**
     * @return array{?float, ?string}
     */
    private function parseBracesContent(string $content): array
    {
        if ($content === '') {
            return [null, null];
        }

        $content = str_starts_with($content, '=') ? substr($content, 1) : $content;
        [$quantity, $unit] = array_pad(explode('%', $content, 2), 2, null);

        return [$this->parseQuantityValue($quantity ?? ''), $unit ?: null];
    }

    private function parseQuantityValue(string $value): ?float
    {
        $value = trim($value);

        if (str_contains($value, '/')) {
            $parts = explode('/', $value);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && (float) $parts[1] !== 0.0) {
                return (float) $parts[0] / (float) $parts[1];
            }

            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  callable(Segment): string  $replacement
     */
    private function replaceSegments(string $text, callable $replacement): string
    {
        $segments = array_reverse($this->parseSegments($text));

        foreach ($segments as $segment) {
            $text = substr_replace(
                $text,
                $replacement($segment),
                $segment['start'],
                $segment['end'] - $segment['start'],
            );
        }

        return $text;
    }

    /** @param PregMatch $match */
    private function captureMatched(array $match, int $index): bool
    {
        return isset($match[$index]) && $match[$index][1] !== -1;
    }

    /** @param PregMatch $match */
    private function captureValue(array $match, int $index): string
    {
        return $this->captureMatched($match, $index) ? (string) $match[$index][0] : '';
    }
}
