<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    Pencil,
    Plus,
    Search,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { formatAccountAmount } from '@/components/dashboard/chartUtils';
import EconomySummaryCards from '@/components/economy/EconomySummaryCards.vue';
import type {
    EconomicAccount,
    PaymentMethod,
} from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Empty, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import {
    archive as accountsArchive,
    create as accountsCreate,
    edit as accountsEdit,
    index as accountsIndex,
    show as accountsShow,
} from '@/routes/economy/me/accounts';
import { index as paymentMethodsIndex } from '@/routes/economy/me/payment-methods';

const { t } = useI18n();

const props = defineProps<{
    accounts: EconomicAccount[];
    filters: {
        search?: string;
        currency?: string;
        payment_method_id?: string;
        include_archived?: string;
    };
    paymentMethods: PaymentMethod[];
    totals: {
        balance_minor: number;
        accounts_count: number;
    };
}>();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'economy.accounts.title', href: accountsIndex.url() },
        ],
    });
});

const filtersOpen = ref(
    Boolean(
        props.filters.search ||
            props.filters.currency ||
            props.filters.payment_method_id ||
            props.filters.include_archived === '1',
    ),
);
const search = ref(props.filters.search ?? '');
const currency = ref(props.filters.currency ?? '');
const paymentMethodId = ref(props.filters.payment_method_id ?? '');
const includeArchived = ref(props.filters.include_archived === '1');

const hasActiveFilters = computed(
    () =>
        search.value !== '' ||
        currency.value !== '' ||
        paymentMethodId.value !== '',
);

const cards = computed(() => [
    {
        label: t('economy.accounts.totalBalance'),
        value: formatAccountAmount(props.totals.balance_minor, 'EUR', 2),
    },
    {
        label: t('economy.accounts.accountsCount'),
        value: String(props.totals.accounts_count),
    },
]);

function applyFilters(): void {
    router.get(
        accountsIndex.url(),
        {
            search: search.value || undefined,
            currency: currency.value || undefined,
            payment_method_id: paymentMethodId.value || undefined,
            include_archived: includeArchived.value ? '1' : undefined,
        },
        { preserveState: true, replace: true },
    );
}

function closeFilters(): void {
    filtersOpen.value = false;
    search.value = '';
    currency.value = '';
    paymentMethodId.value = '';
    includeArchived.value = false;
    applyFilters();
}

function toggleArchive(account: EconomicAccount): void {
    router.post(accountsArchive.url({ account: account.id }), {
        archived: !account.is_archived,
    });
}
</script>

<template>
    <Head :title="t('economy.accounts.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="t('economy.accounts.title')"
                :description="t('economy.accounts.description')"
            />
            <TooltipProvider>
                <div class="flex shrink-0 items-center gap-2">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                :aria-expanded="filtersOpen"
                                aria-controls="accounts-filters"
                                :aria-label="t('economy.accounts.toggleFilters')"
                                @click="filtersOpen = !filtersOpen"
                            >
                                <Search aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.accounts.toggleFilters')
                        }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                v-can="'economy.me.payment-methods.index'"
                                variant="outline"
                                size="icon-sm"
                                as-child
                            >
                                <Link
                                    :href="paymentMethodsIndex.url()"
                                    :aria-label="
                                        t('economy.paymentMethods.title')
                                    "
                                >
                                    <Wallet aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.paymentMethods.title')
                        }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                v-can="'economy.me.accounts.store'"
                                size="icon-sm"
                                as-child
                            >
                                <Link
                                    :href="accountsCreate.url()"
                                    :aria-label="t('economy.accounts.create')"
                                >
                                    <Plus aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('economy.accounts.create')
                        }}</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </div>

        <EconomySummaryCards :cards="cards" />

        <Card v-if="filtersOpen" id="accounts-filters">
            <CardContent class="pt-6">
                <FieldGroup class="grid gap-4 md:grid-cols-3">
                    <Field>
                        <FieldLabel for="accounts-search">{{
                            t('economy.ui.search')
                        }}</FieldLabel>
                        <Input
                            id="accounts-search"
                            v-model="search"
                            @blur="applyFilters"
                            @keyup.enter="applyFilters"
                        />
                    </Field>
                    <Field>
                        <FieldLabel for="accounts-currency">{{
                            t('economy.account.currency')
                        }}</FieldLabel>
                        <Input
                            id="accounts-currency"
                            v-model="currency"
                            maxlength="10"
                            @blur="applyFilters"
                            @keyup.enter="applyFilters"
                        />
                    </Field>
                    <Field>
                        <FieldLabel for="accounts-method">{{
                            t('economy.account.paymentMethod')
                        }}</FieldLabel>
                        <Select
                            v-model="paymentMethodId"
                            @update:model-value="applyFilters"
                        >
                            <SelectTrigger id="accounts-method">
                                <SelectValue :placeholder="t('economy.ui.all')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="">
                                        {{ t('economy.ui.all') }}
                                    </SelectItem>
                                    <SelectItem
                                        v-for="method in paymentMethods"
                                        :key="method.id"
                                        :value="method.id"
                                    >
                                        {{ method.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                </FieldGroup>
                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="accounts-archived"
                            :model-value="includeArchived"
                            @update:model-value="
                                (value) => {
                                    includeArchived = Boolean(value);
                                    applyFilters();
                                }
                            "
                        />
                        <FieldLabel for="accounts-archived">{{
                            t('economy.accounts.includeArchived')
                        }}</FieldLabel>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="closeFilters"
                    >
                        <X data-icon="inline-start" aria-hidden="true" />
                        {{ t('economy.ui.close') }}
                    </Button>
                </div>
                <FieldDescription v-if="hasActiveFilters" class="mt-2">
                    {{ t('economy.accounts.filtersActive') }}
                </FieldDescription>
            </CardContent>
        </Card>

        <Empty v-if="accounts.length === 0">
            <EmptyHeader>
                <EmptyTitle>{{ t('economy.accounts.empty') }}</EmptyTitle>
            </EmptyHeader>
        </Empty>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="account in accounts" :key="account.id">
                <CardContent class="flex flex-col gap-3 pt-6">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                class="size-3 rounded-full"
                                :style="{ backgroundColor: account.color }"
                                aria-hidden="true"
                            />
                            <div>
                                <Link
                                    :href="accountsShow.url(account.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ account.name }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        (account.payment_methods ?? [])
                                            .map((method) => method.name)
                                            .join(', ') || '—'
                                    }}
                                    · {{ account.currency }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        v-can="'economy.me.accounts.update'"
                                        variant="ghost"
                                        size="icon-sm"
                                        as-child
                                    >
                                        <Link
                                            :href="
                                                accountsEdit.url(account.id)
                                            "
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
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        v-can="'economy.me.accounts.archive'"
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="
                                            account.is_archived
                                                ? t('economy.accounts.restore')
                                                : t('economy.accounts.archive')
                                        "
                                        @click="toggleArchive(account)"
                                    >
                                        <ArchiveRestore
                                            v-if="account.is_archived"
                                            aria-hidden="true"
                                        />
                                        <Archive v-else aria-hidden="true" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{{
                                    account.is_archived
                                        ? t('economy.accounts.restore')
                                        : t('economy.accounts.archive')
                                }}</TooltipContent>
                            </Tooltip>
                        </div>
                    </div>
                    <p class="text-2xl font-semibold">
                        {{
                            formatAccountAmount(
                                account.balance_minor ?? 0,
                                account.currency,
                                account.decimal_places,
                            )
                        }}
                    </p>
                    <p
                        v-if="account.is_archived"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('economy.accounts.archived') }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
