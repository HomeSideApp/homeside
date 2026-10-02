<script setup lang="ts" generic="TData extends RowData">
import { router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from '@lucide/vue';
import type { ColumnDef, RowData } from '@tanstack/vue-table';
import {
    columnVisibilityFeature,
    FlexRender,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_text,
    tableFeatures,
    useTable,
} from '@tanstack/vue-table';
import { computed, ref, watch } from 'vue';
import SearchInput from '@/components/SearchInput.vue';
import { Button } from '@/components/ui/button';
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
import type { PaginationMeta } from '@/types';

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

interface Filters {
    search?: string;
    perPage?: number;
}

const props = withDefaults(
    defineProps<{
        columns: ColumnDef<any, TData, any>[];
        data: TData[];
        pagination: PaginationMeta;
        filters: Filters;
        searchPlaceholder?: string;
        routeUrl?: string;
        rowClass?: (row: TData) => string;
    }>(),
    {
        searchPlaceholder: '',
    },
);

const currentUrl = computed(() => new URL(window.location.href));

function visit(url: URL): void {
    router.get(
        props.routeUrl ?? url.pathname,
        Object.fromEntries(url.searchParams.entries()),
        {
            preserveState: true,
            replace: true,
        },
    );
}

const features = tableFeatures({
    columnVisibilityFeature,
    rowSortingFeature,
    sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
});

const sorting = ref<any[]>([]);

const table = useTable({
    features,
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
    },
    onSortingChange: (updater) => {
        sorting.value =
            typeof updater === 'function' ? updater(sorting.value) : updater;
    },
});

const searchValue = ref(props.filters.search ?? '');

const updateSearch = debounce((value: string) => {
    const url = currentUrl.value;

    if (value) {
        url.searchParams.set('search', value);
    } else {
        url.searchParams.delete('search');
    }

    url.searchParams.delete('page');
    visit(url);
}, 300);

watch(searchValue, (val) => {
    updateSearch(val);
});

function goToPage(targetPage: number) {
    const url = currentUrl.value;
    url.searchParams.set('page', String(targetPage));
    visit(url);
}

function updatePerPage(value: unknown) {
    if (typeof value !== 'string' && typeof value !== 'number') {
        return;
    }

    const perPage = String(value);

    if (!perPage) {
        return;
    }

    const url = currentUrl.value;
    url.searchParams.set('perPage', perPage);
    url.searchParams.delete('page');
    visit(url);
}

const pageNumbers = computed(() => {
    const total = props.pagination.last_page;
    const current = props.pagination.current_page;
    const pages: (number | string)[] = [];

    if (total <= 7) {
        for (let i = 1; i <= total; i++) {
            pages.push(i);
        }
    } else {
        pages.push(1);

        if (current > 3) {
            pages.push('...');
        }

        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        if (current < total - 2) {
            pages.push('...');
        }

        pages.push(total);
    }

    return pages;
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-2">
            <SearchInput
                v-model="searchValue"
                class="mr-auto"
                input-class="w-full sm:w-64"
                :placeholder="searchPlaceholder"
            />
            <slot name="filters" />
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <TableHead
                            class="px-6 py-4"
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                        >
                            <FlexRender
                                v-if="!header.isPlaceholder"
                                :render="header.column.columnDef.header"
                                :props="header.getContext()"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                            :class="rowClass?.(row.original)"
                        >
                            <TableCell
                                class="px-6 py-4"
                                v-for="cell in row.getVisibleCells()"
                                :key="cell.id"
                            >
                                <FlexRender
                                    :render="cell.column.columnDef.cell"
                                    :props="cell.getContext()"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow>
                            <TableCell
                                :colspan="columns.length"
                                class="h-24 text-center"
                            >
                                {{ $t('common.states.noResults') }}
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>

        <div class="flex items-center justify-between px-2">
            <div class="text-sm text-muted-foreground">
                {{
                    $t('common.pagination.showing', {
                        from: pagination.from,
                        to: pagination.to,
                        total: pagination.total,
                    })
                }}
            </div>
            <div class="flex items-center gap-6">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-medium">
                        {{ $t('common.pagination.rowsPerPage') }}
                    </p>
                    <Select
                        :model-value="`${pagination.per_page}`"
                        @update:model-value="updatePerPage"
                    >
                        <SelectTrigger class="h-8 w-[70px]">
                            <SelectValue
                                :placeholder="`${pagination.per_page}`"
                            />
                        </SelectTrigger>
                        <SelectContent side="top">
                            <SelectItem
                                v-for="pageSize in [10, 20, 30, 50]"
                                :key="pageSize"
                                :value="`${pageSize}`"
                            >
                                {{ pageSize }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div
                    class="flex w-[100px] items-center justify-center text-sm font-medium"
                >
                    {{
                        $t('common.pagination.page', {
                            current: pagination.current_page,
                            last: pagination.last_page,
                        })
                    }}
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        variant="outline"
                        class="size-8 p-0"
                        :disabled="pagination.current_page <= 1"
                        @click="goToPage(1)"
                    >
                        <span class="sr-only">{{
                            $t('common.pagination.firstPage')
                        }}</span>
                        <ChevronsLeft class="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        class="size-8 p-0"
                        :disabled="pagination.current_page <= 1"
                        @click="goToPage(pagination.current_page - 1)"
                    >
                        <span class="sr-only">{{
                            $t('common.pagination.previousPage')
                        }}</span>
                        <ChevronLeft class="size-4" />
                    </Button>
                    <template
                        v-for="(pageNum, index) in pageNumbers"
                        :key="index"
                    >
                        <span
                            v-if="pageNum === '...'"
                            class="px-2 text-sm text-muted-foreground"
                            >...</span
                        >
                        <Button
                            v-else
                            variant="outline"
                            class="size-8 p-0"
                            :class="{
                                'bg-primary text-primary-foreground hover:bg-primary/90':
                                    pageNum === pagination.current_page,
                            }"
                            @click="goToPage(pageNum as number)"
                        >
                            {{ pageNum }}
                        </Button>
                    </template>
                    <Button
                        variant="outline"
                        class="size-8 p-0"
                        :disabled="
                            pagination.current_page >= pagination.last_page
                        "
                        @click="goToPage(pagination.current_page + 1)"
                    >
                        <span class="sr-only">{{
                            $t('common.pagination.nextPage')
                        }}</span>
                        <ChevronRight class="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        class="size-8 p-0"
                        :disabled="
                            pagination.current_page >= pagination.last_page
                        "
                        @click="goToPage(pagination.last_page)"
                    >
                        <span class="sr-only">{{
                            $t('common.pagination.lastPage')
                        }}</span>
                        <ChevronsRight class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
