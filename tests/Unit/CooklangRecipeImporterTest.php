<?php

namespace Tests\Unit;

use App\Services\Recipes\CooklangParser;
use App\Services\Recipes\Import\CooklangRecipeImporter;
use App\Services\Recipes\Import\DurationParser;
use App\Services\Recipes\Import\IngredientTextParser;
use App\Services\Recipes\IngredientMatcher;
use PHPUnit\Framework\TestCase;

class CooklangRecipeImporterTest extends TestCase
{
    private CooklangRecipeImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new CooklangRecipeImporter(
            new DurationParser,
            new IngredientTextParser,
            new CooklangParser,
            new IngredientMatcher,
        );
    }

    public function test_parses_simple_cooklang_with_ingredients_and_steps(): void
    {
        $cooklang = <<<'COOKLANG'
            Mix @flour{2%cup} and @sugar{1/2%cup} in a bowl.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        $this->assertSame('Receta importada', $result->recipe->name);
        $this->assertCount(2, $result->recipe->ingredients);
        $this->assertSame('flour', $result->recipe->ingredients[0]->name);
        $this->assertSame(2.0, $result->recipe->ingredients[0]->quantity);
        $this->assertSame('cup', $result->recipe->ingredients[0]->unit);
        $this->assertSame('sugar', $result->recipe->ingredients[1]->name);
        $this->assertCount(1, $result->recipe->steps);
    }

    public function test_parses_front_matter_tags(): void
    {
        $cooklang = <<<'COOKLANG'
            ---
            tags:
              - lunch
              - quick
            ---

            @bread{2%slice}
            Toast the @bread{}.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        $this->assertSame(['lunch', 'quick'], $result->recipe->tags);
    }

    public function test_parses_front_matter_title(): void
    {
        $cooklang = <<<'COOKLANG'
            ---
            title: My Recipe
            servings: 4
            ---

            @olive oil{1%tbsp}
            Heat the @olive oil{}.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        $this->assertSame('My Recipe', $result->recipe->name);
        $this->assertSame(4, $result->recipe->servings);
    }

    public function test_parses_cookware(): void
    {
        $cooklang = <<<'COOKLANG'
            #pot{1}
            #knife{1}

            Boil water in the #pot{}.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        $this->assertCount(2, $result->recipe->cookware);
        $this->assertSame('pot', $result->recipe->cookware[0]->name);
        $this->assertSame('knife', $result->recipe->cookware[1]->name);
    }

    public function test_parses_timers(): void
    {
        $cooklang = <<<'COOKLANG'
            Bake for ~bake{30 minutes}.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        $this->assertCount(1, $result->recipe->steps);
        $this->assertCount(1, $result->recipe->steps[0]->timers);
        $this->assertSame('bake', $result->recipe->steps[0]->timers[0]->name);
    }

    public function test_unbraced_unicode_cookware_before_a_timer_is_parsed_separately(): void
    {
        $result = $this->importer->import(
            'Calentar @aceite{2%cucharadas} en la #sartén durante ~precalentar{5 minutos}.',
            collect(),
        );

        $this->assertCount(1, $result->recipe->ingredients);
        $this->assertCount(1, $result->recipe->cookware);
        $this->assertSame('sartén', $result->recipe->cookware[0]->name);
        $this->assertCount(1, $result->recipe->steps[0]->timers);
        $this->assertSame('precalentar', $result->recipe->steps[0]->timers[0]->name);
        $this->assertSame(
            'Calentar @aceite{2%cucharadas} en la #sartén durante ~precalentar{5 minutos}.',
            $result->recipe->steps[0]->description,
        );
    }

    public function test_multiple_steps_separated_by_blank_lines(): void
    {
        $cooklang = <<<'COOKLANG'
            @egg{2}
            Beat the @egg{}.

            @flour{1%cup}
            Add @flour{} and mix.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        $this->assertCount(2, $result->recipe->steps);
        $this->assertCount(2, $result->recipe->ingredients);
    }

    public function test_returns_empty_recipe_for_minimal_content(): void
    {
        $cooklang = 'Just some plain text without any cooklang syntax.';

        $result = $this->importer->import($cooklang, collect());

        $this->assertSame('Receta importada', $result->recipe->name);
        $this->assertCount(0, $result->recipe->ingredients);
        $this->assertCount(1, $result->recipe->steps);
        $this->assertSame('cooklang', $result->source);
    }

    public function test_parses_front_matter_prep_time_and_cook_time(): void
    {
        $cooklang = <<<'COOKLANG'
            ---
            title: Stuffed Peppers
            servings: 4
            prep time: 20 minutes
            cook time: 35 minutes
            ---

            Cook @rice{200%g} according to package directions.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        $this->assertSame('Stuffed Peppers', $result->recipe->name);
        $this->assertSame(4, $result->recipe->servings);
        $this->assertSame(20 * 60, $result->recipe->prepTimeSeconds);
        $this->assertSame(35 * 60, $result->recipe->cookTimeSeconds);
    }

    public function test_parses_section_headers_without_closing_equals(): void
    {
        $cooklang = <<<'COOKLANG'
            = Filling

            Cook @rice{200%g} according to package directions.

            = Assembly

            Cut the tops off @bell peppers{4} and remove seeds.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        $this->assertCount(2, $result->recipe->sections);
        $this->assertSame('Filling', $result->recipe->sections[0]->name);
        $this->assertSame('Assembly', $result->recipe->sections[1]->name);
        $this->assertCount(2, $result->recipe->steps);
        $this->assertSame($result->recipe->sections[0]->client_id, $result->recipe->steps[0]->section_id);
        $this->assertSame($result->recipe->sections[1]->client_id, $result->recipe->steps[1]->section_id);
    }

    public function test_full_recipe_with_frontmatter_and_sections(): void
    {
        $cooklang = <<<'COOKLANG'
            ---
            title: Stuffed Peppers
            tags: [dinner, vegetarian]
            servings: 4
            prep time: 20 minutes
            cook time: 35 minutes
            ---

            > These freeze well.

            = Filling

            Cook @rice{200%g} according to package directions.

            Sauté @onion{1}(diced) and @garlic{3%cloves}(minced) in @olive oil{2%tbsp}
            in a #large skillet{} until softened, about ~{5%minutes}.

            = Assembly

            Cut the tops off @bell peppers{4} and remove seeds.
            Stuff with the filling and place in a #baking dish{}.

            Bake in a preheated #oven{} at 190°C for ~{30%minutes} until peppers
            are tender and cheese is bubbling.
            COOKLANG;

        $result = $this->importer->import($cooklang, collect());

        // Frontmatter
        $this->assertSame('Stuffed Peppers', $result->recipe->name);
        $this->assertSame(4, $result->recipe->servings);
        $this->assertSame(20 * 60, $result->recipe->prepTimeSeconds);
        $this->assertSame(35 * 60, $result->recipe->cookTimeSeconds);
        $this->assertSame(['dinner', 'vegetarian'], $result->recipe->tags);

        // Sections
        $this->assertCount(2, $result->recipe->sections);
        $this->assertSame('Filling', $result->recipe->sections[0]->name);
        $this->assertSame('Assembly', $result->recipe->sections[1]->name);

        // Steps: Cook, Sauté, Cut+Stuff, Bake
        $this->assertCount(4, $result->recipe->steps);
        $this->assertSame($result->recipe->sections[0]->client_id, $result->recipe->steps[0]->section_id);
        $this->assertSame($result->recipe->sections[0]->client_id, $result->recipe->steps[1]->section_id);
        $this->assertSame($result->recipe->sections[1]->client_id, $result->recipe->steps[2]->section_id);
        $this->assertSame($result->recipe->sections[1]->client_id, $result->recipe->steps[3]->section_id);

        // Ingredients: rice, onion, garlic, olive oil, bell peppers
        $this->assertCount(5, $result->recipe->ingredients);

        // Cookware
        $this->assertCount(3, $result->recipe->cookware);

        // Timers (Sauté step has 1 timer, Bake step has 1 timer)
        $this->assertCount(1, $result->recipe->steps[1]->timers);
        $this->assertCount(1, $result->recipe->steps[3]->timers);
    }
}
