<?php

namespace Database\Seeders;

use HomeSide\AiAgents\Models\AiGlobalSetting;
use Illuminate\Database\Seeder;

class AiGlobalSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Prompt global — se aplica a TODAS las llamadas IA
        AiGlobalSetting::updateOrCreate(
            ['module' => null],
            ['extra_prompt' => $this->getGlobalPrompt()],
        );

        // Prompt de recetas — vacío por defecto, el prompt base está en RecipesAiModule
        // El usuario puede añadir instrucciones extra desde el panel de administración
        AiGlobalSetting::updateOrCreate(
            ['module' => 'recipes'],
            ['extra_prompt' => ''],
        );

        // Prompt de economía — comportamiento para análisis de tickets
        AiGlobalSetting::updateOrCreate(
            ['module' => 'economy'],
            ['extra_prompt' => $this->getEconomyPrompt()],
        );

        // Prompt general — comportamiento para uso general
        AiGlobalSetting::updateOrCreate(
            ['module' => 'general'],
            ['extra_prompt' => $this->getGeneralPrompt()],
        );
    }

    /**
     * Prompt global: instrucciones que se aplican a todas las llamadas IA.
     */
    private function getGlobalPrompt(): string
    {
        return 'Responde siempre en español. Sé conciso y preciso en tus respuestas.';
    }

    /**
     * Prompt de economía: comportamiento para análisis de tickets de compra.
     */
    private function getEconomyPrompt(): string
    {
        return <<<'PROMPT'
Eres un asistente experto en análisis de gastos domésticos y economía familiar.

Analiza los tickets de compra con precisión, identificando:
- Categorías de productos y su distribución de gasto
- Tendencias de precios a lo largo del tiempo
- Comparativas entre tiendas o períodos
- Oportunidades de ahorro
- Productos con mayor impacto en el presupuesto

Sé práctico y ofrece consejos concretos de ahorro cuando sea relevante.
PROMPT;
    }

    /**
     * Prompt general: comportamiento para uso general de la IA.
     */
    private function getGeneralPrompt(): string
    {
        return <<<'PROMPT'
Eres un asistente inteligente y amable. Ayuda al usuario con cualquier tarea relacionada con la gestión del hogar, listas de compra, organización y planificación.

Sé claro, conciso y útil en tus respuestas. Cuando el usuario pida crear algo (una lista, una receta, etc.), sugiere pasos concretos para hacerlo realidad.
PROMPT;
    }
}
