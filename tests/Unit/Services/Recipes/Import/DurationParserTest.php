<?php

namespace Tests\Unit\Services\Recipes\Import;

use App\Services\Recipes\Import\DurationParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DurationParserTest extends TestCase
{
    private DurationParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new DurationParser;
    }

    #[Test]
    public function test_parses_iso_8601_minutes(): void
    {
        $this->assertSame(900, $this->parser->parseIso8601('PT15M'));
    }

    #[Test]
    public function test_parses_iso_8601_hours_and_minutes(): void
    {
        $this->assertSame(5400, $this->parser->parseIso8601('PT1H30M'));
    }

    #[Test]
    public function test_parses_iso_8601_days(): void
    {
        $this->assertSame(86400, $this->parser->parseIso8601('P1D'));
    }

    #[Test]
    public function test_parses_human_minutes(): void
    {
        $this->assertSame(900, $this->parser->parseHuman('15 minutes'));
    }

    #[Test]
    public function test_parses_human_hours_and_minutes(): void
    {
        $this->assertSame(5400, $this->parser->parseHuman('1 hour 30 minutes'));
    }

    #[Test]
    public function test_parses_human_seconds(): void
    {
        $this->assertSame(30, $this->parser->parseHuman('30 seconds'));
    }

    #[Test]
    public function test_auto_detects_iso_8601(): void
    {
        $this->assertSame(900, $this->parser->parse('PT15M'));
    }

    #[Test]
    public function test_auto_detects_human(): void
    {
        $this->assertSame(900, $this->parser->parse('15 minutes'));
    }

    #[Test]
    public function test_iso_8601_throws_on_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parseIso8601('invalid');
    }

    #[Test]
    public function test_human_throws_on_unparseable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parseHuman('no numbers here');
    }

    #[Test]
    public function test_iso_8601_seconds_only(): void
    {
        $this->assertSame(45, $this->parser->parseIso8601('PT45S'));
    }

    #[Test]
    public function test_human_abbreviations(): void
    {
        $this->assertSame(5400, $this->parser->parseHuman('1 hr 30 min'));
    }
}
