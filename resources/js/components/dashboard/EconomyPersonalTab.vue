<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { PiggyBank, Wallet } from '@lucide/vue';
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
import { index as personalEconomyIndex } from '@/routes/economy/me';
import EconomyAreaChart from './charts/EconomyAreaChart.vue';
import EconomyDonutChart from './charts/EconomyDonutChart.vue';
import {
    formatMinor,
    formatMoney,
    minorToChartValue,
    monthLabel,
} from './chartUtils';
import type { PersonalEconomyOverview } from './types';

const props = defineProps<{
    overview: PersonalEconomyOverview;
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
        label: t('dashboard.ownSeries'),
        value: formatMoney(props.overview.totals.own_minor, currency.value),
        detail: t('dashboard.ownDetail', {
            privateAmount: formatMoney(
                props.overview.totals.without_house_minor,
                currency.value,
            ),
        }),
    },
    {
        label: t('economy.stats.sharedParticipation'),
        value: formatMoney(
            props.overview.totals.shared_participation_minor,
            currency.value,
        ),
    },
    {
        label: t('economy.stats.personalTotal'),
        value: formatMoney(
            props.overview.totals.personal_total_minor,
            currency.value,
        ),
        detail: otherCurrenciesDetail.value,
    },
]);

const monthlyData = computed(() =>
    props.overview.monthly.map((point, index) => ({
        x: index,
        label: monthLabel(point.month, String(locale.value), true),
        own: minorToChartValue(point.own_minor),
        shared: minorToChartValue(point.shared_minor),
    })),
);

const monthlyConfig = computed<ChartConfig>(() => ({
    own: {
        label: t('dashboard.ownSeries'),
        color: 'var(--chart-1)',
    },
    shared: {
        label: t('economy.stats.sharedParticipation'),
        color: 'var(--chart-3)',
    },
}));

const householdSlices = computed(() =>
    props.overview.by_household.map((point, index) => ({
        key: `household-${index}`,
        label: point.name ?? t('dashboard.noHousehold'),
        value: minorToChartValue(point.total_minor),
    })),
);

const householdConfig = computed<ChartConfig>(() =>
    Object.fromEntries(
        props.overview.by_household.map((point, index) => [
            `household-${index}`,
            {
                label: point.name ?? t('dashboard.noHousehold'),
                color: `var(--chart-${(index % 5) + 1})`,
            },
        ]),
    ),
);

const householdTotal = computed(() =>
    props.overview.by_household.reduce(
        (total, point) => total + minorToChartValue(point.total_minor),
        0,
    ),
);

const hasHouseholdData = computed(() => householdTotal.value > 0);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-lg font-medium">{{ t('dashboard.myEconomy') }}</h3>
            <div class="flex items-center gap-2">
                <Badge v-if="currency" variant="secondary">{{ currency }}</Badge>
                <Button as-child variant="outline" size="sm">
                    <Link :href="personalEconomyIndex.url()">
                        <Wallet data-icon="inline-start" />
                        {{ t('dashboard.viewMyEconomy') }}
                    </Link>
                </Button>
            </div>
        </div>

        <EconomySummaryCards :cards="kpiCards" />

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t('dashboard.monthlyPersonalExpenses') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <!-- Own spending sits at the bottom of the stack, shared participation on top. -->
                    <EconomyAreaChart
                        :data="monthlyData"
                        :stacked-keys="['own', 'shared']"
                        :config="monthlyConfig"
                    />
                    <p class="pt-2 text-xs text-muted-foreground">
                        {{ t('dashboard.stackedTotalHint') }}
                    </p>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">
                        {{ t('dashboard.spendByHousehold') }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Empty v-if="!hasHouseholdData" class="border-none">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <PiggyBank />
                            </EmptyMedia>
                            <EmptyTitle>{{ t('dashboard.noData') }}</EmptyTitle>
                            <EmptyDescription>
                                {{ t('dashboard.noDataHint') }}
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                    <div v-else class="flex flex-col gap-2">
                        <EconomyDonutChart
                            :slices="householdSlices"
                            :config="householdConfig"
                            :central-label="householdTotal.toLocaleString()"
                            :central-sub-label="t('economy.stats.personalTotal')"
                        />
                        <ul class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <li
                                v-for="slice in householdSlices"
                                :key="slice.key"
                                class="flex items-center gap-1.5"
                            >
                                <span
                                    class="size-2 rounded-xs"
                                    :style="{
                                        backgroundColor:
                                            householdConfig[slice.key]?.color,
                                    }"
                                />
                                {{ slice.label }} · {{ slice.value.toLocaleString() }}
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
