<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowLeft, ArrowUpDown, ExternalLink } from '@lucide/vue';
import {
    columnVisibilityFeature,
    createColumnHelper,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_text,
    tableFeatures,
} from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { useI18n } from 'vue-i18n';
import IconAction from '@/components/admin/IconAction.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { productIconUrl } from '@/lib/product-icons';
import { products as productsIndex } from '@/routes/admin';
import { show as showPersonalProduct } from '@/routes/admin/personal-products';
import type { Paginated } from '@/types';

interface Product {
    id: string;
    name: string;
    icon: string | null;
    is_personal: boolean;
    created_by: string;
    creator?: { id: string; name: string };
    category?: { id: string; name: string } | null;
}

const { t } = useI18n();

defineProps<{
    products: Paginated<Product>;
    filters: {
        search?: string;
        perPage?: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Productos', href: '/admin/products' },
            { title: 'Personales' },
        ],
    },
});

// eslint-disable-next-line @typescript-eslint/no-unused-vars
const features = tableFeatures({
    columnVisibilityFeature,
    rowSortingFeature,
    sortFns: { alphanumeric: sortFn_alphanumeric, text: sortFn_text },
});

const columnHelper = createColumnHelper<typeof features, Product>();

const columns: ColumnDef<typeof features, Product, any>[] = [
    columnHelper.accessor('name', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    t('admin.personalProducts.name'),
                    h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
                ],
            ),
        cell: ({ row }) => {
            const product = row.original;

            return h('div', { class: 'flex items-center gap-3' }, [
                productIconUrl(product.icon)
                    ? h('img', {
                          src: productIconUrl(product.icon) ?? undefined,
                          alt: product.name,
                          class: 'h-6 w-6 object-contain',
                          onerror: ($event: Event) => {
                              (
                                  $event.target as HTMLImageElement
                              ).style.display = 'none';
                          },
                      })
                    : null,
                h('span', { class: 'font-medium' }, row.getValue('name')),
            ]);
        },
    }),
    columnHelper.accessor('category', {
        header: () => t('admin.personalProducts.category'),
        cell: ({ row }) => {
            const category = row.getValue('category') as {
                id: string;
                name: string;
            } | null;

            if (!category) {
                return h(
                    'div',
                    { class: 'text-sm text-muted-foreground' },
                    '-',
                );
            }

            return h('span', { class: 'text-sm' }, category.name);
        },
    }),
    columnHelper.accessor('creator', {
        header: () => t('admin.personalProducts.user'),
        cell: ({ row }) => {
            const creator = row.getValue('creator') as
                { id: string; name: string } | undefined;

            return h(
                'span',
                { class: 'text-sm text-muted-foreground' },
                creator?.name ?? '-',
            );
        },
    }),
    columnHelper.display({
        id: 'actions',
        enableHiding: false,
        header: () =>
            h('div', { class: 'text-right' }, t('common.actions.title')),
        cell: ({ row }) => {
            const product = row.original;

            return h('div', { class: 'flex items-center justify-end gap-1' }, [
                h(
                    IconAction,
                    {
                        href: showPersonalProduct.url(product.id),
                        label: `${t('admin.personalProducts.promote')}: ${product.name}`,
                    },
                    {
                        default: () =>
                            h(ExternalLink, { 'aria-hidden': 'true' }),
                    },
                ),
            ]);
        },
    }),
];
</script>

<template>
    <Head :title="t('admin.personalProducts.indexTitle')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <IconAction
                    :href="productsIndex.url()"
                    :label="t('common.actions.back')"
                >
                    <ArrowLeft aria-hidden="true" />
                </IconAction>
                <h1 class="text-2xl font-bold">
                    {{ t('admin.personalProducts.title') }}
                </h1>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :data="products.data"
            :pagination="{
                current_page: products.current_page,
                last_page: products.last_page,
                per_page: products.per_page,
                total: products.total,
                from: products.from,
                to: products.to,
            }"
            :filters="filters"
            :search-placeholder="t('admin.personalProducts.searchPlaceholder')"
        />
    </div>
</template>
