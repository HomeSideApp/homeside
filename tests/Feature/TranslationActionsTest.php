<?php

namespace Tests\Feature;

use App\Actions\Translations\CreateTranslation;
use App\Actions\Translations\DeleteTranslation;
use App\Actions\Translations\PublishTranslation;
use App\Actions\Translations\UpdateTranslation;
use App\Data\Translations\CreateTranslationData;
use App\Data\Translations\PublishTranslationData;
use App\Data\Translations\UpdateTranslationData;
use App\Enums\TranslationEntityStatus;
use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TranslationActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_translation_persists_and_recalculates_status_to_complete(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $translation = app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas',
        ));

        $this->assertSame('Frutas', $translation->value);
        $this->assertSame(TranslationFieldStatus::Pending, $translation->status);

        $status = $category->translationStatus()->forLocale('es-ES')->first();
        $this->assertSame(TranslationEntityStatus::Complete, $status->status);
    }

    public function test_create_translation_starts_incomplete_before_first_field(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $statusBefore = $category->translationStatus()->forLocale('es-ES')->first();
        $this->assertNull($statusBefore);

        $category->recalculateTranslationStatus('es-ES');

        $status = $category->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Incomplete, $status->status);
    }

    public function test_create_translation_rejects_invalid_locale(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $this->expectException(ValidationException::class);

        app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'x',
            field: 'name',
            value: 'Fruits',
        ));
    }

    public function test_create_translation_accepts_free_bcp47_locales(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $translation = app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'pt-BR',
            field: 'name',
            value: 'Frutas',
        ));

        $this->assertSame('Frutas', $translation->value);
        $this->assertSame('pt-BR', $translation->locale);
    }

    public function test_create_translation_normalizes_case_in_free_locales(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $translation = app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'ZH-HANS-CN',
            field: 'name',
            value: '水果',
        ));

        $this->assertSame('zh-Hans-CN', $translation->locale);
    }

    public function test_create_translation_rejects_unknown_translatable_type(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'rocket',
            translatableId: '00000000-0000-0000-0000-000000000000',
            locale: 'es-ES',
            field: 'name',
            value: 'Fruits',
        ));
    }

    public function test_create_translation_rejects_unknown_entity_id(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: '00000000-0000-0000-0000-000000000000',
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas',
        ));
    }

    public function test_create_translation_updates_existing_row_instead_of_duplicating(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        $action = app(CreateTranslation::class);
        $action->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas',
        ));
        $action->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas v2',
            status: TranslationFieldStatus::Translated,
        ));

        $this->assertSame(1, Translation::query()->count());

        $translation = Translation::query()->firstOrFail();
        $this->assertSame('Frutas v2', $translation->value);
        $this->assertSame(TranslationFieldStatus::Translated, $translation->status);
    }

    public function test_update_translation_recalculates_status(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $translation = app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas',
        ));

        $updated = app(UpdateTranslation::class)->execute(
            $translation,
            new UpdateTranslationData(value: 'Frutas actualizada', status: TranslationFieldStatus::Approved),
        );

        $this->assertSame('Frutas actualizada', $updated->value);
        $this->assertSame(TranslationFieldStatus::Approved, $updated->status);
    }

    public function test_delete_translation_downgrades_status_to_incomplete(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $translation = app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas',
        ));

        app(DeleteTranslation::class)->execute($translation);

        $this->assertSame(0, Translation::query()->count());
        $status = $category->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Incomplete, $status->status);
    }

    public function test_publish_translation_rejects_when_not_complete(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);

        try {
            app(PublishTranslation::class)->execute(new PublishTranslationData(
                translatableType: 'category',
                translatableId: $category->id,
                locale: 'es-ES',
                publish: true,
            ));
            $this->fail('PublishTranslation should reject incomplete entities.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                __('app.errors.translation_not_complete'),
                $exception->getMessage(),
            );
        }
    }

    public function test_publish_translation_publishes_complete_entity(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas',
        ));

        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: true,
        ));

        $status = $category->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Published, $status->status);
        $this->assertNotNull($status->published_at);
        $this->assertTrue($category->fresh()->isPublished('es-ES'));
    }

    public function test_unpublish_translation_clears_publication(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas',
        ));
        $publish = app(PublishTranslation::class);
        $publish->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: true,
        ));

        $publish->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: false,
        ));

        $status = $category->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Incomplete, $status->status);
        $this->assertNull($status->published_at);
        $this->assertFalse($category->fresh()->isPublished('es-ES'));
    }

    public function test_published_status_survives_recalculation_after_delete_of_other_locale(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        app(CreateTranslation::class)->execute(new CreateTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            field: 'name',
            value: 'Frutas',
        ));
        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: true,
        ));

        // Delete a translation of another locale; es-ES status must not change.
        $enTranslation = $category->putTranslation('name', 'en-US', 'Fruits', TranslationFieldStatus::Approved);
        app(DeleteTranslation::class)->execute($enTranslation);

        $status = $category->translationStatus()->forLocale('es-ES')->firstOrFail();
        $this->assertSame(TranslationEntityStatus::Published, $status->status);
        $this->assertNotNull($status->published_at);
    }
}
