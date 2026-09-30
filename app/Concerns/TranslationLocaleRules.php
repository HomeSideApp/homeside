<?php

namespace App\Concerns;

use App\Enums\AppLocale;
use App\Models\Translation;

/**
 * Shared helpers for translation locale fields.
 *
 * Data translations accept any well-formed BCP 47 language tag so admins can
 * add languages beyond the app-UI supported list (AppLocale). The tag must
 * match `language[-Script][-REGION]` (e.g. en-US, es-ES, pt-BR, zh-Hans-CN)
 * and must not be the source locale.
 */
final class TranslationLocaleRules
{
    /**
     * BCP 47 validation regex: 2-8 letter language subtag, optional script
     * (4 letters), optional region (2 letters). Case-insensitive: the tag is
     * canonicalised after matching.
     */
    private const string BCP47_REGEX = '/^([a-z]{2,8})(?:-([a-z]{4}))?(?:-([a-z]{2}))?$/i';

    /**
     * Validation rule for a target translation locale: any well-formed BCP 47
     * tag (2-8 letter language subtag, optional script, optional region),
     * case-insensitive.
     *
     * @return list<string>
     */
    public static function localeRule(): array
    {
        return ['required', 'string', 'max:35', 'regex:'.self::BCP47_REGEX];
    }

    /**
     * Normalise a locale tag to its canonical BCP 47 form: lowercase language,
     * Titlecase script, uppercase region. Returns null when the tag is
     * malformed. Accepts input in any case (e.g. ZH-HANS-CN → zh-Hans-CN).
     */
    public static function normalizeLocaleTag(string $locale): ?string
    {
        if (preg_match(self::BCP47_REGEX, $locale, $matches) !== 1) {
            return null;
        }

        $parts = [strtolower($matches[1])];

        // Non-participating optional groups exist as empty strings, so the
        // emptiness must be checked instead of using isset().
        if (($matches[2] ?? '') !== '') {
            $parts[] = ucfirst(strtolower($matches[2]));
        }

        if (($matches[3] ?? '') !== '') {
            $parts[] = strtoupper($matches[3]);
        }

        return implode('-', $parts);
    }

    /**
     * Locales offered in the admin panel: the app-UI supported locales first,
     * then any extra locales already used in the translations table, so newly
     * added free locales persist in the selector.
     *
     * @return list<array{code: string, language: string, region: string, label: string, direction: string}>
     */
    public static function supportedTranslationLocales(?string $selectedLocale = null): array
    {
        $options = AppLocale::options();

        $existingLocales = Translation::query()
            ->select('locale')
            ->distinct()
            ->pluck('locale');

        if ($selectedLocale !== null) {
            $existingLocales->push($selectedLocale);
        }

        $existing = $existingLocales
            ->filter(fn (string $locale): bool => AppLocale::tryFrom($locale) === null)
            ->unique()
            ->map(fn (string $locale): array => [
                'code' => $locale,
                'language' => explode('-', $locale)[0],
                'region' => explode('-', $locale)[1] ?? '',
                'label' => \Locale::getDisplayName($locale, app()->getLocale()) ?: $locale,
                'direction' => \Locale::isRightToLeft($locale) ? 'rtl' : 'ltr',
            ])
            ->values()
            ->all();

        return [...$options, ...$existing];
    }
}
