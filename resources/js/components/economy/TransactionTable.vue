<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from '@lucide/vue';
import { createColumnHelper } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { h, ref } from 'vue';
import { useI18n } from 'vue-i18n';
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
    destroy as privateDestroy,
    edit as privateEdit,
    index as privateIndex,
    show as privateShow,
} from '@/routes/economy/me';
import {
    destroy as householdDestroy,
    edit as householdEdit,
    index as householdIndex,
    show as householdShow,
} from '@/routes/households/economy';
import type { PaginationMeta } from '@/types';
import type { EconomyTransaction } from './types';

const props = defineProps<{
    transactions: EconomyTransaction[];
    pagination: PaginationMeta;
    filters: Record<string, string | number | undefined>;
    householdId?: string | null;
    showOrigin?: boolean;
}>();
const { t } = useI18n();
const page = usePage();

const selected = ref<EconomyTransaction | null>(null);
const indexUrl = props.householdId
    ? householdIndex.url(props.householdId)
    : privateIndex.url();
const columnHelper = createColumnHelper<any, EconomyTransaction>();
const routeFor = (
    kind: 'show' | 'edit',
    transaction: EconomyTransaction,
): string => {
    const householdId = props.householdId ?? transaction.household_id;

    if (householdId) {
        return (kind === 'show' ? householdShow : householdEdit)({
            household: householdId,
            transaction: transaction.id,
        }).url;
    }

    return (kind === 'show' ? privateShow : privateEdit)(transaction.id).url;
};
const canModify = (transaction: EconomyTransaction): boolean => {
    const permission =
        (props.householdId ?? transaction.household_id)
            ? 'households.economy.edit'
            : 'economy.me.edit';

    return (
        transaction.created_by.id === String(page.props.auth.user.id) &&
        page.props.auth.permissions.includes(permission)
    );
};

const columns: ColumnDef<any, EconomyTransaction, any>[] = [
    columnHelper.accessor('title', {
        header: t('economy.transaction.title'),
        cell: ({ row }) =>
            h('div', { class: 'flex flex-wrap items-center gap-2' }, [
                h('span', { class: 'font-medium' }, row.original.title),
                row.original.recurrence_frequency
                    ? h(Badge, { variant: 'outline' }, () =>
                          t('economy.recurrence.sourceBadge'),
                      )
                    : row.original.recurrence_parent_id
                      ? h(Badge, { variant: 'outline' }, () =>
                            t('economy.recurrence.occurrenceBadge'),
                        )
                      : null,
            ]),
    }),
    columnHelper.accessor('amount', {
        header: t('economy.transaction.amount'),
        cell: ({ row }) =>
            h(
                'span',
                { class: 'font-mono' },
                `${row.original.type === 'expense' ? '−' : '+'}${row.original.amount} ${row.original.currency}`,
            ),
    }),
    columnHelper.accessor('type', {
        header: t('economy.ui.type'),
        cell: ({ row }) =>
            h(
                Badge,
                {
                    variant:
                        row.original.type === 'expense'
                            ? 'destructive'
                            : 'default',
                },
                () =>
                    row.original.type === 'expense'
                        ? t('economy.transaction.typeExpense')
                        : t('economy.transaction.typeIncome'),
            ),
    }),
    columnHelper.accessor('scope', {
        header: t('economy.ui.scope'),
        cell: ({ row }) =>
            h(Badge, { variant: 'secondary' }, () =>
                row.original.scope === 'shared'
                    ? t('economy.transaction.shared')
                    : t('economy.transaction.personal'),
            ),
    }),
    columnHelper.accessor('created_by.name', {
        header: t('economy.ui.person'),
        cell: ({ row }) => row.original.created_by.name,
    }),
    ...(props.showOrigin
        ? [
              columnHelper.accessor('origin', {
                  header: t('economy.ui.origin'),
                  cell: ({ row }) =>
                      row.original.origin ?? t('economy.ui.private'),
              }),
          ]
        : []),
    columnHelper.accessor('occurred_at', {
        header: t('economy.ui.date'),
        cell: ({ row }) =>
            row.original.occurred_at
                ? new Date(row.original.occurred_at).toLocaleDateString()
                : '—',
    }),
    columnHelper.display({
        id: 'actions',
        header: t('economy.ui.actions'),
        cell: ({ row }) => {
            const transaction = row.original;
            const actions = [
                h(Link, { href: routeFor('show', transaction) }, () =>
                    h(Button, { variant: 'ghost', size: 'icon' }, () => [
                        h(Eye),
                        h('span', { class: 'sr-only' }, t('economy.ui.view')),
                    ]),
                ),
            ];

            if (canModify(transaction)) {
                actions.push(
                    h(Link, { href: routeFor('edit', transaction) }, () =>
                        h(Button, { variant: 'ghost', size: 'icon' }, () => [
                            h(Pencil),
                            h(
                                'span',
                                { class: 'sr-only' },
                                t('economy.ui.edit'),
                            ),
                        ]),
                    ),
                    h(
                        Button,
                        {
                            variant: 'ghost',
                            size: 'icon',
                            onClick: () => {
                                selected.value = transaction;
                            },
                        },
                        () => [
                            h(Trash2),
                            h(
                                'span',
                                { class: 'sr-only' },
                                t('economy.ui.delete'),
                            ),
                        ],
                    ),
                );
            }

            return h('div', { class: 'flex justify-end gap-1' }, actions);
        },
    }),
];

function destroyTransaction(): void {
    if (!selected.value) {
        return;
    }

    const householdId = props.householdId ?? selected.value.household_id;
    const url = householdId
        ? householdDestroy({
              household: householdId,
              transaction: selected.value.id,
          }).url
        : privateDestroy(selected.value.id).url;
    router.delete(url, {
        onFinish: () => {
            selected.value = null;
        },
    });
}
</script>

<template>
    <DataTable
        :columns="columns"
        :data="transactions"
        :pagination="pagination"
        :filters="filters"
        :search-placeholder="t('economy.ui.search')"
        :route-url="indexUrl"
    >
        <template #filters><slot name="filters" /></template>
    </DataTable>
    <AlertDialog
        :open="selected !== null"
        @update:open="!$event && (selected = null)"
    >
        <AlertDialogContent
            ><AlertDialogHeader
                ><AlertDialogTitle>{{
                    t('economy.ui.deleteTransactionTitle')
                }}</AlertDialogTitle
                ><AlertDialogDescription>{{
                    t('economy.ui.deleteTransactionDescription', {
                        title: selected?.title,
                    })
                }}</AlertDialogDescription></AlertDialogHeader
            ><AlertDialogFooter
                ><AlertDialogCancel>{{
                    t('economy.ui.cancel')
                }}</AlertDialogCancel
                ><AlertDialogAction @click="destroyTransaction">{{
                    t('economy.ui.delete')
                }}</AlertDialogAction></AlertDialogFooter
            ></AlertDialogContent
        >
    </AlertDialog>
</template>
