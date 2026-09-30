<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ReceiptText } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import EconomySummaryCards from '@/components/economy/EconomySummaryCards.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { index as householdEconomyIndex } from '@/routes/households/economy';
import EconomyAreaChart from './charts/EconomyAreaChart.vue';
import EconomyDonutChart from './charts/EconomyDonutChart.vue';
import EconomyHorizontalBarChart from './charts/EconomyHorizontalBarChart.vue';
import {
    formatMinor,
    formatMoney,
    minorToChartValue,
    monthLabel,
} from './chartUtils';
import type { HouseholdEconomyOverview } from './types';

const props = defineProps<{
    overview: HouseholdEconomyOverview;
    householdId: string;
}>();

const { t, locale } = useI18n();

const currency = computed(() => props.overview.currency);

const otherCurrenciesDetail = computed(() => {
    const entries = Object.entries(props.overview.other_currencies);

    if (entries.length === 0) {
        return undefined;
    }

    return t('dashboard.otherCurrencies', {
        list: entries
            .map(([code, minor]) => `${code} ${formatMinor(minor)}`)
            .join(' · '),
    });
});

const kpiCards = computed(() => [
    {
        label: t('economy.stats.expenses'),
        value: formatMoney(props.overview.totals.expenses_minor, currency.value),
    },
    {
        label: t('economy.stats.income'),
        value: formatMoney(props.overview.totals.income_minor, currency.value),
    },
    {
        label: t('economy.stats.balance'),
        value: formatMoney(props.overview.totals.balance_minor, currency.value),
    },
    {
        label: t('economy.stats.shared'),
        value: formatMoney(
            props.overview.totals.shared_expenses_minor,
            currency.value,
        ),
        detail: otherCurrenciesDetail.value,
    },
]);

const monthlyData = computed(() =>
    props.overview.monthly.map((point, index) => ({
        x: index,
        label: monthLabel(point.month, String(locale.value), true),
        expenses: minorToChartValue(point.expenses_minor),
        income: minorToChartValue(point.income_minor),
    })),
);

const monthlyConfig = computed<ChartConfig>(() => ({
    expenses: {
        label: t('economy.stats.expenses'),
        color: 'var(--chart-1)',
    },
    income: {
        label: t('economy.stats.income'),
        color: 'var(--chart-3)',
    },
}));

const scopeSlices = computed(() => [
    {
        key: 'personal',
        label: t('economy.transaction.personal'),
        value: minorToChartValue(props.overview.totals.personal_expenses_minor),
    },
    {
        key: 'shared',
        label: t('economy.transaction.shared'),
        value: minorToChartValue(props.overview.totals.shared_expenses_minor),
    },
]);

const scopeConfig = computed<ChartConfig>(() => ({
    personal: {
        label: t('economy.transaction.personal'),
        color: 'var(--chart-2)',
    },
    shared: {
        label: t('economy.transaction.shared'),
        color: 'var(--chart-1)',
    },
}));

const scopeTotal = computed(
    () =>
        minorToChartValue(props.overview.totals.personal_expenses_minor) +
        minorToChartValue(props.overview.totals.shared_expenses_minor),
);

const hasScopeData = computed(() => scopeTotal.value > 0);

const placeData = computed(() =>
    props.overview.by_place.map((point, index) => ({
        x: index,
        label: point.place,
        total: minorToChartValue(point.total_minor),
    })),
);

const placeConfig = computed<ChartConfig>(() => ({
    total: {
        label: t('economy.stats.expenses'),
        color: 'var(--chart-2)',
    },
}));

const memberData = computed(() =>
    props.overview.by_member.map((point, index) => ({
        x: index,
        label: point.name,
        total: minorToChartValue(point.total_minor),
    })),
);

const memberConfig = computed<ChartConfig>(() => ({
    total: {
        label: t('economy.stats.expenses'),
        color: 'var(--chart-1)',
    },
}));
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-medium">{{ t('dashboard.householdEconomy') }}</h3>
            <div class="flex items-center gap-2">
                <Badge v-if="currency" variant="secondary">{{ currency }}</Badge>
                <Button as-child variant="outline" size="sm">
                    <Link :href="householdEconomyIndex.url(householdId)">
                        <ReceiptText data-icon="inline-start" />
                        {{ t('dashboard.viewHouseholdEconomy') }}
                    </Link>
                </Button>
            </div>
        </div>

        <EconomySummaryCards :cards="kpiCards" />

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t('dashboard.monthlyExpensesIncome') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Expenses are the stacked series; income is drawn as a standalone line. -->
                    <EconomyAreaChart
                        :data="monthlyData"
                        :stacked-keys="['expenses']"
                        :line-keys="['income']"
                        :config="monthlyConfig"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t('dashboard.personalVsShared') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Empty v-if="!hasScopeData" class="border-none">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ReceiptText />
                            </EmptyMedia>
                            <EmptyTitle>{{ t('dashboard.noData') }}</EmptyTitle>
                            <EmptyDescription>
                                {{ t('dashboard.noDataHint') }}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                    <EconomyDonutChart
                        v-else
                        :slices="scopeSlices"
                        :config="scopeConfig"
                        :central-label="scopeTotal.toLocaleString()"
                        :central-sub-label="t('economy.stats.expenses')"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t('dashboard.topPlaces') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Empty v-if="placeData.length === 0" class="border-none">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ReceiptText />
                            </EmptyMedia>
                            <EmptyTitle>{{ t('dashboard.noData') }}</EmptyTitle>
                            <EmptyDescription>
                                {{ t('dashboard.noDataHint') }}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                    <EconomyHorizontalBarChart
                        v-else
                        :data="placeData"
                        :config="placeConfig"
                        series-key="total"
                    />
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t('dashboard.spendByMember') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Empty v-if="memberData.length === 0" class="border-none">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <ReceiptText />
                            </EmptyMedia>
                            <EmptyTitle>{{ t('dashboard.noData') }}</EmptyTitle>
                            <EmptyDescription>
                                {{ t('dashboard.noDataHint') }}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                    <EconomyHorizontalBarChart
                        v-else
                        :data="memberData"
                        :config="memberConfig"
                        series-key="total"
                        :height="Math.max(160, memberData.length * 48)"
                    />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
