<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpDown, Eye, Pencil, Plus, Trash2 } from '@lucide/vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    create as createRole,
    edit as editRole,
    show as showRole,
} from '@/routes/admin/roles';
import type { Paginated } from '@/types';

interface Role {
    id: number;
    name: string;
    users_count: number;
    permissions_count: number;
    is_system: boolean;
}

const { t } = useI18n();

defineProps<{
    roles: Paginated<Role>;
    filters: {
        search?: string;
        perPage?: number;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Roles', href: '/admin/roles' }] },
});

const columnHelper = createColumnHelper<any, Role>();

const columns: ColumnDef<any, Role, any>[] = [
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
        cell: ({ row }) => {
            const role = row.original;

            return h('div', { class: 'flex items-center gap-2 font-medium' }, [
                h('span', {}, row.getValue('name')),
                role.is_system
                    ? h(
                          Badge,
                          { variant: 'secondary', class: 'text-xs' },
                          () => 'Sistema',
                      )
                    : null,
            ]);
        },
    }),
    columnHelper.accessor('users_count', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Usuarios', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            ),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'font-medium' },
                String(row.getValue('users_count')),
            ),
    }),
    columnHelper.accessor('permissions_count', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Permisos', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            ),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'font-medium' },
                String(row.getValue('permissions_count')),
            ),
    }),
    columnHelper.display({
        id: 'actions',
        enableHiding: false,
        header: () => h('div', { class: 'text-right' }, 'Acciones'),
        cell: ({ row }) => {
            const role = row.original;

            return h('div', { class: 'flex items-center justify-end gap-1' }, [
                role.is_system
                    ? h(
                          IconAction,
                          {
                              href: showRole.url(String(role.id)),
                              label: `${t('common.actions.view')}: ${role.name}`,
                          },
                          { default: () => h(Eye, { 'aria-hidden': 'true' }) },
                      )
                    : h(
                          IconAction,
                          {
                              href: editRole.url(String(role.id)),
                              label: `${t('common.actions.edit')}: ${role.name}`,
                          },
                          {
                              default: () =>
                                  h(Pencil, { 'aria-hidden': 'true' }),
                          },
                      ),
                !role.is_system
                    ? h(
                          IconAction,
                          {
                              label: `${t('common.actions.delete')}: ${role.name}`,
                              variant: 'destructive',
                              onClick: () => openDeleteDialog(role),
                          },
                          {
                              default: () =>
                                  h(Trash2, { 'aria-hidden': 'true' }),
                          },
                      )
                    : null,
            ]);
        },
    }),
];

const showDeleteDialog = ref(false);
const roleToDelete = ref<Role | null>(null);

function openDeleteDialog(role: Role) {
    roleToDelete.value = role;
    showDeleteDialog.value = true;
}

function confirmDelete() {
    if (roleToDelete.value) {
        router.delete(`/admin/roles/${roleToDelete.value.id}`);
    }

    showDeleteDialog.value = false;
    roleToDelete.value = null;
}
</script>

<template>
    <Head :title="t('admin.roles.indexTitle')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Roles</h1>
            <IconAction
                :href="createRole.url()"
                :label="t('admin.roles.create')"
                permission="admin.roles"
                variant="default"
            >
                <Plus aria-hidden="true" />
            </IconAction>
        </div>

        <DataTable
            :columns="columns"
            :data="roles.data"
            :pagination="{
                current_page: roles.current_page,
                last_page: roles.last_page,
                per_page: roles.per_page,
                total: roles.total,
                from: roles.from,
                to: roles.to,
            }"
            :filters="filters"
            search-placeholder="Buscar rol..."
        />
    </div>

    <AlertDialog v-model:open="showDeleteDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('admin.roles.deleteTitle')
                }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{
                        t('admin.roles.deleteDescription', {
                            name: roleToDelete?.name,
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
