<?php

namespace Tests\Unit;

use App\Services\Recipes\CooklangParser;
use PHPUnit\Framework\TestCase;

class CooklangParserTest extends TestCase
{
    public function test_it_parses_ordered_segments_from_a_single_source(): void
    {
        $parser = new CooklangParser;
        $text = 'Calentar @aceite{2%cucharadas} en la #sartén durante ~precalentar{5 minutos}.';

        $segments = $parser->parseSegments($text);

        $this->assertSame(['ingredient', 'cookware', 'timer'], array_column($segments, 'type'));
        $this->assertSame(['aceite', 'sartén', 'precalentar'], array_column($segments, 'content'));
        $this->assertSame(2.0, $segments[0]['quantity']);
        $this->assertSame('cucharadas', $segments[0]['unit']);
        $this->assertSame(300, $segments[2]['duration_seconds']);
        $this->assertSame(
            'Calentar aceite en la sartén durante (5 minutos).',
            $parser->toDisplayText($text),
        );
    }

    public function test_derived_parsers_use_the_shared_segments(): void
    {
        $parser = new CooklangParser;
        $text = 'Mezclar @azúcar moreno{1/2%taza} en #bol grande{} durante ~reposo{1%hora}.';

        $this->assertSame('azúcar moreno', $parser->parseIngredients($text)[0]['name']);
        $this->assertSame(0.5, $parser->parseIngredients($text)[0]['quantity']);
        $this->assertSame('bol grande', $parser->parseCookware($text)[0]['name']);
        $this->assertSame(3600, $parser->parseTimers($text)[0]['duration_seconds']);
    }

    public function test_it_distinguishes_recipe_references_from_ingredients(): void
    {
        $parser = new CooklangParser;
        $text = 'Servir con @./Sauces/Salsa Verde{} y @pan{2%rebanadas}.';

        $segments = $parser->parseSegments($text);

        $this->assertSame(['recipe', 'ingredient'], array_column($segments, 'type'));
        $this->assertSame('./Sauces/Salsa Verde', $segments[0]['content']);
        $this->assertSame([], $parser->parseIngredients('@./Sauces/Salsa Verde{}'));
        $this->assertSame('./Sauces/Salsa Verde', $parser->parseRecipeReferences($text)[0]['path']);
    }
}
