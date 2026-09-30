<?php

namespace Tests\Feature\Console;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class ConsoleLocalizationTest extends TestCase
{
    public function test_console_uses_english_by_default(): void
    {
        $this->assertSame('en-US', app()->getLocale());
        $this->assertSame('User name', __('console.create_user.name'));
        $this->assertSame('Create user?', __('console.create_user.confirm'));
    }

    public function test_invalid_console_locale_falls_back_to_english(): void
    {
        config()->set('localization.console', 'invalid');

        $this->app->getProvider(AppServiceProvider::class)?->boot();

        $this->assertSame('en-US', app()->getLocale());
    }
}
