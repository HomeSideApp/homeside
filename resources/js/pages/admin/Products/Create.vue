<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import IconAction from '@/components/admin/IconAction.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { productIconUrl } from '@/lib/product-icons';
import type { IconOption } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    categories: Array<{ id: string; name: string }>;
    icons?: IconOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Productos', href: '/admin/products' },
            { title: 'Crear', href: '#' },
        ],
    },
});

const selectedIcon = ref<string | null>(null);
const iconSearch = ref('');
const allIcons = computed(() => props.icons ?? []);
const loadingIcons = ref(props.icons === undefined);
const selectedIconUrl = computed(() => productIconUrl(selectedIcon.value));

const filteredIcons = computed(() => {
    if (!iconSearch.value) {
        return allIcons.value;
    }

    const q = iconSearch.value.toLowerCase();

    return allIcons.value.filter((icon) => icon.name.toLowerCase().includes(q));
});

onMounted(() => {
    if (props.icons !== undefined) {
        return;
    }

    router.reload({
        only: ['icons'],
        onFinish: () => {
            loadingIcons.value = false;
        },
    });
});

function selectIcon(iconName: string | null) {
    selectedIcon.value = iconName;
}
</script>

<template>
    <Head :title="t('admin.products.createTitle')" />

    <div class="flex max-w-2xl flex-col gap-6 px-8 py-6">
        <h1 class="text-2xl font-bold">
            {{ t('admin.products.createTitle') }}
        </h1>

        <Form
            action="/admin/products"
            method="POST"
            v-slot="{ errors, processing, hasErrors, validate }"
            class="space-y-6"
        >
            <div class="grid gap-2">
                <Label for="name">{{ t('admin.products.name') }}</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    :placeholder="t('admin.products.namePlaceholder')"
                    @blur="validate"
                    @input="validate"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label>{{ t('admin.products.icon') }}</Label>
                <input type="hidden" name="icon" :value="selectedIcon ?? ''" />
                <div v-if="selectedIcon" class="mb-2 flex items-center gap-2">
                    <img
                        v-if="selectedIconUrl"
                        :src="selectedIconUrl"
                        :alt="selectedIcon"
                        class="h-8 w-8 object-contain"
                    />
                    <span class="text-sm text-muted-foreground">{{
                        selectedIcon
                    }}</span>
                    <IconAction
                        :label="t('admin.products.remove')"
                        @click="selectIcon(null)"
                    >
                        <X aria-hidden="true" />
                    </IconAction>
                </div>
                <Input
                    v-model="iconSearch"
                    type="text"
                    :placeholder="t('admin.products.searchIcon')"
                    class="mb-2"
                >
                </Input>
                <div v-if="loadingIcons" class="text-sm text-muted-foreground">
                    {{ t('admin.products.loadingIcons') }}
                </div>
                <div
                    v-else-if="filteredIcons.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('admin.products.noIcons') }}
                </div>
                <div
                    v-else
                    class="grid max-h-48 grid-cols-8 gap-2 overflow-y-auto rounded-md border p-2"
                >
                    <button
                        v-for="icon in filteredIcons"
                        :key="icon.name"
                        type="button"
                        :aria-label="icon.name"
                        :title="icon.name"
                        :aria-pressed="selectedIcon === icon.name"
                        class="flex items-center justify-center rounded-md border p-1 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="{
                            'bg-accent ring-2 ring-primary':
                                selectedIcon === icon.name,
                        }"
                        @click="selectIcon(icon.name)"
                    >
                        <img
                            :src="icon.url"
                            alt=""
                            class="h-8 w-8 object-contain"
                            loading="lazy"
                        />
                    </button>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="category_id">{{
                    t('admin.products.category')
                }}</Label>
                <select
                    name="category_id"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">
                        {{ t('admin.products.noCategory') }}
                    </option>
                    <option
                        v-for="cat in categories"
                        :key="cat.id"
                        :value="cat.id"
                    >
                        {{ cat.name }}
                    </option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0" />
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    checked
                    class="h-4 w-4"
                />
                <Label for="is_active">{{ t('admin.products.active') }}</Label>
            </div>

            <Button :disabled="processing || hasErrors">{{
                t('admin.products.create')
            }}</Button>
        </Form>
    </div>
</template>
