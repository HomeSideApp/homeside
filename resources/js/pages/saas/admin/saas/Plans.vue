<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    archive as archivePlan,
    prices as savePrice,
    publish as publishPlan,
    store as storePlan,
    update as updatePlan,
} from '@/routes/admin/saas/plans';

interface PlanVersionView {
    id: string;
    version: number;
    features: Record<string, boolean>;
    quotas: Record<string, number | null>;
    published_at: string | null;
    is_draft: boolean;
    prices: Array<{
        id: string;
        currency: string;
        interval: string;
        amount_cents: number;
        active: boolean;
    }>;
}

interface PlanView {
    id: string;
    key: string;
    name: string;
    visibility: string;
    sort_order: number;
    is_default: boolean;
    archived_at: string | null;
    versions: PlanVersionView[];
}

interface CapabilityFeature {
    key: string;
    label: string;
    module: string | null;
    metric: string | null;
    permissions: string[];
}

interface CapabilityQuota {
    key: string;
    label: string;
    unit: string;
}

interface Capabilities {
    features: CapabilityFeature[];
    quotas: CapabilityQuota[];
}

const props = defineProps<{ plans: PlanView[]; capabilities: Capabilities }>();

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({ title: t('saas.admin.plans.title') });
});

const creating = ref(false);
const newPlan = ref({ key: '', name: '', visibility: 'public', sort_order: 0 });

const editedPlanId = ref<string | null>(null);

// The editor state is keyed by the DECLARED catalog keys only. Unknown or
// legacy keys are never rendered, so a typo can never be submitted.
const featureValues = ref<Record<string, boolean>>({});
const quotaValues = ref<Record<string, string>>({});
const priceRow = ref({ currency: 'USD', interval: 'month', amount_cents: 0 });

function draftOf(plan: PlanView): PlanVersionView | null {
    return plan.versions.find((version) => version.is_draft) ?? null;
}

function publishedOf(plan: PlanView): PlanVersionView | null {
    return (
        plan.versions
            .filter((version) => !version.is_draft)
            .sort((a, b) => b.version - a.version)[0] ?? null
    );
}

/**
 * Hydrate the editor from the plan's draft when it has one, falling back to
 * its newest PUBLISHED version.
 *
 * DATA-LOSS TRAP: seeded plans only ship a published v1. Hydrating from the
 * draft alone (`draftOf(plan)`) opened a blank editor for every one of them;
 * saving then created a draft with an empty features/quotas matrix that wiped
 * the plan's real characteristics the moment it was published. Always
 * hydrating `draftOf ?? publishedOf` keeps the current matrix intact.
 */
function hydrateEditor(plan: PlanView): void {
    const version = draftOf(plan) ?? publishedOf(plan);

    const features: Record<string, boolean> = {};
    for (const feature of props.capabilities.features) {
        features[feature.key] = Boolean(version?.features?.[feature.key]);
    }

    const quotas: Record<string, string> = {};
    for (const quota of props.capabilities.quotas) {
        const value = version?.quotas?.[quota.key];
        quotas[quota.key] = value === null || value === undefined ? '' : String(value);
    }

    featureValues.value = features;
    quotaValues.value = quotas;
}

function startEdit(plan: PlanView): void {
    if (editedPlanId.value === plan.id) {
        editedPlanId.value = null;

        return;
    }

    hydrateEditor(plan);
    editedPlanId.value = plan.id;
}

function setFeature(key: string, value: boolean): void {
    featureValues.value[key] = value;
}

function payloadFeatures(): Record<string, boolean> {
    const payload: Record<string, boolean> = {};
    for (const feature of props.capabilities.features) {
        payload[feature.key] = Boolean(featureValues.value[feature.key]);
    }

    return payload;
}

function payloadQuotas(): Record<string, number | null> {
    const payload: Record<string, number | null> = {};
    for (const quota of props.capabilities.quotas) {
        const raw = quotaValues.value[quota.key] ?? '';
        payload[quota.key] = raw === '' ? null : Number.parseInt(raw, 10);
    }

    return payload;
}

function saveDraft(plan: PlanView): void {
    router.put(
        updatePlan({ plan: plan.id }).url,
        {
            key: plan.key,
            name: plan.name,
            visibility: plan.visibility,
            sort_order: plan.sort_order,
            is_default: plan.is_default,
            features: payloadFeatures(),
            quotas: payloadQuotas(),
        },
        { preserveScroll: true },
    );
}

function createPlan(): void {
    router.post(storePlan().url, newPlan.value, {
        preserveScroll: true,
        onSuccess: () => {
            creating.value = false;
            newPlan.value = { key: '', name: '', visibility: 'public', sort_order: 0 };
        },
    });
}

function addPrice(plan: PlanView): void {
    router.post(
        savePrice({ plan: plan.id }).url,
        { ...priceRow.value, active: true },
        { preserveScroll: true },
    );
}

const hasDraft = computed(
    () => (plan: PlanView) => draftOf(plan) !== null,
);
</script>

<template>
    <Head :title="t('saas.admin.plans.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ t('saas.admin.plans.title') }}</h1>
            <Button variant="default" @click="creating = !creating">
                {{ t('saas.admin.plans.create') }}
            </Button>
        </div>

        <Card v-if="creating">
            <CardHeader>
                <CardTitle>{{ t('saas.admin.plans.create') }}</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-3 sm:grid-cols-5 sm:items-end"
                    @submit.prevent="createPlan"
                >
                    <div class="grid gap-1">
                        <Label for="plan-key">{{ t('saas.admin.plans.key') }}</Label>
                        <Input id="plan-key" v-model="newPlan.key" required />
                    </div>
                    <div class="grid gap-1">
                        <Label for="plan-name">{{ t('saas.admin.plans.name') }}</Label>
                        <Input id="plan-name" v-model="newPlan.name" required />
                    </div>
                    <div class="grid gap-1">
                        <Label>{{ t('saas.admin.plans.visibility') }}</Label>
                        <Select v-model="newPlan.visibility">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="public">public</SelectItem>
                                <SelectItem value="private">private</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="plan-sort">{{ t('saas.admin.plans.sortOrder') }}</Label>
                        <Input id="plan-sort" v-model="newPlan.sort_order" type="number" />
                    </div>
                    <Button type="submit">{{ t('saas.admin.plans.save') }}</Button>
                </form>
            </CardContent>
        </Card>

        <Card v-for="plan in props.plans" :key="plan.id">
            <CardHeader>
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            {{ plan.name }}
                            <code class="text-xs text-muted-foreground">{{ plan.key }}</code>
                            <Badge v-if="plan.is_default" variant="secondary">
                                {{ t('saas.plans.default') }}
                            </Badge>
                            <Badge v-if="plan.archived_at" variant="outline">
                                {{ t('saas.admin.plans.archived') }}
                            </Badge>
                        </CardTitle>
                        <CardDescription>
                            {{ t('saas.admin.plans.publishedVersion') }}:
                            {{ publishedOf(plan)?.version ?? t('saas.admin.plans.none') }}
                        </CardDescription>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            @click="startEdit(plan)"
                        >
                            {{ editedPlanId === plan.id ? t('common.actions.close') : t('common.actions.edit') }}
                        </Button>
                        <Button
                            v-if="hasDraft(plan)"
                            size="sm"
                            @click="router.post(publishPlan({ plan: plan.id }).url, {}, { preserveScroll: true })"
                        >
                            {{ t('saas.admin.plans.publish') }}
                        </Button>
                        <Button
                            v-if="!plan.archived_at"
                            size="sm"
                            variant="destructive"
                            @click="router.post(archivePlan({ plan: plan.id }).url, {}, { preserveScroll: true })"
                        >
                            {{ t('saas.admin.plans.archive') }}
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <CardContent v-if="editedPlanId === plan.id" class="flex flex-col gap-6">
                <div class="text-sm font-medium">
                    {{ t('saas.admin.plans.draftEditor') }}
                    <span v-if="draftOf(plan)"> (v{{ draftOf(plan)!.version }})</span>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div>
                        <Label class="mb-2 block">{{ t('saas.admin.plans.features') }}</Label>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="feature in props.capabilities.features"
                                :key="feature.key"
                                class="flex items-center justify-between gap-3 rounded-md border p-3"
                            >
                                <div class="flex flex-col gap-0.5">
                                    <Label :for="`feature-${plan.id}-${feature.key}`" class="text-sm">
                                        {{ feature.label }}
                                    </Label>
                                    <code class="text-xs text-muted-foreground">
                                        {{ feature.key }}
                                    </code>
                                    <p
                                        v-if="feature.module || feature.metric"
                                        class="text-xs text-muted-foreground"
                                    >
                                        <span v-if="feature.module" class="font-mono">
                                            {{ feature.module }}
                                        </span>
                                        <span v-if="feature.module && feature.metric"> · </span>
                                        <span v-if="feature.metric" class="font-mono">
                                            {{ feature.metric }}
                                        </span>
                                    </p>
                                </div>
                                <Switch
                                    :id="`feature-${plan.id}-${feature.key}`"
                                    :checked="Boolean(featureValues[feature.key])"
                                    @update:checked="(value: boolean) => setFeature(feature.key, value)"
                                />
                            </div>
                        </div>
                    </div>

                    <div>
                        <Label class="mb-2 block">{{ t('saas.admin.plans.quotas') }}</Label>
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="quota in props.capabilities.quotas"
                                :key="quota.key"
                                class="flex items-center justify-between gap-3 rounded-md border p-3"
                            >
                                <div class="flex flex-col gap-0.5">
                                    <Label :for="`quota-${plan.id}-${quota.key}`" class="text-sm">
                                        {{ quota.label }}
                                    </Label>
                                    <code class="text-xs text-muted-foreground">
                                        {{ quota.key }}<span v-if="quota.unit"> · {{ quota.unit }}</span>
                                    </code>
                                </div>
                                <Input
                                    :id="`quota-${plan.id}-${quota.key}`"
                                    v-model="quotaValues[quota.key]"
                                    type="number"
                                    min="0"
                                    class="w-32"
                                    :placeholder="t('saas.quotas.unlimited')"
                                />
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ t('saas.admin.plans.quotaHint') }}
                        </p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button @click="saveDraft(plan)">
                        {{ t('saas.admin.plans.saveDraft') }}
                    </Button>
                </div>

                <div v-if="draftOf(plan)" class="border-t pt-4">
                    <div class="mb-2 text-sm font-medium">
                        {{ t('saas.admin.plans.prices') }}
                    </div>
                    <form
                        class="mb-4 flex flex-wrap items-end gap-3"
                        @submit.prevent="addPrice(plan)"
                    >
                        <div class="grid gap-1">
                            <Label>{{ t('saas.admin.plans.currency') }}</Label>
                            <Input v-model="priceRow.currency" class="w-20" maxlength="3" />
                        </div>
                        <div class="grid gap-1">
                            <Label>{{ t('saas.admin.plans.interval') }}</Label>
                            <Select v-model="priceRow.interval">
                                <SelectTrigger class="w-28">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="month">month</SelectItem>
                                    <SelectItem value="year">year</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1">
                            <Label>{{ t('saas.admin.plans.amountCents') }}</Label>
                            <Input v-model="priceRow.amount_cents" type="number" class="w-32" />
                        </div>
                        <Button type="submit" variant="outline">
                            {{ t('saas.admin.plans.savePrice') }}
                        </Button>
                    </form>

                    <Table v-if="draftOf(plan)!.prices.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead>{{ t('saas.admin.plans.currency') }}</TableHead>
                                <TableHead>{{ t('saas.admin.plans.interval') }}</TableHead>
                                <TableHead>{{ t('saas.admin.plans.amountCents') }}</TableHead>
                                <TableHead>{{ t('saas.admin.plans.active') }}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="price in draftOf(plan)!.prices"
                                :key="price.id"
                            >
                                <TableCell>{{ price.currency }}</TableCell>
                                <TableCell>{{ price.interval }}</TableCell>
                                <TableCell>{{ price.amount_cents }}</TableCell>
                                <TableCell>
                                    <Badge :variant="price.active ? 'default' : 'outline'">
                                        {{ price.active }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
