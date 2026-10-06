import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { createContext, SourceTextModule, SyntheticModule } from 'node:vm';
import { compileScript, parse } from '@vue/compiler-sfc';
import { useVModel } from '@vueuse/core';
import ts from 'typescript';
import * as Vue from 'vue';

globalThis.Document ??= class {};
globalThis.ShadowRoot ??= class {};

async function mountSearch(t, householdId = null) {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const visits = [];
    const posts = [];
    const context = createContext({
        console,
        setTimeout: (...args) => setTimeout(...args),
        clearTimeout: (timer) => clearTimeout(timer),
    });
    const stub = (exports) =>
        new SyntheticModule(
            Object.keys(exports),
            function () {
                for (const [name, value] of Object.entries(exports)) {
                    this.setExport(name, value);
                }
            },
            { context },
        );
    const modules = {
        vue: stub(Vue),
        '@vueuse/core': stub({ useVModel }),
        '@/lib/utils': stub({
            cn: (...classes) => classes.filter(Boolean).join(' '),
        }),
        '@inertiajs/vue3': stub({
            router: {
                visit: (url, options) => visits.push({ url, options }),
                post: (url, data, options) =>
                    posts.push({ url, data, options }),
            },
        }),
        'vue-i18n': stub({ useI18n: () => ({ t: (key) => key }) }),
        '@/components/ui/button': stub({
            Button: Vue.defineComponent({
                setup:
                    (_, { slots }) =>
                    () =>
                        Vue.h('button', slots.default?.()),
            }),
        }),
        './ProductCard.vue': stub({
            default: Vue.defineComponent({
                props: ['product'],
                setup: (props) => () => Vue.h('product', props.product.name),
            }),
        }),
        '@/composables/useShoppingListRoutes': stub({
            useShoppingListRoutes: () => ({
                searchProducts: (_, query) =>
                    `/search?q=${encodeURIComponent(query)}`,
                quickCreateProduct: () => '/create-product',
                storeItem: () => '/add-product',
            }),
        }),
    };

    async function component(path) {
        const source = await readFile(
            new URL(`../../resources/js/${path}`, import.meta.url),
            'utf8',
        );
        const { descriptor } = parse(source, { filename: path });
        const script = compileScript(descriptor, {
            id: path,
            inlineTemplate: true,
        });
        const compiled = ts.transpileModule(script.content, {
            compilerOptions: {
                module: ts.ModuleKind.ESNext,
                target: ts.ScriptTarget.ESNext,
            },
        });
        const module = new SourceTextModule(compiled.outputText, { context });
        await module.link((name) => {
            assert.ok(
                modules[name],
                `Unexpected component dependency: ${name}`,
            );

            return modules[name];
        });
        await module.evaluate();

        return module.namespace.default;
    }

    modules['@/components/ui/input'] = stub({
        Input: await component('components/ui/input/Input.vue'),
    });
    const ProductSearch = await component('components/lists/ProductSearch.vue');
    const element = (type, text = '') => ({
        type,
        text,
        props: {},
        children: [],
        listeners: {},
        getRootNode() {
            return { activeElement: null };
        },
        addEventListener(name, callback) {
            this.listeners[name] = callback;
        },
        removeEventListener(name) {
            delete this.listeners[name];
        },
    });
    const renderer = Vue.createRenderer({
        createElement: element,
        createText: (text) => element('text', text),
        createComment: (text) => element('comment', text),
        setText: (node, text) => {
            node.text = text;
        },
        setElementText: (node, text) => {
            node.text = text;
            node.children = [];
        },
        patchProp: (node, key, _, value) => {
            node.props[key] = value;
        },
        parentNode: (node) => node.parent,
        nextSibling: (node) =>
            node.parent?.children[node.parent.children.indexOf(node) + 1],
        insert(node, parent, anchor = null) {
            node.parent?.children.splice(node.parent.children.indexOf(node), 1);

            node.parent = parent;
            const index = anchor
                ? parent.children.indexOf(anchor)
                : parent.children.length;
            parent.children.splice(index, 0, node);
        },
        remove(node) {
            node.parent?.children.splice(node.parent.children.indexOf(node), 1);
            node.parent = null;
        },
    });
    const root = element('root');
    const app = renderer.createApp(ProductSearch, {
        listId: 'list',
        householdId,
        initialResults: [],
    });
    app.mount(root);
    t.after(() => app.unmount());
    const nodes = (node = root) => [
        node,
        ...node.children.flatMap((child) => nodes(child)),
    ];
    const createCard = () =>
        nodes().find(
            (node) => node.type === 'button' && node.props.type === 'button',
        );
    const input = () => nodes().find((node) => node.type === 'input');

    async function type(query, advanceTimer = true) {
        input().value = query;
        const event = { target: input() };
        input().listeners.input(event);
        input().props.onInput?.(event);
        await Vue.nextTick();

        if (advanceTimer) {
            t.mock.timers.tick(200);
        }

        await Vue.nextTick();
    }

    async function respond(visit, results = []) {
        visit.options.onSuccess?.({ props: { searchResults: results } });
        await Vue.nextTick();
    }

    return { type, respond, visits, posts, createCard, input, nodes };
}

for (const householdId of [null, 'household']) {
    test(`an empty search shows a working creation card (${householdId ?? 'private'})`, async (t) => {
        const search = await mountSearch(t, householdId);

        await search.type('X');
        assert.equal(search.visits.length, 1);
        assert.equal(search.visits[0].url, '/search?q=X');
        assert.equal(search.createCard(), undefined);
        await search.respond(search.visits[0]);

        assert.ok(search.createCard());
        search.createCard().props.onClick();
        assert.equal(search.posts[0].url, '/create-product');
        assert.equal(search.posts[0].data.name, 'X');
        search.posts[0].options.onSuccess();
        await Vue.nextTick();
        assert.equal(search.createCard(), undefined);
    });
}

test('consecutive empty searches each show the creation card', async (t) => {
    const search = await mountSearch(t);

    await search.type('Missing');
    await search.respond(search.visits[0]);
    assert.ok(search.createCard());
    await search.type('Another missing');
    assert.equal(search.createCard(), undefined);
    await search.respond(search.visits[1]);

    assert.ok(search.createCard());
});

test('matching products hide creation and stale results cannot restore it', async (t) => {
    const search = await mountSearch(t);

    await search.type('Missing');
    await search.type('Pan');
    await search.respond(search.visits[1], [{ id: 'bread', name: 'Pan' }]);
    await search.respond(search.visits[0]);

    assert.equal(search.createCard(), undefined);
    assert.ok(
        search
            .nodes()
            .some((node) => node.type === 'product' && node.text === 'Pan'),
    );
});

test('clearing the input ignores a pending response', async (t) => {
    const search = await mountSearch(t);

    await search.type('Missing');
    search.input().props.onKeydown({ key: 'Escape' });
    await Vue.nextTick();
    await search.respond(search.visits[0]);

    assert.equal(search.createCard(), undefined);
    assert.equal(search.visits.length, 1);
});

test('clearing the input cancels a search before it is sent', async (t) => {
    const search = await mountSearch(t);

    await search.type('Missing', false);
    search.input().props.onKeydown({ key: 'Escape' });
    await Vue.nextTick();
    t.mock.timers.tick(200);

    assert.equal(search.visits.length, 0);
    assert.equal(search.createCard(), undefined);
});
