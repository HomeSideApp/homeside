<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { FileUp, Plus } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatMinor } from '@/components/dashboard/chartUtils';
import EconomySummaryCards from '@/components/economy/EconomySummaryCards.vue';
import TransactionFilters from '@/components/economy/TransactionFilters.vue';
import TransactionTable from '@/components/economy/TransactionTable.vue';
import type {
    EconomyMember,
    EconomyTransaction,
} from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import {
    create as economyCreate,
    index as economyIndex,
} from '@/routes/households/economy';
import { create as importsCreate } from '@/routes/households/economy/imports';
import type { PaginationMeta } from '@/types';

const { t } = useI18n();
const props = defineProps<{
    household: { id: string; name: string };
    transactions: {
        data: EconomyTransaction[];
        meta?: PaginationMeta;
    } & Partial<PaginationMeta>;
    stats: {
        period: string | null;
        expenses_minor: number;
        income_minor: number;
        balance_minor: number;
        personal_expenses_minor: number;
        shared_expenses_minor: number;
        by_currency: Record<string, number>;
    };
    members: EconomyMember[];
    filters: Record<string, string | number | undefined>;
}>();

const pagination = computed<PaginationMeta>(
    () =>
        props.transactions.meta ?? {
            current_page: props.transactions.current_page ?? 1,
            last_page: props.transactions.last_page ?? 1,
            per_page: props.transactions.per_page ?? 15,
            total: props.transactions.total ?? props.transactions.data.length,
            from:
                props.transactions.from ??
                (props.transactions.data.length ? 1 : null),
            to: props.transactions.to ?? props.transactions.data.length,
        },
);
const cards = computed(() => [
    {
        label: t('economy.stats.expenses'),
        value: `${formatMinor(props.stats.expenses_minor)} €`,
    },
    {
        label: t('economy.stats.income'),
        value: `${formatMinor(props.stats.income_minor)} €`,
    },
    {
        label: t('economy.stats.balance'),
        value: `${formatMinor(props.stats.balance_minor)} €`,
    },
    {
        label: t('economy.stats.shared'),
        value: `${formatMinor(props.stats.shared_expenses_minor)} €`,
    },
]);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'economy.title',
                href: economyIndex.url(props.household.id),
            },
        ],
    });
});
</script>

<template>
    <Head :title="t('economy.title')" />
    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="t('economy.householdTitle', { name: household.name })"
                :description="t('economy.description')"
            />
            <TooltipProvider>
                <div class="flex shrink-0 flex-nowrap items-center gap-2">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                v-can="'households.economy.create'"
                                variant="outline"
                                size="icon-sm"
                                as-child
                            >
                                <Link
                                    :href="importsCreate.url(household.id)"
                                    :aria-label="t('economy.importDocument')"
                                >
                                    <FileUp aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.importDocument')
                        }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                v-can="'households.economy.store'"
                                size="icon-sm"
                                as-child
                            >
                                <Link
                                    :href="economyCreate.url(household.id)"
                                    :aria-label="t('economy.create')"
                                >
                                    <Plus aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.create')
                        }}</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </div>
        <EconomySummaryCards :cards="cards" />
        <TransactionFilters
            :filters="filters"
            :members="members"
            :route-url="economyIndex.url(household.id)"
        />
        <TransactionTable
            :transactions="transactions.data"
            :pagination="pagination"
            :filters="filters"
            :household-id="household.id"
        />
    </div>
</template>
