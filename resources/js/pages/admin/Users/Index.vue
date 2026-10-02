<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Plus, Trash2, UserCheck } from '@lucide/vue';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { users as usersIndex } from '@/routes/admin';
import {
    create as createUser,
    edit as editUser,
    approve as approveUser,
    reject as rejectUser,
} from '@/routes/admin/users';
import type { Paginated } from '@/types';

interface User {
    id: string;
    name: string;
    email: string;
    roles: Array<{ name: string }>;
    created_at: string;
    approval_status: string;
    external_providers: string[];
    google_connected: boolean;
}

const { t } = useI18n();

defineProps<{
    roles: Array<{ name: string; slug: string }>;
    pendingCount: number;
    users: Paginated<User>;
    filters: {
        search?: string;
        perPage?: number;
        approval?: string;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Usuarios', href: '/admin/users' }] },
});

const columnHelper = createColumnHelper<any, User>();

const columns: ColumnDef<any, User, any>[] = [
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
    columnHelper.accessor('email', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Email', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            ),
        cell: ({ row }) =>
            h('div', { class: 'lowercase' }, row.getValue('email')),
    }),
    columnHelper.accessor('external_providers', {
        header: 'Proveedor',
        cell: ({ row }) => {
            const providers = row.original.external_providers;

            return h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                providers.length
                    ? providers.map((provider) =>
                          h(
                              Badge,
                              { variant: 'secondary', class: 'text-xs' },
                              () =>
                                  provider === 'google' ? 'Google' : provider,
                          ),
                      )
                    : h(
                          Badge,
                          { variant: 'outline', class: 'text-xs' },
                          () => 'Local',
                      ),
            );
        },
    }),
    columnHelper.accessor('roles', {
        header: 'Roles',
        cell: ({ row }) => {
            const roles = row.getValue('roles') as Array<{ name: string }>;

            return h(
                'div',
                { class: 'flex flex-wrap gap-1' },
                roles.length
                    ? roles.map((role) =>
                          h(
                              Badge,
                              { variant: 'secondary', class: 'text-xs' },
                              () => role.name,
                          ),
                      )
                    : h(
                          Badge,
                          { variant: 'outline', class: 'text-xs' },
                          () => 'Sin rol',
                      ),
            );
        },
    }),
    columnHelper.accessor('approval_status', {
        header: 'Estado',
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant:
                        row.original.approval_status === 'approved'
                            ? 'secondary'
                            : 'outline',
                },
                () =>
                    row.original.approval_status === 'pending'
                        ? 'Pendiente'
                        : row.original.approval_status === 'rejected'
                          ? 'Rechazado'
                          : 'Aprobado',
            ),
    }),
    columnHelper.accessor('created_at', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Creado', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            ),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                new Date(row.getValue('created_at')).toLocaleDateString(
                    'es-ES',
                ),
            ),
    }),
    columnHelper.display({
        id: 'actions',
        enableHiding: false,
        header: () => h('div', { class: 'text-right' }, 'Acciones'),
        cell: ({ row }) => {
            const user = row.original;

            return h('div', { class: 'flex items-center justify-end gap-1' }, [
                ...(user.google_connected && user.approval_status !== 'approved'
                    ? [
                          h(
                              IconAction,
                              {
                                  label: 'Revisar solicitud: ' + user.name,
                                  variant: 'default',
                                  onClick: () => openReviewDialog(user),
                              },
                              {
                                  default: () =>
                                      h(UserCheck, {
                                          'aria-hidden': 'true',
                                      }),
                              },
                          ),
                      ]
                    : []),
                h(
                    IconAction,
                    {
                        href: editUser.url(String(user.id)),
                        label: `${t('common.actions.edit')}: ${user.name}`,
                    },
                    { default: () => h(Pencil, { 'aria-hidden': 'true' }) },
                ),
                h(
                    IconAction,
                    {
                        label: `${t('common.actions.delete')}: ${user.name}`,
                        variant: 'destructive',
                        onClick: () => openDeleteDialog(user),
                    },
                    { default: () => h(Trash2, { 'aria-hidden': 'true' }) },
                ),
            ]);
        },
    }),
];

const pendingApproval = ref<User | null>(null);
const selectedRole = ref('');
const approvalProcessing = ref(false);
const rejectionProcessing = ref(false);

function openReviewDialog(user: User) {
    pendingApproval.value = user;
    selectedRole.value = '';
}

function formatRequestDate(date: string): string {
    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'long',
    }).format(new Date(date));
}

function closeReviewDialog() {
    if (approvalProcessing.value || rejectionProcessing.value) {
        return;
    }

    pendingApproval.value = null;
    selectedRole.value = '';
}

function confirmApproval() {
    if (!pendingApproval.value || !selectedRole.value) {
        return;
    }

    approvalProcessing.value = true;
    router.post(
        approveUser(pendingApproval.value.id).url,
        { role: selectedRole.value },
        {
            onSuccess: () => {
                pendingApproval.value = null;
                selectedRole.value = '';
            },
            onFinish: () => {
                approvalProcessing.value = false;
            },
        },
    );
}
function confirmRejection() {
    if (!pendingApproval.value) {
        return;
    }

    rejectionProcessing.value = true;
    router.post(
        rejectUser(pendingApproval.value.id).url,
        {},
        {
            onSuccess: () => {
                pendingApproval.value = null;
                selectedRole.value = '';
            },
            onFinish: () => {
                rejectionProcessing.value = false;
            },
        },
    );
}

function userRowClass(user: User): string {
    return user.approval_status === 'pending'
        ? 'bg-primary/5 hover:bg-primary/10'
        : '';
}

const showDeleteDialog = ref(false);
const userToDelete = ref<User | null>(null);

function openDeleteDialog(user: User) {
    userToDelete.value = user;
    showDeleteDialog.value = true;
}

function confirmDelete() {
    if (userToDelete.value) {
        router.delete(`/admin/users/${userToDelete.value.id}`);
    }

    showDeleteDialog.value = false;
    userToDelete.value = null;
}
</script>

<template>
    <Head :title="t('admin.users.indexTitle')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Usuarios</h1>
            <IconAction
                :href="createUser.url()"
                :label="t('admin.users.create')"
                permission="admin.users"
                variant="default"
            >
                <Plus aria-hidden="true" />
            </IconAction>
        </div>

        <div class="flex gap-2">
            <Button variant="outline" as-child
                ><Link :href="usersIndex().url">Todos</Link></Button
            >
            <Button variant="outline" as-child
                ><Link
                    :href="usersIndex({ query: { approval: 'pending' } }).url"
                    >Pendientes ({{ pendingCount }})</Link
                ></Button
            >
        </div>

        <DataTable
            :columns="columns"
            :data="users.data"
            :pagination="{
                current_page: users.current_page,
                last_page: users.last_page,
                per_page: users.per_page,
                total: users.total,
                from: users.from,
                to: users.to,
            }"
            :filters="filters"
            :row-class="userRowClass"
            search-placeholder="Buscar usuario..."
        />
    </div>

    <Dialog
        :open="pendingApproval !== null"
        @update:open="
            (open) => {
                if (!open) closeReviewDialog();
            }
        "
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Revisar solicitud de acceso</DialogTitle>
                <DialogDescription>
                    Comprueba los datos y decide el acceso de esta cuenta.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="pendingApproval"
                class="grid gap-3 rounded-lg border bg-muted/30 p-4 text-sm"
            >
                <div class="grid gap-1">
                    <span class="text-xs text-muted-foreground">Usuario</span>
                    <span class="font-medium">{{ pendingApproval.name }}</span>
                    <span class="text-muted-foreground">{{
                        pendingApproval.email
                    }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge variant="secondary">Google</Badge>
                    <Badge variant="outline">
                        {{
                            pendingApproval.approval_status === 'rejected'
                                ? 'Rechazado'
                                : 'Pendiente'
                        }}
                    </Badge>
                </div>
                <div class="grid gap-1">
                    <span class="text-xs text-muted-foreground">
                        Solicitud recibida
                    </span>
                    <span>{{
                        formatRequestDate(pendingApproval.created_at)
                    }}</span>
                </div>
            </div>

            <div class="grid gap-2">
                <span class="text-sm font-medium">Rol de acceso</span>
                <Select v-model="selectedRole">
                    <SelectTrigger>
                        <SelectValue placeholder="Seleccionar rol" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem
                                v-for="role in roles"
                                :key="role.slug"
                                :value="role.slug"
                            >
                                {{ role.name }}
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    Es obligatorio asignar un rol para aprobar el acceso.
                </p>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <Button
                    variant="outline"
                    :disabled="approvalProcessing || rejectionProcessing"
                    @click="closeReviewDialog"
                >
                    Cancelar
                </Button>
                <div class="flex flex-col-reverse gap-2 sm:flex-row">
                    <Button
                        v-if="pendingApproval?.approval_status === 'pending'"
                        variant="outline"
                        class="border-destructive/50 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        :disabled="approvalProcessing || rejectionProcessing"
                        @click="confirmRejection"
                    >
                        {{
                            rejectionProcessing
                                ? 'Rechazando…'
                                : 'Rechazar solicitud'
                        }}
                    </Button>
                    <Button
                        :disabled="
                            !selectedRole ||
                            approvalProcessing ||
                            rejectionProcessing
                        "
                        @click="confirmApproval"
                    >
                        {{
                            approvalProcessing ? 'Aprobando…' : 'Aprobar acceso'
                        }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog v-model:open="showDeleteDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{
                    t('admin.users.deleteTitle')
                }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{
                        t('admin.users.deleteDescription', {
                            name: userToDelete?.name,
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
