import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { renderToString } from '@vue/server-renderer';
import { createServer } from 'vite';
import { createSSRApp } from 'vue';
import { createI18n } from 'vue-i18n';

let server;
let Index;
const messages = {};

before(async () => {
    server = await createServer({
        configFile: false,
        cacheDir: '/tmp/homeside-list-summary-vite',
        optimizeDeps: { noDiscovery: true, include: [] },
        ssr: { noExternal: ['@inertiajs/vue3'] },
        server: { middlewareMode: true, watch: null, hmr: false },
        resolve: {
            alias: {
                '@': fileURLToPath(
                    new URL('../../resources/js', import.meta.url),
                ),
            },
        },
        plugins: [
            {
                name: 'test-inertia',
                enforce: 'pre',
                resolveId: (id) =>
                    id === '@inertiajs/vue3' ? '\0test-inertia' : null,
                load: (id) =>
                    id === '\0test-inertia'
                        ? `
                    import { h } from 'vue';
                    export const Head = { render: () => null };
                    export const Link = {
                        setup: (_, { slots }) => () => h('a', slots.default?.()),
                    };
                    export const router = { get() {} };
                    export const usePage = () => ({ props: { auth: {} } });
                `
                        : null,
            },
            vue(),
        ],
    });
    Index = (await server.ssrLoadModule('/resources/js/pages/lists/Index.vue'))
        .default;

    for (const locale of ['es-ES', 'en-US']) {
        messages[locale] = (
            await server.ssrLoadModule(`/resources/js/locales/${locale}.ts`)
        ).default;
    }
});

after(async () => {
    await server?.close();
});

async function renderList(pending, locale, shared = false) {
    const app = createSSRApp(Index, {
        lists: [
            {
                id: 'list',
                name: 'Prueba',
                created_at: '2026-10-06',
                items_count: 4,
                pending_items_count: pending,
                creator: { id: 'user', name: 'Ana' },
                household: shared
                    ? {
                          id: 'household',
                          name: 'Casa',
                          color: null,
                          image_url: null,
                      }
                    : null,
            },
        ],
        households: [],
        activeHouseholdId: null,
        can: { createPersonalList: false },
        filters: { search: '', scope: 'all', household: null },
    });
    app.use(createI18n({ legacy: false, locale, messages }));

    return renderToString(app);
}

for (const [locale, shared, pending, expected] of [
    ['es-ES', false, 3, '3 pendientes'],
    ['es-ES', true, 1, '1 pendiente'],
    ['es-ES', false, 0, '0 pendientes'],
    ['en-US', true, 3, '3 pending items'],
]) {
    test(`list summary displays only pending items in a badge (${locale}, ${pending}, ${shared})`, async () => {
        const html = await renderList(pending, locale, shared);
        const badges =
            html.match(
                /<([a-z]+)\b[^>]*data-slot="badge"[^>]*>[\s\S]*?<\/\1>/g,
            ) ?? [];
        const pendingBadge = badges.find((badge) => badge.includes(expected));

        assert.ok(pendingBadge, `Missing pending badge: ${expected}`);
        assert.match(pendingBadge, /<svg\b/);
        assert.match(pendingBadge, /aria-hidden="true"/);
        assert.doesNotMatch(html, /4 productos|4 items/);
    });
}
