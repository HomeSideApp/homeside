<script setup lang="ts">
import { BarChart3 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import EconomyHomeTab from '@/components/dashboard/EconomyHomeTab.vue';
import EconomyPersonalTab from '@/components/dashboard/EconomyPersonalTab.vue';
import type {
    HouseholdEconomyOverview,
    PersonalEconomyOverview,
} from '@/components/dashboard/types';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';

defineProps<{
    householdId: string | null;
    householdEconomy?: HouseholdEconomyOverview;
    personalEconomy?: PersonalEconomyOverview;
    showHousehold: boolean;
    showPersonal: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <section class="flex flex-col gap-6">
        <h3 class="flex items-center gap-2 text-lg font-medium">
            <BarChart3 class="size-4" />
            {{ t('dashboard.analytics') }}
        </h3>

        <div v-if="showPersonal" class="flex flex-col gap-4">
            <EconomyPersonalTab
                v-if="personalEconomy"
                :overview="personalEconomy"
            />
            <div v-else class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Skeleton v-for="i in 3" :key="i" class="h-24 w-full" />
                </div>
                <Skeleton class="h-56 w-full" />
            </div>
        </div>

        <Separator v-if="showPersonal && showHousehold" />

        <div v-if="showHousehold && householdId" class="flex flex-col gap-4">
            <EconomyHomeTab
                v-if="householdEconomy"
                :overview="householdEconomy"
                :household-id="householdId"
            />
            <div v-else class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Skeleton v-for="i in 4" :key="i" class="h-24 w-full" />
                </div>
                <Skeleton class="h-56 w-full" />
            </div>
        </div>
    </section>
</template>
