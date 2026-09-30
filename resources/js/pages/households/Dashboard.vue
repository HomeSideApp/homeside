<script setup lang="ts">
import { Head, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import DashboardAccountsCard from '@/components/dashboard/DashboardAccountsCard.vue';
import DashboardActions from '@/components/dashboard/DashboardActions.vue';
import DashboardAlerts from '@/components/dashboard/DashboardAlerts.vue';
import DashboardAnalytics from '@/components/dashboard/DashboardAnalytics.vue';
import DashboardRecentTransactions from '@/components/dashboard/DashboardRecentTransactions.vue';
import DashboardStatsGrid from '@/components/dashboard/DashboardStatsGrid.vue';
import HouseholdComparisonCard from '@/components/dashboard/HouseholdComparisonCard.vue';
import type { HouseholdComparison } from '@/components/dashboard/HouseholdComparisonCard.vue';
import ModuleTiles from '@/components/dashboard/ModuleTiles.vue';
import QuickAccessSection from '@/components/dashboard/QuickAccessSection.vue';
import type {
    HouseholdEconomyOverview,
    PersonalEconomyOverview,
} from '@/components/dashboard/types';
import type { EconomicAccount, EconomyTransaction } from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useHousehold } from '@/composables/useHousehold';
import { dashboard as dashboardRoute } from '@/routes';

type DashboardData = {
    pending_items_count: number;
    active_lists_count: number;
    household_recipes_count: number;
    enabled_modules: string[];
};

type DashboardCapabilities = {
    householdEconomy: boolean;
    personalEconomy: boolean;
    createHouseholdExpense: boolean;
    importHouseholdTicket: boolean;
    createPersonalExpense: boolean;
    importPersonalTicket: boolean;
    accounts: boolean;
    lists: boolean;
    recipes: boolean;
    contacts: boolean;
};

type PersonalAccountsPayload = {
    total_minor: number;
    accounts: EconomicAccount[];
};

type RecentTransactionsPayload = {
    items: EconomyTransaction[];
    total: number;
};

type PendingImportsPayload = {
    personal: number;
    household: number;
    failed_personal: number;
    failed_household: number;
};

const props = defineProps<{
    dashboard?: DashboardData | null;
    household: { id: string; name: string } | null;
    can: DashboardCapabilities;
    householdEconomy?: HouseholdEconomyOverview;
    personalEconomy?: PersonalEconomyOverview;
    personalAccounts?: PersonalAccountsPayload;
    recentTransactions?: RecentTransactionsPayload;
    pendingImports?: PendingImportsPayload;
    householdComparison?: HouseholdComparison;
}>();

const { t } = useI18n();
const page = usePage();
const { householdUrl } = useHousehold();

/**
 * Deferred props are `undefined` while loading; the personal variant resolves to `null`. Computing
 * the list counts here keeps the narrowing out of the template.
 */
const showSkeletons = computed(() => props.dashboard === undefined);
const listCounts = computed(() =>
    props.dashboard && props.can.lists
        ? {
              pendingItems: props.dashboard.pending_items_count,
              activeLists: props.dashboard.active_lists_count,
          }
        : null,
);
const householdRecipesCount = computed(() =>
    props.dashboard && props.can.recipes
        ? props.dashboard.household_recipes_count
        : null,
);
const hasHouseholdDashboard = computed(
    () => props.dashboard !== undefined && props.dashboard !== null,
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: props.household
            ? [
                  { title: 'nav.households', href: '/households' },
                  { title: props.household.name, href: householdUrl('') },
              ]
            : [{ title: 'nav.dashboard', href: dashboardRoute() }],
    });
});
</script>

<template>
    <Head
        :title="
            household ? `Dashboard — ${household.name}` : t('sidebar.dashboard')
        "
    />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="
                    t('dashboard.greeting', {
                        name: String(page.props.auth.user.name),
                    })
                "
                :description="
                    household ? undefined : t('dashboard.personalPanel')
                "
            />
            <DashboardActions
                :can-create-expense="
                    can.createPersonalExpense || can.createHouseholdExpense
                "
                :can-import-ticket="
                    can.importPersonalTicket || can.importHouseholdTicket
                "
            />
        </div>

        <DashboardAlerts
            v-if="pendingImports"
            :pending="pendingImports"
            :household-id="household?.id ?? null"
        />

        <!-- `dashboard` is deferred: `undefined` while loading, but `null` is a valid resolved
             value for the personal variant, so the skeletons must only wait for the request. -->
        <div
            v-if="dashboard === undefined"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <Skeleton v-for="i in 4" :key="i" class="h-28 w-full" />
        </div>

        <ModuleTiles
            v-if="hasHouseholdDashboard"
            :can="can"
            :pending-items-count="listCounts?.pendingItems ?? null"
            :active-lists-count="listCounts?.activeLists ?? null"
            :household-recipes-count="householdRecipesCount"
        />

        <DashboardStatsGrid
            v-if="!showSkeletons"
            :comparison="householdComparison"
            :pending-items-count="listCounts?.pendingItems ?? null"
            :active-lists-count="listCounts?.activeLists ?? null"
            :show-economy="can.personalEconomy"
            :show-lists="can.lists"
        />

        <HouseholdComparisonCard
            v-if="can.personalEconomy && householdComparison"
            :comparison="householdComparison"
        />

        <div
            v-if="can.personalEconomy || can.accounts"
            class="grid gap-4 lg:grid-cols-2"
        >
            <DashboardRecentTransactions
                v-if="can.personalEconomy && recentTransactions"
                :transactions="recentTransactions.items"
                :total="recentTransactions.total"
            />
            <DashboardAccountsCard
                v-if="can.accounts && personalAccounts"
                :accounts="personalAccounts.accounts"
                :total-minor="personalAccounts.total_minor"
            />
        </div>

        <DashboardAnalytics
            :household-id="household?.id ?? null"
            :household-economy="householdEconomy"
            :personal-economy="personalEconomy"
            :show-household="can.householdEconomy"
            :show-personal="can.personalEconomy"
        />

        <QuickAccessSection
            :can-lists="can.lists"
            :can-recipes="can.recipes"
            :has-personal-economy="can.personalEconomy"
        />
    </div>
</template>
