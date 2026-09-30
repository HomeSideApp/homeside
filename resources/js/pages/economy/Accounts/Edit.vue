<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import AccountForm from '@/components/economy/AccountForm.vue';
import type {
    CryptoAsset,
    EconomicAccount,
    PaymentMethod,
} from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { index as accountsIndex } from '@/routes/economy/me/accounts';

const { t } = useI18n();

const props = defineProps<{
    account: EconomicAccount;
    paymentMethods: PaymentMethod[];
    cryptoAssets?: CryptoAsset[];
}>();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'economy.accounts.title', href: accountsIndex.url() },
            { title: 'economy.accounts.editTitle' },
        ],
    });
});
</script>

<template>
    <Head :title="t('economy.accounts.editTitle')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('economy.accounts.editTitle')"
            :description="t('economy.accounts.editDescription')"
        />

        <AccountForm
            :payment-methods="props.paymentMethods"
            :crypto-assets="props.cryptoAssets ?? []"
            :account="props.account"
        />
    </div>
</template>
