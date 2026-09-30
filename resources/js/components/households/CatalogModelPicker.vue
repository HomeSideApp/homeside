<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Paperclip,
    Brain,
    Wrench,
    FileJson,
    Scale,
    Globe,
    Cpu,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import SearchInput from '@/components/SearchInput.vue';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import type {
    CatalogProvider,
    CatalogModel,
    CatalogPrefillData,
    PaginatedData,
} from '@/types';

const { t } = useI18n();

const props = defineProps<{
    /** Paginated catalog providers from Inertia partial reload. */
    catalogProviders: PaginatedData<CatalogProvider> | null;
    /** Paginated catalog models from Inertia partial reload. */
    catalogModels: PaginatedData<CatalogModel> | null;
    /** Prefill data from Inertia partial reload. */
    catalogPrefill: CatalogPrefillData | null;
    /** URL to hit for catalog search via Inertia partial reload. */
    catalogSearchUrl: string;
}>();

const emit = defineEmits<{
    /** Emitted when a model is selected and prefill data is fetched. */
    select: [prefill: CatalogPrefillData];
}>();

const searchQuery = ref('');
const loadingPrefill = ref(false);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function onSearch() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        if (searchQuery.value.trim() === '') {
            return;
        }

        router.get(
            props.catalogSearchUrl,
            { q: searchQuery.value },
            {
                only: ['catalogProviders', 'catalogModels'],
                preserveState: true,
                replace: true,
            },
        );
    }, 300);
}

function selectProvider(slug: string) {
    router.get(
        props.catalogSearchUrl,
        { provider_slug: slug },
        {
            only: ['catalogModels'],
            preserveState: true,
            replace: true,
        },
    );
}

function selectModel(model: CatalogModel) {
    loadingPrefill.value = true;

    router.get(
        props.catalogSearchUrl,
        {
            provider_slug: model.provider_slug,
            model_id: model.model_id,
        },
        {
            only: ['catalogPrefill'],
            preserveState: true,
            replace: true,
        },
    );
}

// Watch for prefill responses while the catalog dialog is open.
watch(
    () => props.catalogPrefill,
    (prefill) => {
        if (prefill) {
            emit('select', prefill);
            loadingPrefill.value = false;
        }
    },
);

function formatCost(value: number | string | null): string | null {
    if (value === null || value === undefined) {
return null;
}

    const num = typeof value === 'string' ? parseFloat(value) : value;

    if (Number.isNaN(num) || num === 0) {
return null;
}

    if (num < 0.01) {
return `$${(num * 1000).toFixed(2)}/M`;
}

    return `$${num.toFixed(4)}/tok`;
}

function formatTokens(value: number | null): string | null {
    if (value === null) {
return null;
}

    if (value >= 1_000_000) {
return `${(value / 1_000_000).toFixed(1)}M`;
}

    if (value >= 1_000) {
return `${(value / 1_000).toFixed(0)}K`;
}

    return value.toString();
}

const hasResults = (
    providers: PaginatedData<CatalogProvider> | null,
    models: PaginatedData<CatalogModel> | null,
) => {
    return (
        (providers?.data && providers.data.length > 0) ||
        (models?.data && models.data.length > 0)
    );
};
</script>

<template>
    <div class="space-y-4">
        <!-- Unified search -->
        <SearchInput
            v-model="searchQuery"
            always-open
            input-class="w-full"
            :placeholder="t('catalog.searchModels')"
            @update:model-value="onSearch"
        />

        <!-- Loading skeleton -->
        <div
            v-if="searchQuery && !catalogProviders && !catalogModels"
            class="space-y-2"
        >
            <Skeleton v-for="i in 4" :key="i" class="h-14 w-full" />
        </div>

        <!-- Results -->
        <div
            v-else-if="
                searchQuery && hasResults(catalogProviders, catalogModels)
            "
            class="space-y-4"
        >
            <!-- Providers section -->
            <div
                v-if="
                    catalogProviders?.data && catalogProviders.data.length > 0
                "
                class="space-y-2"
            >
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('catalog.providers') }}
                </p>
                <button
                    v-for="provider in catalogProviders.data"
                    :key="provider.slug"
                    class="flex w-full items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-muted"
                    @click="selectProvider(provider.slug)"
                >
                    <img
                        v-if="provider.logo_url"
                        :src="provider.logo_url"
                        :alt="provider.name"
                        class="size-8 rounded"
                    />
                    <Cpu v-else class="size-8 text-muted-foreground" />
                    <div class="flex-1">
                        <span class="text-sm font-medium">{{
                            provider.name
                        }}</span>
                        <p class="text-xs text-muted-foreground">
                            {{ provider.models_count }}
                            {{ t('catalog.models') }}
                        </p>
                    </div>
                </button>
            </div>

            <!-- Models section -->
            <div
                v-if="catalogModels?.data && catalogModels.data.length > 0"
                class="space-y-2"
            >
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('catalog.models') }}
                </p>
                <button
                    v-for="model in catalogModels.data"
                    :key="model.id"
                    class="flex w-full flex-col gap-1 rounded-lg border p-3 text-left transition-colors hover:bg-muted"
                    :disabled="loadingPrefill"
                    @click="selectModel(model)"
                >
                    <div class="flex items-center gap-2">
                        <img
                            v-if="model.provider?.logo_url"
                            :src="model.provider.logo_url"
                            :alt="model.provider?.name"
                            class="size-6 rounded"
                        />
                        <Cpu v-else class="size-6 text-muted-foreground" />
                        <div class="flex-1">
                            <span class="text-sm font-medium">{{
                                model.name
                            }}</span>
                            <span class="ml-2 text-xs text-muted-foreground">{{
                                model.provider?.name
                            }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <Badge
                                v-if="model.reasoning"
                                variant="secondary"
                                class="text-xs"
                            >
                                <Brain class="mr-1 size-3" />
                            </Badge>
                            <Badge
                                v-if="model.attachment"
                                variant="secondary"
                                class="text-xs"
                            >
                                <Paperclip class="mr-1 size-3" />
                            </Badge>
                            <Badge
                                v-if="model.tool_call"
                                variant="secondary"
                                class="text-xs"
                            >
                                <Wrench class="mr-1 size-3" />
                            </Badge>
                            <Badge
                                v-if="model.structured_output"
                                variant="secondary"
                                class="text-xs"
                            >
                                <FileJson class="mr-1 size-3" />
                            </Badge>
                        </div>
                    </div>
                    <div
                        class="ml-8 flex flex-wrap items-center gap-3 text-xs text-muted-foreground"
                    >
                        <span class="font-mono">{{ model.model_id }}</span>
                        <span
                            v-if="model.context_window"
                            class="flex items-center gap-1"
                        >
                            <Scale class="size-3" />
                            {{ formatTokens(model.context_window) }}
                        </span>
                        <span v-if="model.family">
                            {{ model.family }}
                        </span>
                        <span
                            v-if="model.cost_input"
                            class="flex items-center gap-1"
                        >
                            <Globe class="size-3" />
                            {{ formatCost(model.cost_input) }} in /
                            {{ formatCost(model.cost_output) }} out
                        </span>
                    </div>
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="searchQuery"
            class="py-4 text-center text-sm text-muted-foreground"
        >
            {{ t('catalog.noResults') }}
        </div>
    </div>
</template>
