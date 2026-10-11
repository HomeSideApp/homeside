<script setup lang="ts">
import { Check, Minus, Sparkles, X } from '@lucide/vue';
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { store as upgradeStore } from '@/routes/saas/upgrade';

const props = defineProps<{
    plans: SaasPlan[];
    currentPlan: string;
    checkoutEnabled: boolean;
}>();

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({
        title: t('saas.plans.title'),
        description: t('saas.plans.description'),
    });
});

type SaasPrice = {
    currency: string;
    interval: string;
    amount_cents: number;
};

type SaasPlan = {
    key: string;
    name: string;
    description: string | null;
    is_default: boolean;
    is_current: boolean;
    features: Record<string, boolean>;
    quotas: Record<string, number | null>;
    prices: SaasPrice[];
};

const FEATURE_KEYS = [
    'lists',
    'economy',
    'recipes',
    'ai_image_recognition',
    'ai_assistant',
] as const;

const QUOTA_KEYS = [
    'households_own',
    'storage_mb',
    'ai_image_monthly',
    'ai_assistant_monthly',
] as const;

const featureKeys = computed(() => {
    const keys = new Set<string>();
    for (const plan of props.plans) {
        Object.keys(plan.features ?? {}).forEach((key) => keys.add(key));
    }
    return keys.size > 0 ? [...keys] : [...FEATURE_KEYS];
});

const quotaKeys = computed(() => {
    const keys = new Set<string>();
    for (const plan of props.plans) {
        Object.keys(plan.quotas ?? {}).forEach((key) => keys.add(key));
    }
    return keys.size > 0 ? [...keys] : [...QUOTA_KEYS];
});

function formatPrice(price: SaasPrice): string {
    const amount = new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: price.currency,
    }).format(price.amount_cents / 100);

    return `${amount} / ${price.interval}`;
}

function formatQuota(value: number | null): string {
    if (value === null) {
        return t('saas.quotas.unlimited');
    }
    if (value === 0) {
        return t('saas.quotas.none');
    }
    return String(value);
}
</script>

<template>
    <Head :title="t('saas.plans.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            :title="t('saas.plans.title')"
            :description="t('saas.plans.description')"
        />

        <div
            v-if="!checkoutEnabled"
            class="rounded-lg border border-dashed bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            {{ t('saas.plans.checkoutHint') }}
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card
                v-for="plan in plans"
                :key="plan.key"
                class="flex flex-col"
                :class="{ 'border-primary': plan.is_current }"
            >
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>{{ plan.name }}</CardTitle>
                        <Badge v-if="plan.is_current" variant="default">
                            {{ t('saas.plans.currentPlan') }}
                        </Badge>
                        <Badge v-else-if="plan.is_default" variant="secondary">
                            {{ t('saas.plans.defaultPlan') }}
                        </Badge>
                    </div>
                    <CardDescription>
                        {{
                            plan.description ||
                            t(`saas.plans.descriptions.${plan.key}`)
                        }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="flex flex-1 flex-col gap-4">
                    <div class="text-2xl font-semibold">
                        <template v-if="plan.prices.length > 0">
                            {{ formatPrice(plan.prices[0]) }}
                        </template>
                        <template v-else>
                            {{ t('saas.plans.freePrice') }}
                        </template>
                    </div>

                    <ul class="flex flex-col gap-2 text-sm">
                        <li
                            v-for="featureKey in featureKeys"
                            :key="featureKey"
                            class="flex items-center gap-2"
                        >
                            <Check
                                v-if="plan.features?.[featureKey]"
                                class="size-4 text-success"
                            />
                            <X v-else class="size-4 text-muted-foreground" />
                            <span>
                                {{ t(`saas.features.${featureKey}`) }}
                            </span>
                        </li>
                        <li
                            v-for="quotaKey in quotaKeys"
                            :key="quotaKey"
                            class="flex items-center gap-2 text-muted-foreground"
                        >
                            <Minus class="size-4" />
                            <span>
                                {{ t(`saas.quotas.${quotaKey}`) }}:
                                {{ formatQuota(plan.quotas?.[quotaKey] ?? null) }}
                            </span>
                        </li>
                    </ul>
                </CardContent>

                <CardFooter>
                    <Button
                        v-if="!plan.is_current"
                        class="w-full"
                        :disabled="!checkoutEnabled"
                        @click="() => upgradeStore.form({ plan: plan.key }).submit()"
                    >
                        <Sparkles class="size-4" />
                        {{ t('saas.plans.upgradeTo', { plan: plan.name }) }}
                    </Button>
                    <Button v-else class="w-full" disabled>
                        {{ t('saas.plans.yourPlan') }}
                    </Button>
                </CardFooter>
            </Card>
        </div>
    </div>
</template>
