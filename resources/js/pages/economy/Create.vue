<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import TransactionForm from '@/components/economy/TransactionForm.vue';
import type { EconomicAccount } from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { index as privateEconomyIndex } from '@/routes/economy/me';
import { index as householdEconomyIndex } from '@/routes/households/economy';

const { t } = useI18n();

const props = defineProps<{
    household: { id: string; name: string } | null;
    members: Array<{ id: string; user: { id: string; name: string } }>;
    membersByHousehold?: Record<
        string,
        Array<{ id: string; user: { id: string; name: string } }>
    >;
    splitTypesByHousehold?: Record<string, 'equal' | 'fixed' | 'percentage'>;
    accounts?: EconomicAccount[];
}>();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: props.household ? 'economy.title' : 'economy.myEconomy',
                href: props.household
                    ? householdEconomyIndex.url(props.household.id)
                    : privateEconomyIndex.url(),
            },
            { title: 'economy.create' },
        ],
    });
});
</script>

<template>
    <Head :title="t('economy.create')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="
                household ? t('economy.create') : t('economy.createPrivate')
            "
            :description="t('economy.createDescription')"
        />

        <div class="max-w-4xl">
            <TransactionForm
                :household="household"
                :members="members"
                :members-by-household="membersByHousehold ?? {}"
                :split-types-by-household="splitTypesByHousehold ?? {}"
                :accounts="accounts ?? []"
            />
        </div>
    </div>
</template>
