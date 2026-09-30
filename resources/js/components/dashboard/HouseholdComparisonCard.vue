<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Scale } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatMoney } from '@/components/dashboard/chartUtils';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { index as personalEconomyIndex } from '@/routes/economy/me';
import { index as householdEconomyIndex } from '@/routes/households/economy';

export interface HouseholdComparisonRow {
    scope: 'private' | 'household';
    household_id: string | null;
    name: string | null;
    own_minor: number;
    shared_participation_minor: number;
    balance_minor: number;
    income_minor: number;
    net_minor: number;
}

export interface HouseholdComparison {
    currency: string | null;
    month: string;
    rows: HouseholdComparisonRow[];
    totals: {
        own_minor: number;
        shared_participation_minor: number;
        balance_minor: number;
        income_minor: number;
        net_minor: number;
    };
}

const props = defineProps<{
    comparison: HouseholdComparison;
}>();

const { t } = useI18n();

/**
 * Labels and detail links are resolved per row so a private row never links to a household.
 */
const rows = computed(() =>
    props.comparison.rows.map((row) => ({
        ...row,
        label: row.name ?? t('dashboard.privateOrigin'),
        href: row.household_id
            ? householdEconomyIndex.url(row.household_id)
            : personalEconomyIndex.url(),
    })),
);

const hasRows = computed(() => rows.value.length > 0);
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-2"
        >
            <CardTitle class="text-base">
                {{ t('dashboard.comparisonTitle') }}
            </CardTitle>
            <span class="text-xs text-muted-foreground">
                {{ t('dashboard.thisMonth') }}
            </span>
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <Empty v-if="!hasRows" class="border-none">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Scale />
                    </EmptyMedia>
                    <EmptyTitle>{{ t('dashboard.noData') }}</EmptyTitle>
                    <EmptyDescription>
                        {{ t('dashboard.noDataHint') }}
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>

            <template v-else>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-muted-foreground">
                                <th class="pb-2 font-medium">
                                    {{ t('dashboard.comparisonDestination') }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t('dashboard.comparisonOwn') }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t('economy.stats.sharedParticipation') }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t('economy.stats.personalTotal') }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t('economy.stats.income') }}
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    {{ t('dashboard.comparisonNet') }}
                                </th>
                                <th class="pb-2" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="row in rows"
                                :key="`${row.scope}-${row.household_id ?? 'private'}`"
                            >
                                <td class="py-2">
                                    {{ row.label }}
                                </td>
                                <td class="py-2 text-right font-mono">
                                    {{
                                        formatMoney(
                                            row.own_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-2 text-right font-mono">
                                    {{
                                        formatMoney(
                                            row.shared_participation_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-2 text-right font-mono font-medium">
                                    {{
                                        formatMoney(
                                            row.balance_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-2 text-right font-mono">
                                    {{
                                        formatMoney(
                                            row.income_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-2 text-right font-mono font-medium">
                                    {{
                                        formatMoney(
                                            row.net_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-2 pl-2 text-right">
                                    <Button
                                        as-child
                                        variant="ghost"
                                        size="icon-sm"
                                    >
                                        <Link
                                            :href="row.href"
                                            :aria-label="row.label"
                                        >
                                            <ArrowRight aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-border font-medium">
                                <td class="pt-2">
                                    {{ t('dashboard.comparisonTotal') }}
                                </td>
                                <td class="pt-2 text-right font-mono">
                                    {{
                                        formatMoney(
                                            comparison.totals.own_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="pt-2 text-right font-mono">
                                    {{
                                        formatMoney(
                                            comparison.totals
                                                .shared_participation_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="pt-2 text-right font-mono">
                                    {{
                                        formatMoney(
                                            comparison.totals.balance_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="pt-2 text-right font-mono">
                                    {{
                                        formatMoney(
                                            comparison.totals.income_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td class="pt-2 text-right font-mono font-medium">
                                    {{
                                        formatMoney(
                                            comparison.totals.net_minor,
                                            comparison.currency,
                                        )
                                    }}
                                </td>
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="text-xs text-muted-foreground">
                    {{ t('dashboard.comparisonHint') }}
                </p>
            </template>
        </CardContent>
    </Card>
</template>
