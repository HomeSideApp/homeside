<?php

namespace App\Enums;

enum AppLocale: string
{
    case EnglishUnitedStates = 'en-US';
    case SpanishSpain = 'es-ES';

    public static function default(): self
    {
        return self::EnglishUnitedStates;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $locale): string => $locale->value,
            self::cases(),
        );
    }

    /**
     * @return list<array{code: string, language: string, region: string, label: string, direction: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $locale): array => [
                'code' => $locale->value,
                'language' => $locale->language(),
                'region' => $locale->region(),
                'label' => $locale->label(),
                'direction' => 'ltr',
            ],
            self::cases(),
        );
    }

    public static function resolve(?string $locale): self
    {
        return self::tryFrom($locale ?? '') ?? self::default();
    }

    /**
     * Map a provider's BCP-47 tag (`es`, `es-419`, `en-GB`, `pt-BR`) to a
     * supported locale.
     *
     * Providers report a language, not one of our exact tags, so the primary
     * subtag decides. Anything unknown falls back to the application default,
     * which is English: an account must never be born in a language nobody
     * chose.
     */
    public static function fromLanguageTag(?string $tag): self
    {
        $language = mb_strtolower(mb_substr(trim((string) $tag), 0, 2));

        return match ($language) {
            self::SpanishSpain->language() => self::SpanishSpain,
            default => self::default(),
        };
    }

    public function language(): string
    {
        return match ($this) {
            self::EnglishUnitedStates => 'en',
            self::SpanishSpain => 'es',
        };
    }

    public function region(): string
    {
        return match ($this) {
            self::EnglishUnitedStates => 'US',
            self::SpanishSpain => 'ES',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::EnglishUnitedStates => 'English (United States)',
            self::SpanishSpain => 'Español (España)',
        };
    }
}
