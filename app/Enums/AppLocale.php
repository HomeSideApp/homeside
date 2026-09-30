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
