<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Check,
    CheckCheck,
    ListFilter,
    Loader2,
    Save,
    Send,
    Sparkles,
    Trash2,
    Undo2,
} from '@lucide/vue';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import IconAction from '@/components/admin/IconAction.vue';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import {
    generate as generateTranslations,
    destroy as destroyTranslation,
    publish as publishTranslation,
    publishBulk as publishTranslationsBulk,
    store as storeTranslation,
    update as updateTranslation,
} from '@/routes/admin/translations';

interface TranslationCell {
    id: string;
    value: string;
    status: string;
}

interface TranslatableEntity {
    id: string;
    type: string;
    name: string | null;
    translations: Record<string, TranslationCell | null>;
    status: string | null;
    published_at: string | null;
    is_complete: boolean;
    coverage: { completed: number; total: number };
    translation_id: string | null;
    field_status: string | null;
}

interface CountSummary {
    incomplete: number;
    complete: number;
    published: number;
    untranslated: number;
}

interface LocaleOption {
    code: string;
    label: string;
}

const props = defineProps<{
    entities: {
        data: TranslatableEntity[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: {
        type: string;
        locale: string;
        status: string;
        search: string | null;
        perPage: number;
    };
    supportedLocales: LocaleOption[];
    sourceLocale: string;
    counts: CountSummary;
}>();

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'admin.translations.title', href: '/admin/translations' },
        ],
    },
});

const TYPES = ['category', 'product', 'store', 'tag'] as const;
const STATUS_FILTERS = [
    'all',
    'untranslated',
    'incomplete',
    'complete',
    'published',
] as const;

const searchValue = ref(props.filters.search ?? '');
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

/** The filter panel starts open when a filter is already applied. */
const showFilters = ref(
    (props.filters.search ?? '') !== '' ||
        props.filters.type !== '' ||
        props.filters.locale !== '',
);

const currentUrl = computed(() => new URL(window.location.href));

function applyFilters(updates: Record<string, string | number | null>): void {
    const url = currentUrl.value;

    for (const [key, value] of Object.entries(updates)) {
        if (
            value === null ||
            value === '' ||
            (value === 'all' && key === 'status')
        ) {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, String(value));
        }
    }

    if (!Object.hasOwn(updates, 'page')) {
        url.searchParams.delete('page');
    }

    router.get(
        url.pathname + url.search,
        {},
        { preserveState: true, replace: true },
    );
}

function onSearchInput(value: string): void {
    searchValue.value = value;

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => applyFilters({ search: value }), 300);
}

function setFilter(key: 'type' | 'locale' | 'status', value: string): void {
    applyFilters({ [key]: value });
}

// ===== Free locale (any BCP 47 language tag) =====

const freeLocale = ref('');

const isValidFreeLocale = computed(() => {
    const value = freeLocale.value.trim();

    return (
        /^[a-z]{2,8}(-[A-Z][a-z]{3})?(-[A-Z]{2})?$/.test(value) &&
        value !== props.sourceLocale
    );
});

function applyFreeLocale(): void {
    if (!isValidFreeLocale.value) {
        return;
    }

    applyFilters({ locale: freeLocale.value.trim() });
    freeLocale.value = '';
}

// ===== Inline translation editing =====

const editingValues = ref<Record<string, string>>({});

function cellValue(entity: TranslatableEntity): string {
    const key = `${entity.id}:${props.filters.locale}`;

    return (
        editingValues.value[key] ??
        entity.translations[props.filters.locale]?.value ??
        ''
    );
}

function isDirty(entity: TranslatableEntity): boolean {
    const original = entity.translations[props.filters.locale]?.value ?? '';

    return cellValue(entity) !== original;
}

function saveTranslation(entity: TranslatableEntity): void {
    const existing = entity.translations[props.filters.locale];
    const value = cellValue(entity);

    if (existing) {
        const form = useForm({ value });
        form.put(updateTranslation.url({ translation: existing.id }));
    } else {
        const form = useForm({
            translatable_type: entity.type,
            translatable_id: entity.id,
            locale: props.filters.locale,
            field: 'name',
            value,
        });
        form.post(storeTranslation.url());
    }
}

// ===== AI generation =====

const generating = ref(false);

function generateWithAi(): void {
    generating.value = true;

    router.post(
        generateTranslations.url(),
        {
            translatable_type: props.filters.type,
            locale: props.filters.locale,
            ids: null,
        },
        {
            onFinish: () => {
                generating.value = false;
                router.reload();
            },
        },
    );
}

// ===== Publish / unpublish =====

function togglePublish(entity: TranslatableEntity, publish: boolean): void {
    const form = useForm({
        translatable_type: entity.type,
        translatable_id: entity.id,
        locale: props.filters.locale,
        publish,
    });
    form.post(publishTranslation.url());
}

const selectedIds = ref<string[]>([]);
const selectableEntities = computed(() =>
    props.entities.data.filter(
        (entity) => entity.is_complete && entity.status !== 'published',
    ),
);
const selectionState = computed<boolean | 'indeterminate'>(() => {
    if (selectedIds.value.length === 0) {
        return false;
    }

    return selectedIds.value.length === selectableEntities.value.length
        ? true
        : 'indeterminate';
});

watch(
    () => [
        props.filters.type,
        props.filters.locale,
        props.filters.status,
        props.filters.search,
        props.entities.current_page,
        props.entities.data
            .map(
                (entity) =>
                    `${entity.id}:${entity.status}:${entity.is_complete}`,
            )
            .join('|'),
    ],
    () => {
        selectedIds.value = [];
    },
);

function toggleSelectPage(checked: boolean): void {
    selectedIds.value = checked
        ? selectableEntities.value.map((entity) => entity.id)
        : [];
}

function toggleSelected(id: string, checked: boolean): void {
    selectedIds.value = checked
        ? [...selectedIds.value, id]
        : selectedIds.value.filter((selectedId) => selectedId !== id);
}

const showBulkDialog = ref(false);
const bulkMode = ref<'selected' | 'filtered'>('selected');
const bulkPublishing = ref(false);
const bulkError = ref<string | null>(null);

function openBulkDialog(mode: 'selected' | 'filtered'): void {
    bulkMode.value = mode;
    bulkError.value = null;
    showBulkDialog.value = true;
}

function confirmBulkPublish(): void {
    if (bulkPublishing.value) {
        return;
    }

    bulkPublishing.value = true;

    router.post(
        publishTranslationsBulk.url(),
        {
            mode: bulkMode.value,
            translatable_type: props.filters.type,
            locale: props.filters.locale,
            ...(bulkMode.value === 'selected'
                ? { ids: selectedIds.value }
                : {
                      status: props.filters.status,
                      search: props.filters.search,
                  }),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                showBulkDialog.value = false;
            },
            onError: (errors) => {
                bulkError.value = Object.values(errors)[0] ?? null;
            },
            onFinish: () => {
                bulkPublishing.value = false;
            },
        },
    );
}

// ===== Delete translation =====

const showDeleteDialog = ref(false);
const entityToDelete = ref<TranslatableEntity | null>(null);

function openDeleteDialog(entity: TranslatableEntity): void {
    entityToDelete.value = entity;
    showDeleteDialog.value = true;
}

function confirmDelete(): void {
    const existing = entityToDelete.value?.translations[props.filters.locale];

    if (existing) {
        router.delete(destroyTranslation.url({ translation: existing.id }));
    }

    showDeleteDialog.value = false;
    entityToDelete.value = null;
}

const statusBadgeClasses: Record<string, string> = {
    incomplete:
        'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
    complete: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    published:
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
};

const statusBadgeKey: Record<string, string> = {
    incomplete: 'admin.translations.statuses.incomplete',
    complete: 'admin.translations.statuses.complete',
    published: 'admin.translations.statuses.published',
};

const typeLabelKey: Record<string, string> = {
    category: 'admin.translations.types.category',
    product: 'admin.translations.types.product',
    store: 'admin.translations.types.store',
    tag: 'admin.translations.types.tag',
};

const statusFilterKey: Record<string, string> = {
    all: 'admin.translations.statusFilters.all',
    untranslated: 'admin.translations.statusFilters.untranslated',
    incomplete: 'admin.translations.statusFilters.incomplete',
    complete: 'admin.translations.statusFilters.complete',
    published: 'admin.translations.statusFilters.published',
};
</script>

<template>
    <Head :title="t('admin.translations.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('admin.translations.title')"
            :description="t('admin.translations.description')"
        />

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.translations.counts.incomplete') }}
                </p>
                <p class="text-2xl font-bold">{{ props.counts.incomplete }}</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.translations.counts.complete') }}
                </p>
                <p class="text-2xl font-bold">{{ props.counts.complete }}</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.translations.counts.published') }}
                </p>
                <p class="text-2xl font-bold">{{ props.counts.published }}</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.translations.counts.untranslated') }}
                </p>
                <p class="text-2xl font-bold">
                    {{ props.counts.untranslated }}
                </p>
            </div>
        </div>

        <TooltipProvider>
            <div class="flex justify-end">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon-sm"
                            :aria-label="t('common.actions.filters')"
                            :aria-expanded="showFilters"
                            :aria-controls="
                                showFilters ? 'translation-filters' : undefined
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
            </div>

            <div
                v-if="showFilters"
                id="translation-filters"
                class="flex flex-wrap items-center gap-3 rounded-xl border bg-card px-6 py-3"
            >
                <SearchInput
                    :model-value="searchValue"
                    input-class="w-72"
                    :placeholder="t('admin.translations.searchPlaceholder')"
                    @update:model-value="(v: string) => onSearchInput(v)"
                />

                <Select
                    :model-value="props.filters.type"
                    @update:model-value="
                        (v: AcceptableValue) => setFilter('type', String(v))
                    "
                >
                    <SelectTrigger class="h-9 w-[180px]">
                        <SelectValue
                            :placeholder="t('admin.translations.type')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="type in TYPES"
                            :key="type"
                            :value="type"
                        >
                            {{ t(typeLabelKey[type]) }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select
                    :model-value="props.filters.locale"
                    @update:model-value="
                        (v: AcceptableValue) => setFilter('locale', String(v))
                    "
                >
                    <SelectTrigger class="h-9 w-[220px]">
                        <SelectValue
                            :placeholder="t('admin.translations.localeTarget')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="locale in props.supportedLocales"
                            :key="locale.code"
                            :value="locale.code"
                            :disabled="locale.code === props.sourceLocale"
                        >
                            {{ locale.label }}
                            <span
                                v-if="locale.code === props.sourceLocale"
                                class="ml-1 text-xs text-muted-foreground"
                            >
                                ({{ t('admin.translations.source') }})
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>

                <!-- Free locale: add any BCP 47 language (e.g. pt-BR, de-DE) -->
                <div class="flex items-center gap-1">
                    <Input
                        v-model="freeLocale"
                        :placeholder="
                            t('admin.translations.freeLocalePlaceholder')
                        "
                        class="h-9 w-[110px] font-mono text-xs"
                        @keydown.enter="applyFreeLocale"
                    />
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                :aria-disabled="!isValidFreeLocale"
                                class="aria-disabled:cursor-not-allowed aria-disabled:opacity-50"
                                :aria-label="
                                    t('admin.translations.applyLocale')
                                "
                                @click="isValidFreeLocale && applyFreeLocale()"
                            >
                                <Check aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('admin.translations.applyLocale')
                        }}</TooltipContent>
                    </Tooltip>
                </div>

                <Select
                    :model-value="props.filters.status"
                    @update:model-value="
                        (v: AcceptableValue) => setFilter('status', String(v))
                    "
                >
                    <SelectTrigger class="h-9 w-[180px]">
                        <SelectValue
                            :placeholder="t('admin.translations.statusFilter')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="status in STATUS_FILTERS"
                            :key="status"
                            :value="status"
                        >
                            {{ t(statusFilterKey[status]) }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            v-can="'admin.translations.generate'"
                            size="icon-sm"
                            :disabled="generating"
                            :aria-label="
                                generating
                                    ? t('admin.translations.generating')
                                    : t('admin.translations.generate')
                            "
                            @click="generateWithAi"
                        >
                            <Loader2
                                v-if="generating"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            <Sparkles v-else aria-hidden="true" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{
                        generating
                            ? t('admin.translations.generating')
                            : t('admin.translations.generate')
                    }}</TooltipContent>
                </Tooltip>
            </div>
        </TooltipProvider>

        <div
            v-can="'admin.translations.publish'"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <span
                class="text-sm text-muted-foreground"
                role="status"
                aria-live="polite"
            >
                {{
                    t('admin.translations.bulk.selectedCount', {
                        count: selectedIds.length,
                    })
                }}
            </span>
            <div
                class="flex items-center gap-2"
                role="group"
                :aria-label="t('admin.translations.bulk.actions')"
            >
                <IconAction
                    :label="
                        t('admin.translations.bulk.publishSelected', {
                            count: selectedIds.length,
                        })
                    "
                    variant="outline"
                    :disabled="selectedIds.length === 0 || bulkPublishing"
                    @click="openBulkDialog('selected')"
                >
                    <CheckCheck aria-hidden="true" />
                </IconAction>
                <IconAction
                    :label="
                        t('admin.translations.bulk.publishFiltered', {
                            count: props.entities.total,
                        })
                    "
                    variant="default"
                    :disabled="props.entities.total === 0 || bulkPublishing"
                    @click="openBulkDialog('filtered')"
                >
                    <Send aria-hidden="true" />
                </IconAction>
            </div>
        </div>

        <TooltipProvider>
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-10 px-4 py-3">
                                <Checkbox
                                    v-can="'admin.translations.publish'"
                                    :model-value="selectionState"
                                    :disabled="selectableEntities.length === 0"
                                    :aria-label="
                                        t('admin.translations.bulk.selectPage')
                                    "
                                    @update:model-value="
                                        toggleSelectPage($event === true)
                                    "
                                />
                            </TableHead>
                            <TableHead class="px-4 py-3">{{
                                t('admin.translations.source')
                            }}</TableHead>
                            <TableHead class="px-4 py-3">{{
                                t('admin.translations.translation')
                            }}</TableHead>
                            <TableHead class="px-4 py-3">{{
                                t('admin.translations.statusLabel')
                            }}</TableHead>
                            <TableHead class="px-4 py-3">{{
                                t('admin.translations.coverage')
                            }}</TableHead>
                            <TableHead class="px-4 py-3 text-right">{{
                                t('admin.translations.actionsLabel')
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-if="props.entities.data.length">
                            <TableRow
                                v-for="entity in props.entities.data"
                                :key="entity.id"
                            >
                                <TableCell class="px-4 py-3">
                                    <Checkbox
                                        v-can="'admin.translations.publish'"
                                        :model-value="
                                            selectedIds.includes(entity.id)
                                        "
                                        :disabled="
                                            !entity.is_complete ||
                                            entity.status === 'published'
                                        "
                                        :aria-label="
                                            t(
                                                'admin.translations.bulk.selectRow',
                                                {
                                                    name:
                                                        entity.name ??
                                                        entity.id,
                                                },
                                            )
                                        "
                                        @update:model-value="
                                            toggleSelected(
                                                entity.id,
                                                $event === true,
                                            )
                                        "
                                    />
                                </TableCell>
                                <TableCell class="px-4 py-3 font-medium">
                                    {{ entity.name ?? '—' }}
                                </TableCell>
                                <TableCell class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Input
                                            :model-value="cellValue(entity)"
                                            class="max-w-xs"
                                            :placeholder="
                                                t(
                                                    'admin.translations.translationPlaceholder',
                                                )
                                            "
                                            @update:model-value="
                                                (v: string | number) => {
                                                    const key = `${entity.id}:${props.filters.locale}`;
                                                    editingValues[key] =
                                                        String(v);
                                                }
                                            "
                                        />
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    v-can="
                                                        'admin.translations.update'
                                                    "
                                                    size="icon-sm"
                                                    variant="outline"
                                                    :disabled="!isDirty(entity)"
                                                    :aria-label="`${t('admin.translations.actions.save')}: ${entity.name ?? entity.id}`"
                                                    @click="
                                                        saveTranslation(entity)
                                                    "
                                                >
                                                    <Save aria-hidden="true" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{
                                                t(
                                                    'admin.translations.actions.save',
                                                )
                                            }}</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TableCell>
                                <TableCell class="px-4 py-3">
                                    <Badge
                                        v-if="entity.status"
                                        :class="
                                            statusBadgeClasses[entity.status]
                                        "
                                    >
                                        {{ t(statusBadgeKey[entity.status]) }}
                                    </Badge>
                                    <span
                                        v-else
                                        class="text-sm text-muted-foreground"
                                        >—</span
                                    >
                                </TableCell>
                                <TableCell class="px-4 py-3">
                                    {{ entity.coverage.completed }}/{{
                                        entity.coverage.total
                                    }}
                                </TableCell>
                                <TableCell class="px-4 py-3">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Tooltip
                                            v-if="entity.status !== 'published'"
                                        >
                                            <TooltipTrigger as-child>
                                                <Button
                                                    v-can="
                                                        'admin.translations.publish'
                                                    "
                                                    size="icon-sm"
                                                    variant="outline"
                                                    :aria-disabled="
                                                        !entity.is_complete
                                                    "
                                                    class="aria-disabled:cursor-not-allowed aria-disabled:opacity-50"
                                                    :aria-label="
                                                        entity.is_complete
                                                            ? `${t('admin.translations.actions.publish')}: ${entity.name ?? entity.id}`
                                                            : `${t('admin.translations.actions.publish')}: ${t('admin.translations.publishRequiresComplete')}`
                                                    "
                                                    @click="
                                                        entity.is_complete &&
                                                        togglePublish(
                                                            entity,
                                                            true,
                                                        )
                                                    "
                                                >
                                                    <Send aria-hidden="true" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{
                                                entity.is_complete
                                                    ? t(
                                                          'admin.translations.actions.publish',
                                                      )
                                                    : t(
                                                          'admin.translations.publishRequiresComplete',
                                                      )
                                            }}</TooltipContent>
                                        </Tooltip>
                                        <Tooltip v-else>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    v-can="
                                                        'admin.translations.publish'
                                                    "
                                                    size="icon-sm"
                                                    variant="outline"
                                                    :aria-label="`${t('admin.translations.actions.unpublish')}: ${entity.name ?? entity.id}`"
                                                    @click="
                                                        togglePublish(
                                                            entity,
                                                            false,
                                                        )
                                                    "
                                                >
                                                    <Undo2 aria-hidden="true" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{
                                                t(
                                                    'admin.translations.actions.unpublish',
                                                )
                                            }}</TooltipContent>
                                        </Tooltip>
                                        <Tooltip
                                            v-if="
                                                entity.translations[
                                                    props.filters.locale
                                                ]
                                            "
                                        >
                                            <TooltipTrigger as-child>
                                                <Button
                                                    v-can="
                                                        'admin.translations.destroy'
                                                    "
                                                    size="icon-sm"
                                                    variant="outline"
                                                    class="text-destructive hover:text-destructive"
                                                    :aria-label="`${t('admin.translations.actions.delete')}: ${entity.name ?? entity.id}`"
                                                    @click="
                                                        openDeleteDialog(entity)
                                                    "
                                                >
                                                    <Trash2
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{
                                                t(
                                                    'admin.translations.actions.delete',
                                                )
                                            }}</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </template>
                        <template v-else>
                            <TableRow>
                                <TableCell
                                    :colspan="6"
                                    class="h-24 text-center"
                                >
                                    {{ t('admin.translations.empty') }}
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
            </div>
        </TooltipProvider>

        <div class="flex items-center justify-between px-2">
            <div class="text-sm text-muted-foreground">
                {{
                    t('common.pagination.showing', {
                        from: props.entities.from,
                        to: props.entities.to,
                        total: props.entities.total,
                    })
                }}
            </div>
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="props.entities.current_page <= 1"
                    @click="
                        applyFilters({ page: props.entities.current_page - 1 })
                    "
                >
                    {{ t('common.pagination.previous') }}
                </Button>
                <span class="text-sm font-medium">
                    {{
                        t('common.pagination.page', {
                            current: props.entities.current_page,
                            last: props.entities.last_page,
                        })
                    }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="
                        props.entities.current_page >= props.entities.last_page
                    "
                    @click="
                        applyFilters({ page: props.entities.current_page + 1 })
                    "
                >
                    {{ t('common.pagination.next') }}
                </Button>
            </div>
        </div>
    </div>

    <AlertDialog v-model:open="showDeleteDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('admin.translations.deleteTitle')
                }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{
                        t('admin.translations.deleteDescription', {
                            name: entityToDelete?.translations[
                                props.filters.locale
                            ]?.value,
                        })
                    }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <p v-if="bulkError" class="text-sm text-destructive" role="alert">
                {{ bulkError }}
            </p>
            <AlertDialogFooter>
                <AlertDialogCancel>{{
                    t('common.actions.cancel')
                }}</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete">
                    {{ t('common.actions.delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-model:open="showBulkDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('admin.translations.bulk.confirmTitle')
                }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{
                        bulkMode === 'selected'
                            ? t('admin.translations.bulk.confirmSelected', {
                                  count: selectedIds.length,
                              })
                            : t('admin.translations.bulk.confirmFiltered', {
                                  count: props.entities.total,
                              })
                    }}
                    {{ t('admin.translations.bulk.confirmNote') }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="bulkPublishing">{{
                    t('common.actions.cancel')
                }}</AlertDialogCancel>
                <Button :disabled="bulkPublishing" @click="confirmBulkPublish">
                    <Loader2
                        v-if="bulkPublishing"
                        class="animate-spin"
                        aria-hidden="true"
                    />
                    {{ t('admin.translations.bulk.confirm') }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
