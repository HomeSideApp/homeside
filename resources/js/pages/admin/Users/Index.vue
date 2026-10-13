<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    Ban,
    Pause,
    Pencil,
    Play,
    Plus,
    ShieldCheck,
    Trash2,
    UserCheck,
} from '@lucide/vue';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useAuthorization } from '@/composables/useAuthorization';
import { users as usersIndex } from '@/routes/admin';
import {
    create as createUser,
    edit as editUser,
    approve as approveUser,
    reject as rejectUser,
    suspend as suspendUser,
    unsuspend as unsuspendUser,
    block as blockUser,
    unblock as unblockUser,
    deleteMethod as deleteUser,
    deleteAndBlock as deleteAndBlockUser,
} from '@/routes/admin/users';
import type { Paginated } from '@/types';

interface User {
    id: string;
    name: string;
    email: string;
    roles: Array<{ name: string }>;
    created_at: string;
    approval_status: string;
    account_status: string;
    external_providers: string[];
    google_connected: boolean;
}

const { t } = useI18n();
const { can } = useAuthorization();

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
            h('div', { class: 'flex flex-wrap items-center gap-1' }, [
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
                ...(row.original.account_status !== 'active'
                    ? [
                          h(
                              Badge,
                              { variant: 'destructive' },
                              () =>
                                  row.original.account_status === 'suspended'
                                      ? t('admin.users.status.suspended')
                                      : t('admin.users.status.blocked'),
                          ),
                      ]
                    : []),
            ]),
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
            const lifecycle = [];

            // Only the transition the row's own state allows is offered: an
            // active account can be suspended or blocked, a suspended one can
            // be restored or blocked, and a blocked one can only be unblocked.
            if (user.account_status === 'active' && can('admin.users.suspend')) {
                lifecycle.push(
                    h(
                        IconAction,
                        {
                            label: `${t('admin.users.suspend')}: ${user.name}`,
                            onClick: () => confirmLifecycle(suspendUser, user),
                        },
                        { default: () => h(Pause, { 'aria-hidden': 'true' }) },
                    ),
                );
            }

            if (user.account_status === 'suspended' && can('admin.users.suspend')) {
                lifecycle.push(
                    h(
                        IconAction,
                        {
                            label: `${t('admin.users.unsuspend')}: ${user.name}`,
                            onClick: () => confirmLifecycle(unsuspendUser, user),
                        },
                        { default: () => h(Play, { 'aria-hidden': 'true' }) },
                    ),
                );
            }

            if (user.account_status !== 'blocked' && can('admin.users.block')) {
                lifecycle.push(
                    h(
                        IconAction,
                        {
                            label: `${t('admin.users.block')}: ${user.name}`,
                            variant: 'destructive',
                            onClick: () => openBlockDialog(user),
                        },
                        { default: () => h(Ban, { 'aria-hidden': 'true' }) },
                    ),
                );
            }

            if (user.account_status === 'blocked' && can('admin.users.unblock')) {
                lifecycle.push(
                    h(
                        IconAction,
                        {
                            label: `${t('admin.users.unblock')}: ${user.name}`,
                            onClick: () => confirmLifecycle(unblockUser, user),
                        },
                        { default: () => h(ShieldCheck, { 'aria-hidden': 'true' }) },
                    ),
                );
            }

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
                ...lifecycle,
                h(
                    IconAction,
                    {
                        href: editUser.url(String(user.id)),
                        label: `${t('common.actions.edit')}: ${user.name}`,
                    },
                    { default: () => h(Pencil, { 'aria-hidden': 'true' }) },
                ),
                ...(can('admin.users.delete') || can('admin.users.delete-and-block')
                    ? [
                          h(
                              IconAction,
                              {
                                  label: `${t('common.actions.delete')}: ${user.name}`,
                                  variant: 'destructive',
                                  onClick: () => openDeleteDialog(user),
                              },
                              {
                                  default: () =>
                                      h(Trash2, { 'aria-hidden': 'true' }),
                              },
                          ),
                      ]
                    : []),
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

function confirmLifecycle(
    route: (user: { id: string }) => { url: string },
    user: User,
): void {
    router.post(route({ id: String(user.id) }).url, {}, { preserveScroll: true });
}

const showBlockDialog = ref(false);
const userToBlock = ref<User | null>(null);
const blockReason = ref('');

function openBlockDialog(user: User) {
    userToBlock.value = user;
    blockReason.value = '';
    showBlockDialog.value = true;
}

function confirmBlock() {
    if (!userToBlock.value) {
        return;
    }

    router.post(
        blockUser({ id: String(userToBlock.value.id) }).url,
        { reason: blockReason.value || undefined },
        {
            preserveScroll: true,
            onFinish: () => {
                showBlockDialog.value = false;
                userToBlock.value = null;
                blockReason.value = '';
            },
        },
    );
}

const showDeleteDialog = ref(false);
const userToDelete = ref<User | null>(null);
const deleteAlsoBlock = ref(false);
const deleteReason = ref('');

function openDeleteDialog(user: User) {
    userToDelete.value = user;
    deleteAlsoBlock.value = false;
    deleteReason.value = '';
    showDeleteDialog.value = true;
}

function confirmDelete() {
    if (!userToDelete.value) {
        return;
    }

    const user = userToDelete.value;
    const alsoBlock = deleteAlsoBlock.value && can('admin.users.delete-and-block');

    const request = alsoBlock
        ? router.delete(
              deleteAndBlockUser({ id: String(user.id) }).url,
              {
                  data: { reason: deleteReason.value || undefined },
                  preserveScroll: true,
              },
          )
        : router.delete(deleteUser({ id: String(user.id) }).url, {
              preserveScroll: true,
          });

    void request;

    showDeleteDialog.value = false;
    userToDelete.value = null;
    deleteAlsoBlock.value = false;
    deleteReason.value = '';
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

    <Dialog
        :open="showBlockDialog"
        @update:open="
            (open) => {
                if (!open) {
                    showBlockDialog = false;
                    userToBlock = null;
                }
            }
        "
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('admin.users.blockTitle') }}</DialogTitle>
                <DialogDescription>
                    {{
                        t('admin.users.blockDescription', {
                            name: userToBlock?.name,
                        })
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-2">
                <Label for="block-reason">
                    {{ t('admin.users.blockReason') }}
                </Label>
                <Input
                    id="block-reason"
                    v-model="blockReason"
                    maxlength="500"
                />
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showBlockDialog = false">
                    {{ t('common.actions.cancel') }}
                </Button>
                <Button variant="destructive" @click="confirmBlock">
                    {{ t('admin.users.block') }}
                </Button>
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

            <div
                v-if="can('admin.users.delete-and-block')"
                class="grid gap-3 rounded-lg border bg-muted/30 p-4"
            >
                <Label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="deleteAlsoBlock" />
                    {{ t('admin.users.deleteAlsoBlock') }}
                </Label>
                <div v-if="deleteAlsoBlock" class="grid gap-2">
                    <Label for="delete-reason">
                        {{ t('admin.users.blockReason') }}
                    </Label>
                    <Input
                        id="delete-reason"
                        v-model="deleteReason"
                        maxlength="500"
                    />
                </div>
            </div>

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
