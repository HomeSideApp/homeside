<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatAccountAmount } from '@/components/dashboard/chartUtils';
import TransactionTable from '@/components/economy/TransactionTable.vue';
import type {
    EconomicAccount,
    EconomyTransaction,
} from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import { edit as accountsEdit, index as accountsIndex } from '@/routes/economy/me/accounts';
import type { PaginationMeta } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    account: EconomicAccount;
    transactions: {
        data: EconomyTransaction[];
        meta?: PaginationMeta;
    };
    filters: { perPage?: number };
}>();

const methodNames = computed(() =>
    (props.account.payment_methods ?? []).map((method) => method.name),
);

const pagination: PaginationMeta = props.transactions.meta ?? {
    current_page: 1,
    last_page: 1,
    per_page: props.filters.perPage ?? 15,
    total: props.transactions.data.length,
    from: 1,
    to: props.transactions.data.length,
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'economy.accounts.title', href: accountsIndex.url() },
            { title: props.account.name },
        ],
    });
});
</script>

<template>
    <Head :title="account.name" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="account.name"
                :description="
                    `${methodNames.join(', ') || '—'} · ${account.currency}`
                "
            />
            <TooltipProvider>
                <div class="flex shrink-0 items-center gap-2">
                    <Badge v-if="account.is_archived" variant="outline">
                        {{ t('economy.accounts.archived') }}
                    </Badge>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                v-can="'economy.me.accounts.update'"
                                variant="outline"
                                size="icon-sm"
                                as-child
                            >
                                <Link
                                    :href="accountsEdit.url(account.id)"
                                    :aria-label="t('economy.ui.edit')"
                                >
                                    <Pencil aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.ui.edit')
                        }}</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{
                    t('economy.accounts.currentBalance')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-1">
                <p class="text-3xl font-semibold">
                    {{
                        formatAccountAmount(
                            account.balance_minor ?? 0,
                            account.currency,
                            account.decimal_places,
                        )
                    }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{
                        t('economy.accounts.openingBalance', {
                            amount: formatAccountAmount(
                                account.initial_balance_minor,
                                account.currency,
                                account.decimal_places,
                            ),
                        })
                    }}
                </p>
            </CardContent>
        </Card>

        <TransactionTable
            :transactions="transactions.data"
            :pagination="pagination"
            :filters="filters"
            show-origin
        />
    </div>
</template>
