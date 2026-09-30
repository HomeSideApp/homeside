<?php

namespace Tests\Feature;

use App\Actions\Translations\PublishTranslation;
use App\Data\Translations\PublishTranslationData;
use App\Enums\TranslationEntityStatus;
use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TranslationDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_localized_returns_translation_only_when_published(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');
        $this->assertFalse($category->isPublished('es-ES'));

        Config::set('app.locale', 'es-ES');
        $this->assertSame('Fruits', $category->localized('name'));
    }

    public function test_localized_returns_translation_when_published(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');

        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: true,
        ));

        $this->assertTrue($category->isPublished('es-ES'));

        Config::set('app.locale', 'es-ES');
        $this->assertSame('Frutas', $category->localized('name'));
    }

    public function test_localized_falls_back_to_source_when_active_locale_not_published(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');

        Config::set('app.locale', 'es-ES');
        $this->assertSame('Fruits', $category->localized('name'));
    }

    public function test_localized_uses_fallback_locale_when_active_locale_not_published_but_fallback_is(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        // Simulate en-US being a "translation" too: put it and publish it.
        $category->putTranslation('name', 'en-US', 'Fruits EN', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('en-US');
        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'en-US',
            publish: true,
        ));
        // es-ES has an unpublished translation.
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');

        Config::set('app.locale', 'es-ES');
        $this->assertSame('Fruits EN', $category->localized('name'));
    }

    public function test_source_column_stays_intact_after_publishing(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');
        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: true,
        ));
        $category->refresh();

        Config::set('app.locale', 'es-ES');
        $this->assertSame('Frutas', $category->localized('name'));
        $this->assertSame('Fruits', $category->name);
    }

    public function test_is_complete_requires_translation_for_every_translatable_field(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $this->assertFalse($category->isComplete('es-ES'));

        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->refresh();
        $this->assertTrue($category->isComplete('es-ES'));
    }

    public function test_is_complete_ignores_other_locales(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);

        $this->assertTrue($category->isComplete('es-ES'));
        $this->assertFalse($category->isComplete('en-US'));
    }

    public function test_recalculate_never_publishes_automatically(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');

        $status = $category->translationStatus()->forLocale('es-ES')->first();

        $this->assertNotNull($status);
        $this->assertSame(TranslationEntityStatus::Complete, $status->status);
        $this->assertNull($status->published_at);
    }

    public function test_recalculate_keeps_published_status_when_coverage_is_kept(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');
        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: true,
        ));

        $category->putTranslation('name', 'es-ES', 'Frutas 2', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');

        $status = $category->translationStatus()->forLocale('es-ES')->first();
        $this->assertSame(TranslationEntityStatus::Published, $status->status);
        $this->assertNotNull($status->published_at);
    }

    public function test_recalculate_downgrades_published_to_incomplete_when_coverage_is_lost(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');
        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: true,
        ));

        $category->translations()->delete();

        $category->recalculateTranslationStatus('es-ES');

        $status = $category->translationStatus()->forLocale('es-ES')->first();
        $this->assertSame(TranslationEntityStatus::Incomplete, $status->status);
        $this->assertNull($status->published_at);
        $this->assertFalse($category->fresh()->isPublished('es-ES'));
    }

    public function test_localized_works_with_eager_loaded_relations(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);
        $category->recalculateTranslationStatus('es-ES');
        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'category',
            translatableId: $category->id,
            locale: 'es-ES',
            publish: true,
        ));

        Config::set('app.locale', 'es-ES');

        $localized = Category::query()->withTranslationData()
            ->whereKey($category->id)
            ->get()
            ->firstOrFail()
            ->localized('name');

        $this->assertSame('Frutas', $localized);
    }

    public function test_translation_status_recalculates_to_incomplete_when_value_becomes_empty(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', '   ', TranslationFieldStatus::Approved);
        $category->refresh();

        $this->assertFalse($category->isComplete('es-ES'));

        $status = $category->translationStatus()->forLocale('es-ES')->first();
        $this->assertNull($status);
    }

    public function test_translation_model_uses_morph_alias_in_database(): void
    {
        $category = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'color' => '#FF0000']);
        $category->putTranslation('name', 'es-ES', 'Frutas', TranslationFieldStatus::Approved);

        $row = Translation::query()->firstOrFail();

        $this->assertSame('category', $row->translatable_type);
        $this->assertSame($category->id, $row->translatable_id);
        $this->assertSame('category', $category->translatableType());
    }
}
