<?php

namespace App\Services\Recipes\Import;

class IngredientTextParser
{
    /**
     * Parse a raw ingredient text string into components.
     *
     * Example: "200 g de arroz bomba" → [quantity: 200, unit: g, name: arroz bomba]
     * Example: "una pizca de sal" → [quantity: null, unit: null, name: una pizca de sal]
     * Example: "2 cebollas grandes, finamente picadas" → [quantity: 2, unit: null, name: cebollas, preparation: finamente picadas]
     *
     * @return array<string, mixed>
     */
    public function parse(string $text): array
    {
        $originalText = trim($text);

        if ($originalText === '') {
            return [
                'original_text' => $originalText,
                'quantity' => null,
                'quantity_text' => null,
                'unit' => null,
                'name' => '',
                'preparation' => null,
                'notes' => null,
            ];
        }

        // Known Spanish units (expand as needed)
        $units = [
            'l', 'ml', 'kg', 'g', 'mg',
            'litro', 'litros', 'mililitro', 'mililitros',
            'kilogramo', 'kilogramos', 'gramo', 'gramos',
            'taza', 'tazas', 'cucharada', 'cucharadas',
            'cucharadita', 'cucharaditas', 'pizca',
            'diente', 'dientes', 'rama', 'ramas',
            'unidad', 'unidades', 'ud', 'uds', 'pieza', 'piezas',
            'filete', 'filetes', 'rebanada', 'rebanadas', 'rodaja', 'rodajas',
            'puñado', 'cachito', 'cachitos',
            'raíz', 'raíces', 'hoja', 'hojas',
            'bola', 'bolas', 'nudo', 'nudos',
        ];

        $unitPattern = implode('|', $units);

        // Pattern 1: number unit de/name
        $pattern1 = '/^(\d+(?:[\/\.]\d+)?)\s*('.$unitPattern.')\b\s*de?\s+(.+)/i';

        if (preg_match($pattern1, $originalText, $matches)) {
            $quantity = $this->parseQuantity($matches[1]);
            $unit = $matches[2];
            $rest = $matches[3];

            [$name, $preparation] = $this->splitNameAndPreparation($rest);

            return [
                'original_text' => $originalText,
                'quantity' => $quantity,
                'quantity_text' => null,
                'unit' => $unit,
                'name' => trim($name),
                'preparation' => $preparation,
                'notes' => null,
            ];
        }

        // Pattern 2: number unit name
        $pattern2 = '/^(\d+(?:[\/\.]\d+)?)\s*('.$unitPattern.')\s+(.+)/i';

        if (preg_match($pattern2, $originalText, $matches)) {
            $quantity = $this->parseQuantity($matches[1]);
            $unit = $matches[2];
            $name = trim($matches[3]);

            [$name, $preparation] = $this->splitNameAndPreparation($name);

            return [
                'original_text' => $originalText,
                'quantity' => $quantity,
                'quantity_text' => null,
                'unit' => $unit,
                'name' => $name,
                'preparation' => $preparation,
                'notes' => null,
            ];
        }

        // Pattern 3: just text (no parseable quantity)
        return [
            'original_text' => $originalText,
            'quantity' => null,
            'quantity_text' => $originalText,
            'unit' => null,
            'name' => '',
            'preparation' => null,
            'notes' => null,
        ];
    }

    /**
     * Parse a quantity string to a float. Supports fractions like "1/2".
     */
    private function parseQuantity(string $quantity): ?float
    {
        // Handle fraction: "1/2"
        if (str_contains($quantity, '/')) {
            $parts = explode('/', $quantity);
            if (count($parts) === 2) {
                $numerator = (float) trim($parts[0]);
                $denominator = (float) trim($parts[1]);
                if ($denominator !== 0.0) {
                    return $numerator / $denominator;
                }
            }
        }

        // Handle decimal or integer
        $value = (float) $quantity;

        return $value > 0 ? $value : null;
    }

    /**
     * Split a string into name and preparation parts.
     *
     * "tomates maduros, cortados en cuartos" → ["tomates maduros", "cortados en cuartos"]
     * "aceite de oliva virgen extra" → ["aceite de oliva virgen extra", null]
     */
    /**
     * @return array{string, string|null}
     */
    private function splitNameAndPreparation(string $text): array
    {
        // Split on comma followed by common preparation keywords
        $parts = preg_split('/,\s+(cortados?|picados?|troceados?|trozos?|en\s+cuartos|en\s+rodajas|en\s+aros|laminados?|enteros?|enteras?|entero|entera)/i', $text, 2);

        if ($parts !== false && count($parts) === 2) {
            return [trim($parts[0]), trim($parts[1])];
        }

        return [trim($text), null];
    }
}
