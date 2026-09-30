import { createInertiaApp, router } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { initializeTheme } from '@/composables/useAppearance';
import { vCan } from '@/directives/can';
import { createApplicationI18n } from '@/i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'recipes/Cook':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
    setup({ el, App, props }) {
        const app = createApp({ render: () => h(App, props) });
        const localization = props.initialPage.props.localization;
        const i18n = createApplicationI18n(localization.locale, localization.fallbackLocale);

        app.use(i18n);
        app.directive('can', vCan);

        router.on('navigate', (event) => {
            const nextLocalization = event.detail.page.props.localization;
            i18n.global.locale.value = nextLocalization.locale;
            document.documentElement.lang = nextLocalization.locale;
            document.documentElement.dir = nextLocalization.supportedLocales.find(
                (locale) => locale.code === nextLocalization.locale,
            )?.direction ?? 'ltr';
        });

        if (typeof document !== 'undefined' && el) {
            document.documentElement.lang = localization.locale;
            document.documentElement.dir = localization.supportedLocales.find(
                (locale) => locale.code === localization.locale,
            )?.direction ?? 'ltr';
            app.mount(el);
        }

        return app;
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
