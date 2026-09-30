<?php

namespace App\Actions\Recipes;

use App\Data\Recipes\RecipeImportResult;
use App\Services\Recipes\Import\JsonLdExtractor;
use App\Services\Recipes\Import\JsonLdRecipeImporter;
use App\Services\Recipes\Import\SafeUrlFetcher;
use Exception;

/**
 * Fetches a URL, extracts its JSON-LD recipe and imports it.
 */
final class PreviewJsonLdRecipeImport
{
    public function __construct(
        private readonly SafeUrlFetcher $fetcher,
        private readonly JsonLdExtractor $extractor,
        private readonly JsonLdRecipeImporter $importer,
    ) {}

    /**
     * Run the shared JSON-LD import pipeline.
     *
     * @return array{result: RecipeImportResult|null, error: string|null}
     */
    public function execute(string $url): array
    {
        try {
            $html = $this->fetcher->fetch($url);
        } catch (Exception $exception) {
            return ['result' => null, 'error' => 'No se pudo obtener el contenido de la URL: '.$exception->getMessage()];
        }

        $recipes = $this->extractor->extract($html);

        if ($recipes === []) {
            $foundTypes = $this->extractor->findJsonLdTypes($html);

            if ($foundTypes !== []) {
                return [
                    'result' => null,
                    'error' => 'Se encontró contenido JSON-LD (tipo: '.implode(', ', $foundTypes).'), pero no es una receta. La página debe tener un bloque @type: Recipe con ingredientes e instrucciones.',
                ];
            }

            return ['result' => null, 'error' => 'No se encontraron datos de receta (JSON-LD) en esta URL.'];
        }

        try {
            $recipes[0]['url'] ??= $url;

            return ['result' => $this->importer->import($recipes[0]), 'error' => null];
        } catch (Exception $exception) {
            return ['result' => null, 'error' => 'Error al procesar la receta: '.$exception->getMessage()];
        }
    }
}
