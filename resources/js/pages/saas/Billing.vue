<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as redeemStore } from '@/routes/saas/redeem';
import { index as plansIndex } from '@/routes/saas/plans';

const props = defineProps<{
    entitlement: {
        user_id: string;
        plan: string;
        version: number;
        origin: string;
        features: Record<string, boolean>;
        quotas: Record<string, number | null>;
        usage: Record<string, { used: number; cap: number | null }>;
        expires_at: string | null;
    };
    currentPlan: string;
    checkoutEnabled: boolean;
}>();

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({
        title: t('saas.billing.title'),
        description: t('saas.billing.description'),
    });
});

const ORIGIN_VARIANTS: Record<string, 'default' | 'secondary' | 'outline'> = {
    granted: 'default',
    subscribed: 'default',
    trial: 'secondary',
    free: 'secondary',
};

const QUOTA_KEYS = [
    'households_own',
    'storage_mb',
    'ai_image_monthly',
    'ai_assistant_monthly',
] as const;

const originVariant = computed(
    () => ORIGIN_VARIANTS[props.entitlement.origin] ?? 'outline',
);

const expiresAt = computed(() =>
    props.entitlement.expires_at
        ? new Date(props.entitlement.expires_at).toLocaleDateString()
        : null,
);

const enabledFeatures = computed(() =>
    Object.entries(props.entitlement.features ?? {}).filter(
        ([, enabled]) => enabled,
    ),
);

const usageMetrics = computed(() => {
    const usage = props.entitlement.usage ?? {};
    const keys = new Set<string>([
        ...Object.keys(usage),
        ...QUOTA_KEYS,
    ]);
    return [...keys].map((key) => ({
        key,
        used: usage[key]?.used ?? 0,
        cap: usage[key]?.cap ?? null,
    }));
});

function percent(used: number, cap: number | null): number | null {
    if (cap === null || cap <= 0) {
        return null;
    }
    return Math.min(100, Math.round((used / cap) * 100));
}

function formatCap(cap: number | null): string {
    if (cap === null) {
        return t('saas.quotas.unlimited');
    }
    if (cap === 0) {
        return t('saas.quotas.none');
    }
    return String(cap);
}
</script>

<template>
    <Head :title="t('saas.billing.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            :title="t('saas.billing.title')"
            :description="t('saas.billing.description')"
        />

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>{{ t('saas.billing.entitlementTitle') }}</CardTitle>
                        <Badge :variant="originVariant">
                            {{ t(`saas.origins.${entitlement.origin}`) }}
                        </Badge>
                    </div>
                    <CardDescription>
                        {{ t('saas.billing.planLine', { plan: entitlement.plan, version: entitlement.version }) }}
                        <span v-if="expiresAt">
                            · {{ t('saas.billing.expiresAt', { date: expiresAt }) }}
                        </span>
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="[feature] in enabledFeatures"
                            :key="feature"
                            variant="outline"
                        >
                            {{ t(`saas.features.${feature}`) }}
                        </Badge>
                    </div>
                    <Button as-child variant="outline" class="w-fit">
                        <Link :href="plansIndex()">
                            {{ t('saas.billing.viewPlans') }}
                        </Link>
                    </Button>
                    <p
                        v-if="!checkoutEnabled"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('saas.billing.checkoutHint') }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('saas.billing.usageTitle') }}</CardTitle>
                    <CardDescription>
                        {{ t('saas.billing.usageDescription') }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-4">
                    <div
                        v-for="metric in usageMetrics"
                        :key="metric.key"
                        class="flex flex-col gap-1"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ t(`saas.quotas.${metric.key}`) }}</span>
                            <span class="text-muted-foreground">
                                {{
                                    t('saas.billing.usedOf', {
                                        used: metric.used,
                                        cap: formatCap(metric.cap),
                                    })
                                }}
                            </span>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                v-if="percent(metric.used, metric.cap) !== null"
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${percent(metric.used, metric.cap)}%`,
                                }"
                            />
                            <div
                                v-else-if="metric.cap === 0"
                                class="h-full w-full rounded-full bg-muted-foreground/30"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>{{ t('saas.billing.redeemTitle') }}</CardTitle>
                <CardDescription>
                    {{ t('saas.billing.redeemDescription') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="redeemStore.form()"
                    v-slot="{ errors, processing }"
                    class="flex max-w-md flex-col gap-3"
                >
                    <div class="grid gap-2">
                        <Label for="code">{{ t('saas.billing.redeemCode') }}</Label>
                        <Input
                            id="code"
                            type="text"
                            name="code"
                            required
                            :placeholder="t('saas.billing.redeemPlaceholder')"
                            autocomplete="off"
                        />
                        <InputError :message="errors.code" />
                    </div>
                    <Button type="submit" class="w-fit" :disabled="processing">
                        {{ t('saas.billing.redeemSubmit') }}
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
