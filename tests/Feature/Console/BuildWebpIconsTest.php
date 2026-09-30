<?php

namespace Tests\Feature\Console;

use Imagick;
use Tests\TestCase;

class BuildWebpIconsTest extends TestCase
{
    public function test_generated_icon_catalog_is_valid(): void
    {
        $this->artisan('icons:build-webp', ['--check' => true])
            ->assertSuccessful();

        $path = public_path('icons/webp/avocado.webp');
        $imageSize = getimagesize($path);

        $this->assertIsArray($imageSize);
        $this->assertSame(128, $imageSize[0]);
        $this->assertSame(128, $imageSize[1]);
        $this->assertSame('image/webp', $imageSize['mime']);
        $this->assertSame('VP8L', substr((string) file_get_contents($path), 12, 4));

        $image = new Imagick($path);
        $alpha = $image->exportImagePixels(0, 0, 128, 128, 'A', Imagick::PIXEL_DOUBLE);
        $image->clear();

        $this->assertLessThan(1.0, min($alpha));
    }
}
