<script setup lang="ts">
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { FileUp, ListChecks, Plus } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatMinor } from '@/components/dashboard/chartUtils';
import EconomySummaryCards from '@/components/economy/EconomySummaryCards.vue';
import TransactionFilters from '@/components/economy/TransactionFilters.vue';
import TransactionTable from '@/components/economy/TransactionTable.vue';
import type { EconomyTransaction } from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import { create as meCreate, index as meIndex } from '@/routes/economy/me';
import {
    create as meImportsCreate,
    index as meImportsIndex,
} from '@/routes/economy/me/imports';
import type { PaginationMeta } from '@/types';

const { t } = useI18n();
const props = defineProps<{
    transactions: {
        data: EconomyTransaction[];
        meta?: PaginationMeta;
    } & Partial<PaginationMeta>;
    totals: {
        own_total_minor: number;
        shared_participation_minor: number;
        personal_total_minor: number;
        without_house_minor: number;
        by_currency: Record<string, number>;
    };
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
const currencyDetail = computed(() =>
    Object.entries(props.totals.by_currency)
        .map(([currency, minor]) => `${currency} ${formatMinor(minor)}`)
        .join(' · '),
);
const cards = computed(() => [
    {
        label: t('economy.stats.privateAccount'),
        value: `${formatMinor(props.totals.without_house_minor)} €`,
    },
    {
        label: t('economy.stats.ownInHouseholds'),
        value: `${formatMinor(props.totals.own_total_minor - props.totals.without_house_minor)} €`,
    },
    {
        label: t('economy.stats.sharedParticipation'),
        value: `${formatMinor(props.totals.shared_participation_minor)} €`,
    },
    {
        label: t('economy.stats.personalTotal'),
        value: `${formatMinor(props.totals.personal_total_minor)} €`,
        detail: currencyDetail.value,
    },
]);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [{ title: 'economy.myEconomy', href: meIndex.url() }],
    });
});
</script>

<template>
    <Head :title="t('economy.myEconomy')" />
    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="t('economy.myEconomy')"
                :description="t('economy.myEconomyDescription')"
            />
            <TooltipProvider>
                <div class="flex shrink-0 flex-nowrap items-center gap-2">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                v-can="'economy.me.imports.index'"
                                variant="outline"
                                size="icon-sm"
                                as-child
                            >
                                <Link
                                    :href="meImportsIndex.url()"
                                    :aria-label="
                                        t('economy.import.index.title')
                                    "
                                >
                                    <ListChecks aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.import.index.title')
                        }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                v-can="'economy.me.create'"
                                variant="outline"
                                size="icon-sm"
                                as-child
                            >
                                <Link
                                    :href="meImportsCreate.url()"
                                    :aria-label="t('economy.importTicket')"
                                >
                                    <FileUp aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.importTicket')
                        }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                v-can="'economy.me.store'"
                                size="icon-sm"
                                as-child
                            >
                                <Link
                                    :href="meCreate.url()"
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
            :show-scope="false"
            :route-url="meIndex.url()"
        />
        <TransactionTable
            :transactions="transactions.data"
            :pagination="pagination"
            :filters="filters"
            show-origin
        />
    </div>
</template>
