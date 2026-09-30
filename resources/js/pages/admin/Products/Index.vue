<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Plus, Trash2 } from '@lucide/vue';
import {
    columnVisibilityFeature,
    createColumnHelper,
    rowSortingFeature,
    sortFn_alphanumeric,
    sortFn_text,
    tableFeatures,
} from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { computed, h, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import IconAction from '@/components/admin/IconAction.vue';
import DataTable from '@/components/DataTable.vue';
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
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { productIconUrl } from '@/lib/product-icons';
import {
    create as createProduct,
    edit as editProduct,
} from '@/routes/admin/products';
import type { Paginated } from '@/types';

interface Product {
    id: string;
    name: string;
    icon: string | null;
    is_active: boolean;
    category?: { id: string; name: string } | null;
}

interface Category {
    id: string;
    name: string;
}

const { t } = useI18n();

const props = defineProps<{
    products: Paginated<Product>;
    categories: Category[];
    filters: {
        search?: string;
        perPage?: number;
        category?: string;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Productos', href: '/admin/products' }] },
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
                    t('admin.products.name'),
                    h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
                ],
            ),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('name')),
    }),
    columnHelper.accessor('category', {
        header: () => t('admin.products.category'),
        cell: ({ row }) => {
            const category = row.getValue('category') as {
                id: string;
                name: string;
                color?: string;
            } | null;

            if (!category) {
                return h(
                    'div',
                    { class: 'text-sm text-muted-foreground' },
                    '-',
                );
            }

            return h(
                'span',
                {
                    class: 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                    style: {
                        backgroundColor: category.color
                            ? `${category.color}20`
                            : undefined,
                        color: category.color ?? undefined,
                    },
                },
                category.name,
            );
        },
    }),
    columnHelper.accessor('icon', {
        header: () => t('admin.products.icon'),
        cell: ({ row }) => {
            const icon = row.getValue('icon') as string | null;

            const url = productIconUrl(icon);

            if (!url) {
                return h(
                    'div',
                    { class: 'text-sm text-muted-foreground' },
                    '-',
                );
            }

            return h('img', {
                src: url,
                alt: icon,
                class: 'h-6 w-6 object-contain',
                onerror: ($event: Event) => {
                    ($event.target as HTMLImageElement).style.display = 'none';
                },
            });
        },
    }),
    columnHelper.accessor('is_active', {
        header: 'Estado',
        cell: ({ row }) => {
            const isActive = row.getValue('is_active') as boolean;

            return h(
                'span',
                {
                    class: isActive
                        ? 'inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-300'
                        : 'inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800 dark:bg-red-900 dark:text-red-300',
                },
                () => (isActive ? 'Activo' : 'Inactivo'),
            );
        },
    }),
    columnHelper.display({
        id: 'actions',
        enableHiding: false,
        header: () => h('div', { class: 'text-right' }, 'Acciones'),
        cell: ({ row }) => {
            const product = row.original;

            return h('div', { class: 'flex items-center justify-end gap-1' }, [
                h(
                    IconAction,
                    {
                        href: editProduct.url(product.id),
                        label: `${t('common.actions.edit')}: ${product.name}`,
                    },
                    { default: () => h(Pencil, { 'aria-hidden': 'true' }) },
                ),
                h(
                    IconAction,
                    {
                        label: `${t('common.actions.delete')}: ${product.name}`,
                        variant: 'destructive',
                        onClick: () => openDeleteDialog(product),
                    },
                    { default: () => h(Trash2, { 'aria-hidden': 'true' }) },
                ),
            ]);
        },
    }),
];

const showDeleteDialog = ref(false);
const productToDelete = ref<Product | null>(null);

function openDeleteDialog(product: Product) {
    productToDelete.value = product;
    showDeleteDialog.value = true;
}

function confirmDelete() {
    if (productToDelete.value) {
        router.delete(`/admin/products/${productToDelete.value.id}`);
    }

    showDeleteDialog.value = false;
    productToDelete.value = null;
}
function updateCategory(value: string) {
    const url = new URL(window.location.href);

    if (value && value !== 'all') {
        url.searchParams.set('category', value);
    } else {
        url.searchParams.delete('category');
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
}

const selectedCategory = computed({
    get: () => props.filters.category ?? 'all',
    set: (value: string) => updateCategory(value),
});
</script>

<template>
    <Head :title="t('admin.products.indexTitle')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ t('admin.products.title') }}</h1>
            <IconAction
                :href="createProduct.url()"
                :label="t('admin.products.newProduct')"
                permission="admin.products"
                variant="default"
            >
                <Plus aria-hidden="true" />
            </IconAction>
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
            :search-placeholder="t('admin.products.searchPlaceholder')"
        >
            <template #filters>
                <Select v-model="selectedCategory">
                    <SelectTrigger class="h-9 w-[180px]">
                        <SelectValue
                            :placeholder="t('admin.products.allCategories')"
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{
                            t('admin.products.allCategories')
                        }}</SelectItem>
                        <SelectItem
                            v-for="cat in categories"
                            :key="cat.id"
                            :value="cat.id"
                        >
                            {{ cat.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </template>
        </DataTable>
    </div>

    <AlertDialog v-model:open="showDeleteDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('admin.products.deleteTitle')
                }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{
                        t('admin.products.deleteDescription', {
                            name: productToDelete?.name,
                        })
                    }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{
                    t('common.actions.cancel')
                }}</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete">{{
                    t('common.actions.delete')
                }}</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
