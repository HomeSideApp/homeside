<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Landmark } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatAccountAmount } from '@/components/dashboard/chartUtils';
import type { EconomicAccount } from '@/components/economy/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { index as accountsIndex } from '@/routes/economy/me/accounts';

const props = defineProps<{
    accounts: EconomicAccount[];
    totalMinor: number;
}>();

const { t } = useI18n();

/**
 * Group the accounts by currency so amounts in different currencies are never summed together.
 */
const groupedByCurrency = computed(() => {
    const groups = new Map<
        string,
        { currency: string; accounts: EconomicAccount[]; totalMinor: number }
    >();

    for (const account of props.accounts) {
        const group = groups.get(account.currency) ?? {
            currency: account.currency,
            accounts: [],
            totalMinor: 0,
        };

        group.accounts.push(account);
        group.totalMinor += account.include_in_totals
            ? (account.balance_minor ?? 0)
            : 0;
        groups.set(account.currency, group);
    }

    return [...groups.values()];
});
</script>

<template>
    <Card>
        <CardHeader
            class="flex flex-row items-center justify-between space-y-0 pb-2"
        >
            <CardTitle class="text-base">
                {{ t('dashboard.myAccounts') }}
            </CardTitle>
            <Button as-child variant="ghost" size="sm">
                <Link :href="accountsIndex.url()">
                    {{ t('dashboard.viewAll') }}
                    <ArrowRight data-icon="inline-end" />
                </Link>
            </Button>
        </CardHeader>
        <CardContent class="flex flex-col gap-4">
            <Empty v-if="accounts.length === 0" class="border-none">
                <EmptyHeader>
                    <EmptyMedia variant="icon">
                        <Landmark />
                    </EmptyMedia>
                    <EmptyTitle>{{ t('dashboard.noAccounts') }}</EmptyTitle>
                    <EmptyDescription>
                        {{ t('dashboard.noAccountsHint') }}
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>

            <div
                v-for="group in groupedByCurrency"
                :key="group.currency"
                class="flex flex-col gap-2"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-xs text-muted-foreground">
                        {{ t('dashboard.totalBalance') }}
                    </span>
                    <span class="font-mono text-lg font-semibold">
                        {{
                            formatAccountAmount(
                                group.totalMinor,
                                group.currency,
                                2,
                            )
                        }}
                    </span>
                </div>

                <ul class="flex flex-col divide-y divide-border">
                    <li
                        v-for="account in group.accounts"
                        :key="account.id"
                        class="flex items-center justify-between gap-3 py-2"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <span
                                class="size-2 shrink-0 rounded-full"
                                :style="{ backgroundColor: account.color }"
                            />
                            <span class="truncate text-sm">
                                {{ account.name }}
                            </span>
                            <span
                                v-if="account.last_four_digits"
                                class="text-xs text-muted-foreground"
                            >
                                ····{{ account.last_four_digits }}
                            </span>
                        </span>
                        <span class="shrink-0 font-mono text-sm">
                            {{
                                formatAccountAmount(
                                    account.balance_minor ?? 0,
                                    account.currency,
                                    account.decimal_places,
                                )
                            }}
                        </span>
                    </li>
                </ul>

                <p class="text-xs text-muted-foreground">
                    {{ t('dashboard.accountsTotalHint') }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
