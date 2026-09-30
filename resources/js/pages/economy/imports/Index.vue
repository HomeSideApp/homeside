<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { Eye, Search, X } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import { index as privateEconomyIndex } from '@/routes/economy/me';
import {
    index as privateImportsIndex,
    show as privateImportShow,
} from '@/routes/economy/me/imports';
import { index as householdEconomyIndex } from '@/routes/households/economy';
import {
    index as householdImportsIndex,
    show as householdImportShow,
} from '@/routes/households/economy/imports';
import type { PaginationMeta } from '@/types';

interface ImportRow {
    id: string;
    status: string;
    document: { id: string; original_filename: string } | null;
    created_by?: { id: string; name: string };
    error: { code: string; message: string } | null;
    created_at: string | null;
    transaction_id?: string | null;
}

const { t } = useI18n();

const props = defineProps<{
    household: { id: string; name: string } | null;
    imports: { data: ImportRow[]; meta?: PaginationMeta };
    filters: { status?: string; search?: string; created_by?: string };
    pendingCount: number;
}>();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: props.household ? 'economy.title' : 'economy.myEconomy',
                href: props.household
                    ? householdEconomyIndex.url(props.household.id)
                    : privateEconomyIndex.url(),
            },
            { title: 'economy.import.index.title' },
        ],
    });
});

const indexUrl = computed(() =>
    props.household
        ? householdImportsIndex.url(props.household.id)
        : privateImportsIndex.url(),
);

const statusFilters = ['active', 'ready', 'failed', 'history'] as const;
const status = ref(props.filters.status ?? 'active');
const search = ref(props.filters.search ?? '');
const filtersOpen = ref(Boolean(props.filters.search));

const hasRunningAnalysis = computed(() =>
    props.imports.data.some((row) =>
        ['pending', 'processing'].includes(row.status),
    ),
);

const { start, stop } = usePoll(
    5000,
    { only: ['imports', 'pendingCount'] },
    { autoStart: false },
);

watchEffect(() => {
    if (hasRunningAnalysis.value) {
        start();

        return;
    }

    stop();
});

function applyFilters(nextStatus?: string): void {
    router.get(
        indexUrl.value,
        {
            status: nextStatus ?? status.value,
            search: search.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function showUrl(row: ImportRow): string {
    return props.household
        ? householdImportShow.url({
              household: props.household.id,
              import: row.id,
          })
        : privateImportShow.url(row.id);
}

function closeFilters(): void {
    filtersOpen.value = false;
    search.value = '';
    applyFilters();
}

function statusLabel(statusValue: string): string {
    return (
        {
            pending: t('economy.ui.statusPending'),
            processing: t('economy.ui.statusProcessing'),
            ready_for_review: t('economy.ui.statusReady'),
            failed: t('economy.ui.statusFailed'),
            confirmed: t('economy.ui.statusConfirmed'),
            discarded: t('economy.ui.statusDiscarded'),
        }[statusValue] ?? t('economy.ui.statusUnknown')
    );
}
</script>

<template>
    <Head :title="t('economy.import.index.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="t('economy.import.index.title')"
                :description="t('economy.import.index.description')"
            />
            <TooltipProvider>
                <div class="flex shrink-0 items-center gap-2">
                    <Badge v-if="pendingCount > 0" variant="secondary">
                        {{ pendingCount }}
                    </Badge>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                :aria-expanded="filtersOpen"
                                aria-controls="imports-filters"
                                :aria-label="
                                    t('economy.import.index.toggleFilters')
                                "
                                @click="filtersOpen = !filtersOpen"
                            >
                                <Search aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.import.index.toggleFilters')
                        }}</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-for="filter in statusFilters"
                :key="filter"
                :variant="status === filter ? 'default' : 'outline'"
                size="sm"
                @click="
                    status = filter;
                    applyFilters(filter);
                "
            >
                {{ t(`economy.import.index.filters.${filter}`) }}
            </Button>
        </div>

        <Card v-if="filtersOpen" id="imports-filters">
            <CardContent class="pt-6">
                <FieldGroup class="grid gap-4 md:grid-cols-2">
                    <Field>
                        <FieldLabel for="imports-search">{{
                            t('economy.import.index.filename')
                        }}</FieldLabel>
                        <Input
                            id="imports-search"
                            v-model="search"
                            @blur="applyFilters()"
                            @keyup.enter="applyFilters()"
                        />
                    </Field>
                </FieldGroup>
                <div class="mt-4 flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="closeFilters"
                    >
                        <X data-icon="inline-start" aria-hidden="true" />
                        {{ t('economy.ui.close') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Empty v-if="imports.data.length === 0">
            <EmptyHeader>
                <EmptyTitle>{{
                    t('economy.import.index.empty')
                }}</EmptyTitle>
            </EmptyHeader>
        </Empty>

        <div v-else class="flex flex-col gap-3">
            <Card v-for="row in imports.data" :key="row.id">
                <CardHeader>
                    <CardTitle
                        class="flex flex-wrap items-center justify-between gap-2 text-base"
                    >
                        <span class="truncate">
                            {{
                                row.document?.original_filename ??
                                t('economy.import.index.unknownDocument')
                            }}
                        </span>
                        <Badge
                            :variant="
                                row.status === 'failed'
                                    ? 'destructive'
                                    : 'secondary'
                            "
                            >{{ statusLabel(row.status) }}</Badge
                        >
                    </CardTitle>
                </CardHeader>
                <CardContent
                    class="flex flex-wrap items-center justify-between gap-3"
                >
                    <p class="text-xs text-muted-foreground">
                        {{
                            row.created_at
                                ? new Date(row.created_at).toLocaleString()
                                : ''
                        }}
                        <span v-if="row.created_by">
                            · {{ row.created_by.name }}
                        </span>
                    </p>
                    <TooltipProvider>
                        <div class="flex items-center gap-1">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="outline"
                                        size="icon-sm"
                                        as-child
                                    >
                                        <Link :href="showUrl(row)">
                                            <Eye aria-hidden="true" />
                                            <span class="sr-only">{{
                                                t(
                                                    'economy.import.index.openReview',
                                                )
                                            }}</span>
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{{
                                    t('economy.import.index.openReview')
                                }}</TooltipContent>
                            </Tooltip>
                        </div>
                    </TooltipProvider>
                </CardContent>
            </Card>
        </div>

    </div>
</template>
