<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Plus,
    Upload,
    Folder,
    FolderCog,
    Pencil,
    ListFilter,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import { create as createRecipe, importMethod as importRecipe } from '@/routes/recipes';
import { index as collectionsIndex } from '@/routes/recipes/collections';
import type { Paginated } from '@/types';

interface Recipe {
    id: string;
    name: string;
    description: string | null;
    cover_image_url: string | null;
    servings: number | null;
    difficulty: string | null;
    owner_id: string;
    tags: string[];
    created_at: string;
    collection_path: string | null;
}

const props = defineProps<{
    recipes: Paginated<Recipe>;
    filters: {
        search: string;
        tag: string | null;
        collection: string | null;
    };
    collections: Array<{ id: string; path: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recetas', href: '/recipes' }] },
});

const { t } = useI18n();

const searchValue = ref(props.filters.search ?? '');

function debounce(
    fn: (...args: any[]) => void,
    delay: number,
): (...args: any[]) => void {
    let timeoutId: ReturnType<typeof setTimeout>;

    return (...args: any[]) => {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn(...args), delay);
    };
}

/** The filter panel starts open when a filter is already applied. */
const showFilters = ref(
    (props.filters.search ?? '') !== '' ||
        (props.filters.collection ?? null) !== null,
);

const updateSearch = debounce((value: string) => {
    const url = new URL(window.location.href);

    if (value) {
        url.searchParams.set('search', value);
    } else {
        url.searchParams.delete('search');
    }

    url.searchParams.delete('page');
    router.get(
        url.pathname + url.search,
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}, 300);

function onSearchInput(value: string) {
    searchValue.value = value;
    updateSearch(value);
}

function onCollectionChange(event: Event) {
    const collection = (event.target as HTMLSelectElement).value;
    const url = new URL(window.location.href);

    if (collection) {
        url.searchParams.set('collection', collection);
    } else {
        url.searchParams.delete('collection');
    }

    url.searchParams.delete('page');
    router.get(
        url.pathname + url.search,
        {},
        { preserveState: true, replace: true },
    );
}

function goToPage(page: number) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));
    router.get(
        url.pathname + url.search,
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
}
</script>

<template>
    <Head :title="t('recipes.index.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="t('recipes.index.title')"
                :description="t('recipes.index.description')"
            />
            <TooltipProvider>
                <div class="flex shrink-0 flex-nowrap items-center gap-2">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button type="button" variant="outline" size="icon-sm" :aria-label="t('common.actions.filters')" :aria-expanded="showFilters" :aria-controls="showFilters ? 'recipe-filters' : undefined" data-test="toggle-recipe-filters" @click="showFilters = !showFilters">
                                <ListFilter aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('common.actions.filters') }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="outline" size="icon-sm" as-child>
                                <Link :href="collectionsIndex().url" :aria-label="t('recipes.index.collections')">
                                    <FolderCog aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('recipes.index.collections') }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button v-can="'recipes.import'" variant="outline" size="icon-sm" as-child>
                                <Link :href="importRecipe.url()" :aria-label="t('recipes.index.import')">
                                    <Upload aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('recipes.index.import') }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button v-can="'recipes.create'" size="icon-sm" as-child>
                                <Link :href="createRecipe.url()" :aria-label="t('recipes.index.newRecipe')">
                                    <Plus aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('recipes.index.newRecipe') }}</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </div>

        <!-- Filtros -->
        <div
            v-if="showFilters"
            id="recipe-filters"
            class="flex flex-col gap-3 rounded-xl border bg-card px-6 py-3 sm:flex-row sm:items-center"
        >
            <SearchInput
                v-model="searchValue"
                always-open
                input-class="w-full sm:w-80"
                :placeholder="t('recipes.index.searchPlaceholder')"
                @update:model-value="onSearchInput"
            />
            <select
                :value="filters.collection ?? ''"
                class="flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none sm:w-64"
                @change="onCollectionChange"
            >
                <option value="">Todas las colecciones</option>
                <option
                    v-for="collection in collections"
                    :key="collection.id"
                    :value="collection.id"
                >
                    {{ collection.path }}
                </option>
            </select>
        </div>

        <!-- Lista de recetas -->
        <div
            v-if="recipes.data.length === 0"
            class="rounded-md border border-dashed p-8 text-center"
        >
            <p class="text-sm text-muted-foreground">
                No se encontraron recetas.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card
                v-for="recipe in recipes.data"
                :key="recipe.id"
                class="overflow-hidden"
            >
                <div
                    v-if="recipe.cover_image_url"
                    class="aspect-video w-full overflow-hidden"
                >
                    <img
                        :src="recipe.cover_image_url"
                        :alt="recipe.name"
                        class="h-full w-full object-cover"
                    />
                </div>
                <CardHeader class="pb-2">
                    <CardTitle
                        class="flex items-center justify-between text-base"
                    >
                        <Link
                            :href="`/recipes/${recipe.id}`"
                            class="hover:underline"
                        >
                            {{ recipe.name }}
                        </Link>
                        <Badge
                            v-if="recipe.difficulty"
                            variant="outline"
                            class="ml-2 shrink-0"
                            >{{ recipe.difficulty }}</Badge
                        >
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div
                        v-if="recipe.collection_path"
                        class="flex items-center gap-1 text-xs text-muted-foreground"
                    >
                        <Folder class="h-3.5 w-3.5" />
                        {{ recipe.collection_path }}
                    </div>
                    <p
                        v-if="recipe.description"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ recipe.description }}
                    </p>
                    <div
                        v-if="recipe.servings"
                        class="text-xs text-muted-foreground"
                    >
                        {{ recipe.servings }} porcion{{
                            recipe.servings !== 1 ? 'es' : ''
                        }}
                    </div>
                    <div
                        v-if="recipe.tags && recipe.tags.length > 0"
                        class="flex flex-wrap gap-1"
                    >
                        <Badge
                            v-for="tag in recipe.tags"
                            :key="tag"
                            variant="outline"
                            class="text-xs"
                        >
                            {{ tag }}
                        </Badge>
                    </div>
                    <div class="flex justify-end pt-2">
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        v-can="'recipes.edit'"
                                        variant="ghost"
                                        size="icon-sm"
                                        as-child
                                    >
                                        <Link
                                            :href="`/recipes/${recipe.id}/edit`"
                                            :aria-label="`${t('common.actions.edit')}: ${recipe.name}`"
                                        >
                                            <Pencil aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{{
                                    t('common.actions.edit')
                                }}</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Paginación -->
        <div
            v-if="recipes.last_page > 1"
            class="flex items-center justify-center gap-2"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="recipes.current_page <= 1"
                @click="goToPage(recipes.current_page - 1)"
            >
                {{ t('common.pagination.previous') }}
            </Button>
            <span class="text-sm text-muted-foreground">
                {{
                    t('common.pagination.page', {
                        current: recipes.current_page,
                        last: recipes.last_page,
                    })
                }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="recipes.current_page >= recipes.last_page"
                @click="goToPage(recipes.current_page + 1)"
            >
                {{ t('common.pagination.next') }}
            </Button>
        </div>
    </div>
</template>
