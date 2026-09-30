import { createI18n } from 'vue-i18n';
import enUS from '@/locales/en-US';
import esES from '@/locales/es-ES';
import type { AppLocale } from '@/types/auth';

const messages = {
    'en-US': enUS,
    'es-ES': esES,
};

const dateTimeFormats = {
    'en-US': {
        short: { year: 'numeric', month: 'short', day: 'numeric' },
        long: { year: 'numeric', month: 'long', day: 'numeric' },
        dateTime: { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' },
    },
    'es-ES': {
        short: { year: 'numeric', month: 'short', day: 'numeric' },
        long: { year: 'numeric', month: 'long', day: 'numeric' },
        dateTime: { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: 'numeric' },
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

export function createApplicationI18n(locale: AppLocale, fallbackLocale: AppLocale) {
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
