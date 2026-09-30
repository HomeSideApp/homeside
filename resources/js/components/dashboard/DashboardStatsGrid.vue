<script setup lang="ts">
import {
    ClipboardList,
    Receipt,
    TrendingUp,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatMoney } from '@/components/dashboard/chartUtils';
import type { HouseholdComparison } from '@/components/dashboard/HouseholdComparisonCard.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    comparison?: HouseholdComparison;
    pendingItemsCount: number | null;
    activeListsCount: number | null;
    showEconomy: boolean;
    showLists: boolean;
}>();

const { t } = useI18n();

type StatCard = {
    key: string;
    label: string;
    value: string;
    detail?: string;
    icon: typeof Receipt;
};

/**
 * Build the current month summary cards from the cross-scope comparison, so every money figure
 * covers all of the user's active households plus the private account and reconciles with the
 * per-destination breakdown rendered below.
 *
 * Each card is independent: a user without economy permissions still gets the shopping list
 * summary while the money cards are being deferred or are unavailable.
 */
const cards = computed<StatCard[]>(() => {
    const items: StatCard[] = [];
    const totals = props.showEconomy
        ? props.comparison?.totals
        : undefined;

    if (totals) {
        const currency = props.comparison?.currency ?? null;

        items.push({
            key: 'expenses',
            label: t('economy.stats.expenses'),
            value: formatMoney(totals.balance_minor, currency),
            detail: t('dashboard.expensesDetail', {
                own: formatMoney(totals.own_minor, currency),
                shared: formatMoney(
                    totals.shared_participation_minor,
                    currency,
                ),
            }),
            icon: Receipt,
        });

        items.push({
            key: 'income',
            label: t('economy.stats.income'),
            value: formatMoney(totals.income_minor, currency),
            icon: TrendingUp,
        });

        items.push({
            key: 'balance',
            label: t('economy.stats.balance'),
            value: formatMoney(totals.net_minor, currency),
            detail: t('dashboard.netDetail'),
            icon: Wallet,
        });
    }

    if (props.showLists && props.pendingItemsCount !== null) {
        items.push({
            key: 'lists',
            label: t('dashboard.pendingItems'),
            value: String(props.pendingItemsCount),
            detail: t('dashboard.inLists', {
                count: props.activeListsCount ?? 0,
            }),
            icon: ClipboardList,
        });
    }

    return items;
});
</script>

<template>
    <section v-if="cards.length" class="flex flex-col gap-3">
        <h3 class="text-lg font-medium">{{ t('dashboard.thisMonth') }}</h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="card in cards" :key="card.key">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium">
                        {{ card.label }}
                    </CardTitle>
                    <component
                        :is="card.icon"
                        class="size-4 text-muted-foreground"
                    />
                </CardHeader>
                <CardContent class="flex flex-col gap-1">
                    <p class="text-2xl font-semibold">{{ card.value }}</p>
                    <p
                        v-if="card.detail"
                        class="text-xs text-muted-foreground"
                    >
                        {{ card.detail }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </section>
</template>
