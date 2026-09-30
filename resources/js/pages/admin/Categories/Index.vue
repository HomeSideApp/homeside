<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Plus, Trash2, Tags } from '@lucide/vue';
import { createColumnHelper } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { h, ref } from 'vue';
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
import { products as productsIndex } from '@/routes/admin';
import {
    create as createCategory,
    edit as editCategory,
} from '@/routes/admin/categories';
import type { Paginated } from '@/types';

interface Category {
    id: string;
    name: string;
    color: string;
    is_active: boolean;
    products_count: number;
}

const { t } = useI18n();

defineProps<{
    categories: Paginated<Category>;
    filters: {
        search?: string;
        perPage?: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categorías', href: '/admin/categories' }],
    },
});

const columnHelper = createColumnHelper<any, Category>();

const columns: ColumnDef<any, Category, any>[] = [
    columnHelper.accessor('name', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Nombre', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            ),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('name')),
    }),
    columnHelper.accessor('color', {
        header: 'Color',
        cell: ({ row }) => {
            const color = row.getValue('color') as string;

            return h('div', { class: 'flex items-center gap-2' }, [
                h('div', {
                    class: 'h-4 w-4 rounded-full border',
                    style: { backgroundColor: color },
                }),
                h('span', { class: 'text-sm text-muted-foreground' }, color),
            ]);
        },
    }),
    columnHelper.accessor('products_count', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Productos', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            ),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-center' },
                String(row.getValue('products_count')),
            ),
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
            const category = row.original;

            return h('div', { class: 'flex items-center justify-end gap-1' }, [
                h(
                    IconAction,
                    {
                        href: productsIndex.url({
                            query: { category: category.id },
                        }),
                        label: t('admin.categories.viewProducts', {
                            name: category.name,
                        }),
                    },
                    { default: () => h(Tags, { 'aria-hidden': 'true' }) },
                ),
                h(
                    IconAction,
                    {
                        href: editCategory.url(category.id),
                        label: `${t('common.actions.edit')}: ${category.name}`,
                    },
                    { default: () => h(Pencil, { 'aria-hidden': 'true' }) },
                ),
                h(
                    IconAction,
                    {
                        label: `${t('common.actions.delete')}: ${category.name}`,
                        variant: 'destructive',
                        onClick: () => openDeleteDialog(category),
                    },
                    { default: () => h(Trash2, { 'aria-hidden': 'true' }) },
                ),
            ]);
        },
    }),
];

const showDeleteDialog = ref(false);
const categoryToDelete = ref<Category | null>(null);

function openDeleteDialog(category: Category) {
    categoryToDelete.value = category;
    showDeleteDialog.value = true;
}

function confirmDelete() {
    if (categoryToDelete.value) {
        router.delete(`/admin/categories/${categoryToDelete.value.id}`);
    }

    showDeleteDialog.value = false;
    categoryToDelete.value = null;
}
</script>

<template>
    <Head :title="t('admin.categories.indexTitle')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">
                {{ t('admin.categories.title') }}
            </h1>
            <IconAction
                :href="createCategory.url()"
                :label="t('admin.categories.create')"
                permission="admin.categories"
                variant="default"
            >
                <Plus aria-hidden="true" />
            </IconAction>
        </div>

        <DataTable
            :columns="columns"
            :data="categories.data"
            :pagination="{
                current_page: categories.current_page,
                last_page: categories.last_page,
                per_page: categories.per_page,
                total: categories.total,
                from: categories.from,
                to: categories.to,
            }"
            :filters="filters"
            :search-placeholder="t('admin.categories.searchPlaceholder')"
        />
    </div>

    <AlertDialog v-model:open="showDeleteDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('admin.categories.deleteTitle')
                }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{
                        t('admin.categories.deleteDescription', {
                            name: categoryToDelete?.name,
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
