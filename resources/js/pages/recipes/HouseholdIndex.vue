<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ListFilter } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index as householdRecipesIndex } from '@/routes/households/recipes';
import { show as recipeShow } from '@/routes/recipes';

interface HouseholdRecipe {
    id: string;
    household_id: string;
    recipe_id: string;
    shared_by: string;
    recipe: {
        id: string;
        name: string;
        description: string | null;
        servings: number | null;
        created_by: string;
        owner_id: string;
        difficulty: string | null;
        cuisine: string | null;
        cover_image_url: string | null;
        created_at: string;
        updated_at: string;
        owner?: { id: string; name: string };
        tags?: Array<{ id: string; name: string }>;
    };
}

interface Household {
    id: string;
    name: string;
}

const props = defineProps<{
    household: Household;
    householdRecipes: {
        data: HouseholdRecipe[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
    };
}>();

const { t } = useI18n();

const recipes = computed(
    () => props.householdRecipes?.data?.map((hr) => hr.recipe) ?? [],
);

/** The filter panel starts open when a search is already applied. */
const showFilters = ref((props.filters.search ?? '') !== '');

const search = computed({
    get: () => props.filters.search ?? '',
    set: (value: string) => {
        router.get(
            householdRecipesIndex.url(props.household),
            { search: value, page: 1 },
            { preserveState: true, replace: true },
        );
    },
});

function goToPage(page: number) {
    router.get(
        householdRecipesIndex.url(props.household),
        { search: props.filters.search, page },
        { preserveState: true, replace: true },
    );
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recetas', href: '/recipes' }] },
});

function getDifficultyColor(difficulty: string | null): string {
    switch (difficulty) {
        case 'easy':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'medium':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'hard':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
    }
}

function getDifficultyLabel(difficulty: string | null): string {
    switch (difficulty) {
        case 'easy':
            return t('recipes.householdIndex.difficulty.easy');
        case 'medium':
            return t('recipes.householdIndex.difficulty.medium');
        case 'hard':
            return t('recipes.householdIndex.difficulty.hard');
        default:
            return difficulty ?? '';
    }
}
</script>

<template>
    <div class="flex flex-col gap-6 px-8 py-6">
        <Head
            :title="t('recipes.householdIndex.title', { name: household.name })"
        />
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="
                    t('recipes.householdIndex.title', { name: household.name })
                "
                :description="t('recipes.householdIndex.description')"
            />
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon-sm"
                            :aria-label="t('common.actions.filters')"
                            :aria-expanded="showFilters"
                            :aria-controls="
                                showFilters
                                    ? 'household-recipe-filters'
                                    : undefined
                            "
                            @click="showFilters = !showFilters"
                        >
                            <ListFilter aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{
                        t('common.actions.filters')
                    }}</TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>

        <div
            v-if="showFilters"
            id="household-recipe-filters"
            class="flex flex-col gap-3 rounded-xl border bg-card px-6 py-3 sm:flex-row sm:items-center"
        >
            <SearchInput
                v-model="search"
                always-open
                input-class="w-full sm:w-80"
                :placeholder="t('recipes.householdIndex.searchPlaceholder')"
            />
        </div>

        <div
            v-if="recipes.length === 0"
            class="py-12 text-center text-muted-foreground"
        >
            {{ t('recipes.householdIndex.empty') }}
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="recipe in recipes"
                :key="recipe.id"
                :href="recipeShow.url(recipe)"
                class="group block"
            >
                <div
                    class="rounded-lg border bg-card p-4 text-card-foreground shadow-sm transition-shadow hover:shadow-md"
                >
                    <div
                        v-if="recipe.cover_image_url"
                        class="mb-3 aspect-video overflow-hidden rounded-md"
                    >
                        <img
                            :src="recipe.cover_image_url"
                            :alt="recipe.name"
                            class="h-full w-full object-cover"
                        />
                    </div>

                    <h3
                        class="text-lg font-semibold transition-colors group-hover:text-primary"
                    >
                        {{ recipe.name }}
                    </h3>

                    <p
                        v-if="recipe.description"
                        class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ recipe.description }}
                    </p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="recipe.difficulty"
                            :class="getDifficultyColor(recipe.difficulty)"
                        >
                            {{ getDifficultyLabel(recipe.difficulty) }}
                        </Badge>
                        <Badge v-if="recipe.servings" variant="secondary">
                            {{
                                t('recipes.householdIndex.servings', {
                                    count: recipe.servings,
                                })
                            }}
                        </Badge>
                        <Badge v-if="recipe.cuisine" variant="outline">
                            {{ recipe.cuisine }}
                        </Badge>
                    </div>

                    <div
                        v-if="recipe.tags && recipe.tags.length > 0"
                        class="mt-2 flex flex-wrap items-center gap-1"
                    >
                        <Badge
                            v-for="(tag, index) in recipe.tags"
                            :key="
                                typeof tag === 'string'
                                    ? tag
                                    : (tag.id ?? index)
                            "
                            variant="secondary"
                            class="text-xs"
                        >
                            {{ typeof tag === 'string' ? tag : tag.name }}
                        </Badge>
                    </div>

                    <p
                        v-if="recipe.owner"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        {{
                            t('recipes.householdIndex.by', {
                                name: recipe.owner.name,
                            })
                        }}
                    </p>
                </div>
            </Link>
        </div>

        <!-- Paginación -->
        <div
            v-if="householdRecipes.last_page > 1"
            class="flex items-center justify-center gap-2"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="householdRecipes.current_page <= 1"
                @click="goToPage(householdRecipes.current_page - 1)"
            >
                {{ t('common.pagination.previous') }}
            </Button>
            <span class="text-sm text-muted-foreground">
                {{
                    t('common.pagination.page', {
                        current: householdRecipes.current_page,
                        last: householdRecipes.last_page,
                    })
                }}
            </span>
            <Button
                variant="outline"
                size="sm"
                :disabled="
                    householdRecipes.current_page >= householdRecipes.last_page
                "
                @click="goToPage(householdRecipes.current_page + 1)"
            >
                {{ t('common.pagination.next') }}
            </Button>
        </div>
    </div>
</template>
