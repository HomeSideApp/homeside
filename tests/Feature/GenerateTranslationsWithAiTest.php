<?php

namespace Tests\Feature;

use App\Actions\Translations\GenerateTranslationsWithAi;
use App\Data\Translations\GenerateTranslationsData;
use App\Enums\TranslationEntityStatus;
use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Translation;
use App\Models\User;
use HomeSide\AiAgents\AiAgentManager;
use HomeSide\AiAgents\Execution\AiExecutionResultData;
use HomeSide\AiAgents\Execution\AiUsageData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class GenerateTranslationsWithAiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_persists_translations_and_recalculates_status_without_publishing(): void
    {
        $categoryA = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $categoryB = Category::create(['name' => 'Fanta', 'slug' => 'fanta', 'color' => '#00FF00']);

        $agentManager = Mockery::mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')->once()->andReturn($this->aiResult(json_encode([
            'translations' => [
                ['client_id' => $categoryA->id, 'value' => 'Frutas'],
            ],
            'unmatched' => [$categoryB->id],
        ], JSON_THROW_ON_ERROR)));
        $this->app->instance(AiAgentManager::class, $agentManager);

        $result = app(GenerateTranslationsWithAi::class)->execute(
            $this->user,
            new GenerateTranslationsData('category', 'es-ES'),
        );

        $this->assertSame(1, $result['translated']);
        $this->assertSame([$categoryB->id], $result['unmatched']);
        $this->assertSame([], $result['failed']);

        /** @var Translation $translation */
        $translation = $categoryA->translations()->forLocale('es-ES')->firstOrFail();
        $this->assertSame('Frutas', $translation->value);
        $this->assertSame(TranslationFieldStatus::Translated, $translation->status);

        $status = $categoryA->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Complete, $status->status);
        $this->assertNull($status->published_at);

        // The unmatched item gets no translation.
        $this->assertSame(0, $categoryB->translations()->count());
    }

    public function test_retries_with_invalid_response_and_succeeds_on_second_attempt(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $agentManager = Mockery::mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')->twice()->andReturn(
            $this->aiResult(json_encode([
                'translations' => [
                    ['client_id' => 'unknown-id', 'value' => 'Frutas'],
                ],
                'unmatched' => [],
            ], JSON_THROW_ON_ERROR)),
            $this->aiResult(json_encode([
                'translations' => [
                    ['client_id' => $category->id, 'value' => 'Frutas'],
                ],
                'unmatched' => [],
            ], JSON_THROW_ON_ERROR)),
        );
        $this->app->instance(AiAgentManager::class, $agentManager);

        $result = app(GenerateTranslationsWithAi::class)->execute(
            $this->user,
            new GenerateTranslationsData('category', 'es-ES', [$category->id]),
        );

        $this->assertSame(1, $result['translated']);
        $this->assertDatabaseHas('translations', [
            'translatable_id' => $category->id,
            'locale' => 'es-ES',
            'value' => 'Frutas',
        ]);
    }

    public function test_marks_items_as_failed_after_three_invalid_attempts(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $agentManager = Mockery::mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')->times(3)->andReturn(
            $this->aiResult('not-json-at-all'),
        );
        $this->app->instance(AiAgentManager::class, $agentManager);

        $result = app(GenerateTranslationsWithAi::class)->execute(
            $this->user,
            new GenerateTranslationsData('category', 'es-ES', [$category->id]),
        );

        $this->assertSame(0, $result['translated']);
        $this->assertSame([$category->id], $result['failed']);
        $this->assertSame(0, $category->translations()->count());
    }

    public function test_rejects_a_non_translatable_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(GenerateTranslationsWithAi::class)->execute(
            $this->user,
            new GenerateTranslationsData('rocket', 'es-ES'),
        );
    }

    public function test_skips_entities_already_published_for_the_target_locale_when_ids_are_null(): void
    {
        $published = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $pending = Category::create(['name' => 'Vegetables', 'slug' => 'vegetables', 'color' => '#00FF00']);

        $published->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $published->recalculateTranslationStatus('es-ES');
        $statusRow = $published->translationStatus()->forLocale('es-ES')->firstOrFail();
        $statusRow->status = TranslationEntityStatus::Published;
        $statusRow->save();

        $agentManager = Mockery::mock(AiAgentManager::class);
        $agentManager->shouldReceive('run')->once()->andReturnUsing(function (string $agentKey, $context, string $userMessage) use ($pending): AiExecutionResultData {
            // Only the non-published entity is sent to the agent.
            $this->assertStringContainsString('Vegetables', $userMessage);
            $this->assertStringNotContainsString('Fruits', $userMessage);

            return $this->aiResult(json_encode([
                'translations' => [
                    ['client_id' => $pending->id, 'value' => 'Verduras'],
                ],
                'unmatched' => [],
            ], JSON_THROW_ON_ERROR));
        });
        $this->app->instance(AiAgentManager::class, $agentManager);

        $result = app(GenerateTranslationsWithAi::class)->execute(
            $this->user,
            new GenerateTranslationsData('category', 'es-ES'),
        );

        $this->assertSame(1, $result['translated']);
        $this->assertSame(0, $published->translations()->forLocale('es-ES')->where('value', '!=', 'Frutas')->count());
    }

    private function aiResult(string $reply): AiExecutionResultData
    {
        return new AiExecutionResultData(
            runId: fake()->uuid(),
            agent: 'translations.generator',
            agentVersion: 1,
            provider: 'test-provider',
            model: 'test-model',
            status: 'ok',
            reply: $reply,
            usage: new AiUsageData(
                inputTokens: 0,
                outputTokens: 0,
                totalTokens: 0,
                latencyMs: 1,
            ),
            structured: true,
        );
    }
}
