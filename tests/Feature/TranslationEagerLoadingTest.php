<?php

namespace Tests\Feature;

use App\Actions\Translations\PublishTranslation;
use App\Data\Translations\PublishTranslationData;
use App\Enums\TranslationFieldStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TranslationEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_localized_with_eager_loaded_relations_does_not_fire_queries_per_model(): void
    {
        Config::set('app.locale', 'es-ES');

        foreach (['Fruits', 'Vegetables', 'Dairy'] as $name) {
            $category = Category::create(['name' => $name, 'slug' => Str::slug($name), 'color' => '#FF0000']);
            $category->putTranslation('name', 'es-ES', 'ES '.$name, TranslationFieldStatus::Approved);
            $category->recalculateTranslationStatus('es-ES');
            app(PublishTranslation::class)->execute(new PublishTranslationData(
                translatableType: 'category',
                translatableId: $category->id,
                locale: 'es-ES',
                publish: true,
            ));
        }

        DB::enableQueryLog();

        $names = Category::query()->withTranslationData()->get()
            ->map(fn (Category $category): ?string => $category->localized('name'));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(['ES Fruits', 'ES Vegetables', 'ES Dairy'], $names->all());
        // 1 query for categories + 1 for translations + 1 for translation statuses.
        $this->assertSame(3, $queryCount);
    }

    public function test_localized_without_eager_load_still_resolves_but_loads_relations_on_demand(): void
    {
        Config::set('app.locale', 'es-ES');

        $store = Store::create(['name' => 'Groceries', 'slug' => 'groceries']);
        $store->putTranslation('name', 'es-ES', 'Ultramarinos', TranslationFieldStatus::Approved);
        $store->recalculateTranslationStatus('es-ES');
        app(PublishTranslation::class)->execute(new PublishTranslationData(
            translatableType: 'store',
            translatableId: $store->id,
            locale: 'es-ES',
            publish: true,
        ));

        DB::enableQueryLog();

        $loaded = Store::query()->findOrFail($store->id);
        $loaded->loadMissing(['translations', 'translationStatus']);
        $name = $loaded->localized('name');

        $this->assertSame('Ultramarinos', $name);
        $this->assertSame('Groceries', $loaded->name);
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_morph_aliases_are_resolved_for_all_translatable_models(): void
    {
        $this->assertSame('category', Category::create(['name' => 'A', 'slug' => 'a', 'color' => '#000000'])->translatableType());
        $this->assertSame('store', Store::create(['name' => 'B', 'slug' => 'b'])->translatableType());
        $this->assertSame('product', (new Product)->translatableType());
        $this->assertSame('tag', (new Tag)->translatableType());
    }
}
