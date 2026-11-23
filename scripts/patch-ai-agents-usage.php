<?php

/**
 * Parche temporal: el paquete lee campos de `Usage` que ya no existen.
 *
 * `homeside/laravel-ai-agents` (commit del lock) hace
 * `$usage->promptTokens` / `$usage->completionTokens`, pero
 * `Laravel\Ai\Responses\Data\Usage` expone `inputTokens` / `outputTokens` desde
 * la v1.0 del SDK. En CLI eso es un warning; dentro de una request PHP lo
 * convierte en excepción, y **cualquier** generación con IA responde 422.
 *
 * El arreglo upstream existe (`b40de02`, 1 oct), pero no se puede instalar: las
 * versiones que lo incluyen piden `laravel/ai ^1.1`, y eso choca con
 * `rubix/ml` → `amphp/parallel ^1.3` → `amphp/amp ^2`. Hasta que el paquete
 * relaje `rubix/ml`, este script aplica el cambio después de `composer install`
 * o `composer update`.
 *
 * Es idempotente y se retira solo: cuando el paquete ya no mencione
 * `promptTokens`, no hace nada. El día que se pueda actualizar, borrar este
 * archivo y su entrada en `composer.json`.
 */
$target = __DIR__.'/../vendor/homeside/laravel-ai-agents/src/AiAgentManager.php';

if (! is_file($target)) {
    return;
}

$source = (string) file_get_contents($target);

if (! str_contains($source, 'promptTokens') && ! str_contains($source, 'completionTokens')) {
    // Ya está arreglado arriba: no hay nada que hacer.
    return;
}

$patched = str_replace(
    ['$usage->promptTokens', '$usage->completionTokens'],
    ['$usage->inputTokens', '$usage->outputTokens'],
    $source,
);

file_put_contents($target, $patched);

fwrite(STDOUT, "  patched homeside/laravel-ai-agents: Usage API (promptTokens → inputTokens)\n");
