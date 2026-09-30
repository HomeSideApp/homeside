<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Receipt } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { EconomyTransaction } from '@/components/economy/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { show as personalShow } from '@/routes/economy/me';
import { index as personalEconomyIndex } from '@/routes/economy/me';
import { show as householdShow } from '@/routes/households/economy';

const props = defineProps<{
    transactions: EconomyTransaction[];
    total: number;
}>();

const { t, locale } = useI18n();

/**
 * Format the movement date, falling back to "no date" when the ticket date was unreadable.
 *
 * The dashboard deliberately shows the same date the API exposes instead of hiding the movement.
 */
function formatDate(transaction: EconomyTransaction): string {
    if (!transaction.occurred_at) {
        return t('dashboard.noDate');
    }

    return new Date(transaction.occurred_at).toLocaleDateString(
        String(locale.value),
    );
}

/**
 * Resolve the detail link, routing household movements through the household scope.
 */
function detailUrl(transaction: EconomyTransaction): string {
    return transaction.household_id
        ? householdShow({
              household: transaction.household_id,
              transaction: transaction.id,
          }).url
        : personalShow(transaction.id).url;
}

/**
 * Render a movement amount with its currency, keeping the account precision from the API.
 */
function formatTransactionAmount(transaction: EconomyTransaction): string {
    return `${transaction.amount} ${
        transaction.currency === 'EUR' ? '€' : transaction.currency
    }`;
}

const hasMore = computed(() => props.total > props.transactions.length);
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-2"
        >
            <CardTitle class="text-base">
                {{ t('dashboard.recentMovements') }}
            </CardTitle>
            <Button as-child variant="ghost" size="sm">
                <Link :href="personalEconomyIndex.url()">
                    {{ t('dashboard.viewAll') }}
                    <ArrowRight data-icon="inline-end" />
                </Link>
            </Button>
        </CardHeader>
        <CardContent>
            <Empty v-if="transactions.length === 0" class="border-none">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Receipt />
                    </EmptyMedia>
                    <EmptyTitle>{{ t('dashboard.noMovements') }}</EmptyTitle>
                    <EmptyDescription>
                        {{ t('dashboard.noMovementsHint') }}
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>

            <ul v-else class="flex flex-col divide-y divide-border">
                <li
                    v-for="transaction in transactions"
                    :key="transaction.id"
                    class="flex items-center justify-between gap-3 py-3"
                >
                    <div class="flex min-w-0 flex-col">
                        <Link
                            :href="detailUrl(transaction)"
                            class="truncate text-sm font-medium hover:underline"
                        >
                            {{ transaction.title }}
                        </Link>
                        <span
                            class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span>{{ formatDate(transaction) }}</span>
                            <Badge variant="outline" class="text-[10px]">
                                {{
                                    transaction.origin ??
                                    t('dashboard.privateOrigin')
                                }}
                            </Badge>
                        </span>
                    </div>
                    <span
                        class="shrink-0 font-mono text-sm"
                        :class="
                            transaction.type === 'income'
                                ? 'text-success'
                                : undefined
                        "
                    >
                        {{ transaction.type === 'income' ? '+' : '−' }}
                        {{ formatTransactionAmount(transaction) }}
                    </span>
                </li>
            </ul>

            <p v-if="hasMore" class="pt-3 text-xs text-muted-foreground">
                {{ t('dashboard.moreMovements', { count: total - transactions.length }) }}
            </p>
        </CardContent>
    </Card>
</template>
