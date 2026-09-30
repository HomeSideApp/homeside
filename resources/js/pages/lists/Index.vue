<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    House,
    ListFilter,
    LockKeyhole,
    Plus,
    ShoppingBasket,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import {
    create as createHouseholdList,
    show as showHouseholdList,
} from '@/routes/households/lists';
import {
    create as createPersonalList,
    index as listsIndex,
    show as showPersonalList,
} from '@/routes/lists';

type ListScope = 'all' | 'personal' | 'household';

interface HouseholdSummary {
    id: string;
    name: string;
    color: string | null;
    image_url: string | null;
}

interface ShoppingListSummary {
    id: string;
    name: string;
    created_at: string;
    items_count: number;
    pending_items_count: number;
    creator: { id: string; name: string };
    household: HouseholdSummary | null;
}

const props = defineProps<{
    lists: ShoppingListSummary[];
    households: HouseholdSummary[];
    activeHouseholdId: string | null;
    filters: {
        search: string;
        scope: ListScope;
        household: string | null;
    };
}>();

const { t } = useI18n();
const search = ref(props.filters.search);
const scope = ref<ListScope>(props.filters.scope);
const household = ref(props.filters.household ?? 'all');
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

function onSearchInput(): void {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(updateFilters, 300);
}

const selectedHouseholdForCreate = computed(() => {
    const preferredHouseholdId =
        household.value !== 'all' ? household.value : props.activeHouseholdId;

    return (
        props.households.find((item) => item.id === preferredHouseholdId) ??
        props.households[0] ??
        null
    );
});

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        scope.value !== 'all' ||
        household.value !== 'all',
);

/** The filter panel starts open when filters are already applied. */
const showFilters = ref(hasActiveFilters.value);

function updateFilters(): void {
    router.get(
        listsIndex.url(),
        {
            search: search.value || undefined,
            scope: scope.value !== 'all' ? scope.value : undefined,
            household: household.value !== 'all' ? household.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function onScopeChange(value: unknown): void {
    scope.value = value as ListScope;

    if (scope.value === 'personal') {
        household.value = 'all';
    }

    updateFilters();
}

function onHouseholdChange(value: unknown): void {
    household.value = String(value);

    if (household.value !== 'all') {
        scope.value = 'household';
    }

    updateFilters();
}

function clearFilters(): void {
    search.value = '';
    scope.value = 'all';
    household.value = 'all';
    updateFilters();
}

function showList(list: ShoppingListSummary): string {
    return list.household
        ? showHouseholdList.url({
              household: list.household.id,
              list: list.id,
          })
        : showPersonalList.url(list.id);
}

function householdInitial(name: string): string {
    return name.charAt(0).toUpperCase();
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Listas', href: '/lists' }] },
});
</script>

<template>
    <Head :title="t('lists.index.title')" />

    <div class="flex flex-col gap-6 px-4 py-6 sm:px-8">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="t('lists.index.title')"
                :description="t('lists.index.description')"
            />
            <TooltipProvider>
                <div class="flex shrink-0 flex-nowrap items-center gap-2">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button type="button" variant="outline" size="icon-sm" :aria-label="t('common.actions.filters')" :aria-expanded="showFilters" :aria-controls="showFilters ? 'list-filters' : undefined" data-test="toggle-list-filters" @click="showFilters = !showFilters">
                                <ListFilter aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('common.actions.filters') }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button v-can="'households.lists.create'" variant="outline" size="icon-sm" as-child>
                                <Link :href="createPersonalList.url()" :aria-label="t('lists.index.newPersonalList')">
                                    <LockKeyhole aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('lists.index.newPersonalList') }}</TooltipContent>
                    </Tooltip>
                    <Tooltip v-if="selectedHouseholdForCreate">
                        <TooltipTrigger as-child>
                            <Button v-can="'households.lists.create'" size="icon-sm" as-child>
                                <Link :href="createHouseholdList.url(selectedHouseholdForCreate.id)" :aria-label="t('lists.index.newHouseholdList', { household: selectedHouseholdForCreate.name })">
                                    <Plus aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('lists.index.newHouseholdList', { household: selectedHouseholdForCreate.name }) }}</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </div>

        <Card v-if="showFilters" id="list-filters" class="gap-3 py-3">
            <CardContent class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-medium">
                        {{ t('lists.index.filters.title') }}
                    </p>
                    <span class="text-xs text-muted-foreground">
                        {{ t('lists.index.results', { count: lists.length }) }}
                    </span>
                </div>

                <FieldGroup class="grid gap-3 md:grid-cols-3">
                    <Field class="md:col-span-3">
                        <SearchInput
                            v-model="search"
                            always-open
                            input-class="w-full"
                            :placeholder="
                                t('lists.index.filters.searchPlaceholder')
                            "
                            @update:model-value="onSearchInput"
                        />
                    </Field>

                    <Field>
                        <FieldLabel for="list-scope-filter">
                            {{ t('lists.index.filters.scopeLabel') }}
                        </FieldLabel>
                        <Select
                            :model-value="scope"
                            @update:model-value="onScopeChange"
                        >
                            <SelectTrigger
                                id="list-scope-filter"
                                class="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        {{ t('lists.index.filters.allScopes') }}
                                    </SelectItem>
                                    <SelectItem value="personal">
                                        {{ t('lists.index.filters.personal') }}
                                    </SelectItem>
                                    <SelectItem value="household">
                                        {{ t('lists.index.filters.household') }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field
                        v-if="households.length > 0"
                        :data-disabled="scope === 'personal' || undefined"
                    >
                        <FieldLabel for="list-household-filter">
                            {{ t('lists.index.filters.householdLabel') }}
                        </FieldLabel>
                        <Select
                            :model-value="household"
                            :disabled="scope === 'personal'"
                            @update:model-value="onHouseholdChange"
                        >
                            <SelectTrigger
                                id="list-household-filter"
                                class="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        {{
                                            t(
                                                'lists.index.filters.allHouseholds',
                                            )
                                        }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="item in households"
                                        :key="item.id"
                                        :value="item.id"
                                    >
                                        {{ item.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                </FieldGroup>

                <div v-if="hasActiveFilters" class="flex justify-end">
                    <Button variant="ghost" size="sm" @click="clearFilters">
                        <X data-icon="inline-start" />
                        {{ t('lists.index.filters.clear') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Empty v-if="lists.length === 0" class="border">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <ShoppingBasket />
                </EmptyMedia>
                <EmptyTitle>{{ t('lists.index.emptyTitle') }}</EmptyTitle>
                <EmptyDescription>
                    {{ t('lists.index.emptyDescription') }}
                </EmptyDescription>
            </EmptyHeader>
            <EmptyContent v-if="hasActiveFilters">
                <Button variant="outline" @click="clearFilters">
                    <X data-icon="inline-start" />
                    {{ t('lists.index.filters.clear') }}
                </Button>
            </EmptyContent>
        </Empty>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="list in lists"
                :key="list.id"
                class="group overflow-hidden transition-colors hover:border-primary"
            >
                <div
                    v-if="list.household?.image_url"
                    class="aspect-video w-full overflow-hidden"
                >
                    <img
                        :src="list.household.image_url"
                        :alt="list.household.name"
                        class="size-full object-cover transition-transform group-hover:scale-105"
                    />
                </div>
                <div
                    v-else-if="list.household"
                    class="flex aspect-video w-full items-center justify-center bg-muted text-5xl font-bold text-white"
                    :style="{
                        backgroundColor: list.household.color ?? undefined,
                    }"
                >
                    <span v-if="list.household.color">
                        {{ householdInitial(list.household.name) }}
                    </span>
                    <House v-else class="size-14 text-muted-foreground" />
                </div>
                <div
                    v-else
                    class="flex aspect-video w-full items-center justify-center bg-muted"
                >
                    <LockKeyhole class="size-14 text-muted-foreground" />
                </div>

                <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-col gap-1">
                            <CardTitle class="truncate text-base">
                                {{ list.name }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    list.household
                                        ? list.household.name
                                        : t('lists.index.personalOwner')
                                }}
                            </CardDescription>
                        </div>
                        <Badge
                            :variant="list.household ? 'default' : 'secondary'"
                        >
                            {{
                                list.household
                                    ? t('lists.household')
                                    : t('lists.private')
                            }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent
                    class="flex flex-col gap-2 text-sm text-muted-foreground"
                >
                    <p>
                        {{
                            t('lists.index.itemCount', {
                                count: list.items_count,
                            })
                        }}
                    </p>
                    <p>
                        {{
                            t('lists.index.pendingCount', {
                                count: list.pending_items_count,
                            })
                        }}
                    </p>
                    <p v-if="list.household">
                        {{
                            t('lists.index.createdBy', {
                                name: list.creator.name,
                            })
                        }}
                    </p>
                </CardContent>
                <CardFooter>
                    <Button
                        v-can="'households.lists.show'"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        as-child
                    >
                        <Link :href="showList(list)">
                            {{ t('lists.index.view') }}
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </div>
</template>
