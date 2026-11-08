import { createI18n } from 'vue-i18n';
import type { LocaleMessageValue } from 'vue-i18n';
import enUS from '@/locales/en-US';
import esES from '@/locales/es-ES';
import type { AppLocale } from '@/types/auth';

/** A vue-i18n message object: nested namespaces whose leaves are message strings. */
type LocaleMessageBag = Record<string, LocaleMessageValue>;

/**
 * Bundles shipped by the app itself. This map is the source of truth for the
 * supported locales: locale-fragment matching below derives from its keys.
 */
const baseMessages: Record<AppLocale, LocaleMessageBag> = {
    'en-US': enUS,
    'es-ES': esES,
};

/**
 * Locale fragments owned by optional Composer modules, merged over the base
 * bundles at build time.
 *
 * Contract:
 * - A fragment lives in `resources/js/locales/` and is named
 *   `module-<module>-<locale>.ts`, e.g. `module-saas-en-US.ts`. Its trailing
 *   `<locale>` must be one of the app locales above; a fragment whose locale is
 *   unknown is skipped instead of breaking the build.
 * - Its default export is a vue-i18n message object, e.g.
 *   `{ saas: { plans: { title: 'Plans & Billing' } } }`.
 * - Modules publish the file themselves, e.g. `php artisan saas:install`; the
 *   published files are installation artifacts and are gitignored.
 * - The merge is deep: nested objects merge key by key and a fragment scalar
 *   replaces the base scalar at that path. The fragment wins on conflict
 *   because it owns its namespace.
 * - Zero fragments is supported: the glob resolves to an empty object and the
 *   app runs on the base bundles alone, with no error, warning or placeholder.
 */
const localeFragments = import.meta.glob('./locales/module-*.ts', {
    eager: true,
    import: 'default',
});

function isLocaleMessageBag(value: unknown): value is LocaleMessageBag {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function mergeMessages(
    base: LocaleMessageBag,
    fragment: LocaleMessageBag,
): LocaleMessageBag {
    const merged: LocaleMessageBag = { ...base };

    for (const [key, fragmentValue] of Object.entries(fragment)) {
        const baseValue = merged[key];

        if (
            isLocaleMessageBag(fragmentValue) &&
            isLocaleMessageBag(baseValue)
        ) {
            merged[key] = mergeMessages(baseValue, fragmentValue);
        } else {
            merged[key] = fragmentValue;
        }
    }

    return merged;
}

function resolveMessages(): Record<string, LocaleMessageBag> {
    const messages: Record<string, LocaleMessageBag> = { ...baseMessages };
    const locales = Object.keys(baseMessages);

    for (const [fragmentPath, fragment] of Object.entries(localeFragments)) {
        const locale = locales.find((candidate) =>
            fragmentPath.endsWith(`-${candidate}.ts`),
        );

        if (locale === undefined || !isLocaleMessageBag(fragment)) {
            if (import.meta.env.DEV) {
                console.warn(
                    `[i18n] Ignoring locale fragment "${fragmentPath}": expected module-<module>-<locale>.ts with a vue-i18n message object as default export and a known locale (${locales.join(', ')}).`,
                );
            }

            continue;
        }

        messages[locale] = mergeMessages(messages[locale], fragment);
    }

    return messages;
}

const messages = resolveMessages();

const dateTimeFormats = {
    'en-US': {
        short: { year: 'numeric', month: 'short', day: 'numeric' },
        long: { year: 'numeric', month: 'long', day: 'numeric' },
        dateTime: {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
        },
    },
    'es-ES': {
        short: { year: 'numeric', month: 'short', day: 'numeric' },
        long: { year: 'numeric', month: 'long', day: 'numeric' },
        dateTime: {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
        },
    },
} as const;

const numberFormats = {
    'en-US': {
        decimal: { style: 'decimal', maximumFractionDigits: 2 },
        percent: { style: 'percent', maximumFractionDigits: 1 },
    },
    'es-ES': {
        decimal: { style: 'decimal', maximumFractionDigits: 2 },
        percent: { style: 'percent', maximumFractionDigits: 1 },
    },
} as const;

export function createApplicationI18n(
    locale: AppLocale,
    fallbackLocale: AppLocale,
) {
    return createI18n({
        legacy: false,
        globalInjection: true,
        locale,
        fallbackLocale,
        messages,
        dateTimeFormats,
        numberFormats,
        missingWarn: import.meta.env.DEV,
        fallbackWarn: import.meta.env.DEV,
    });
}

export type ApplicationI18n = ReturnType<typeof createApplicationI18n>;
