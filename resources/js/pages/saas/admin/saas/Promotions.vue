<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { ref, watchEffect } from 'vue';
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
    store as storePromotion,
    update as updatePromotion,
} from '@/routes/admin/saas/promotions';

interface PromotionRow {
    id: string;
    key: string;
    type: string;
    code: string | null;
    plan: string | null;
    plan_version: number | null;
    plan_version_id: string;
    starts_at: string | null;
    ends_at: string | null;
    benefit_days: number;
    max_redemptions: number | null;
    per_user_limit: number | null;
    active: boolean;
    redemptions_count: number;
}

const props = defineProps<{
    promotions: PromotionRow[];
    planVersions: Array<{ id: string; label: string }>;
}>();

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({ title: t('saas.admin.promotions.title') });
});

const creating = ref(false);
const editingId = ref<string | null>(null);

const emptyForm = () => ({
    key: '',
    plan_version_id: '',
    code: '',
    starts_at: '',
    ends_at: '',
    benefit_days: 14,
    max_redemptions: '' as number | '',
    per_user_limit: '' as number | '',
    active: true,
});

const form = ref(emptyForm());

function startEdit(promotion: PromotionRow): void {
    if (editingId.value === promotion.id) {
        editingId.value = null;

        return;
    }

    editingId.value = promotion.id;
    form.value = {
        key: promotion.key,
        plan_version_id: promotion.plan_version_id,
        code: promotion.code ?? '',
        starts_at: promotion.starts_at?.slice(0, 10) ?? '',
        ends_at: promotion.ends_at?.slice(0, 10) ?? '',
        benefit_days: promotion.benefit_days,
        max_redemptions: promotion.max_redemptions ?? '',
        per_user_limit: promotion.per_user_limit ?? '',
        active: promotion.active,
    };
}

function submit(): void {
    const payload: Record<string, unknown> = {
        key: form.value.key,
        plan_version_id: form.value.plan_version_id,
        code: form.value.code === '' ? null : form.value.code,
        starts_at: form.value.starts_at,
        ends_at: form.value.ends_at,
        benefit_days: form.value.benefit_days,
        active: form.value.active,
    };

    if (form.value.max_redemptions !== '') {
        payload.max_redemptions = form.value.max_redemptions;
    }

    if (form.value.per_user_limit !== '') {
        payload.per_user_limit = form.value.per_user_limit;
    }

    if (editingId.value !== null) {
        router.put(updatePromotion({ promotion: editingId.value }).url, payload, {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
                form.value = emptyForm();
            },
        });

        return;
    }

    router.post(storePromotion().url, payload, {
        preserveScroll: true,
        onSuccess: () => {
            creating.value = false;
            form.value = emptyForm();
        },
    });
}

function toggleActive(promotion: PromotionRow): void {
    router.put(
        updatePromotion({ promotion: promotion.id }).url,
        {
            key: promotion.key,
            plan_version_id: promotion.plan_version_id,
            code: promotion.code,
            starts_at: promotion.starts_at?.slice(0, 10),
            ends_at: promotion.ends_at?.slice(0, 10),
            benefit_days: promotion.benefit_days,
            max_redemptions: promotion.max_redemptions,
            per_user_limit: promotion.per_user_limit,
            active: !promotion.active,
        },
        { preserveScroll: true },
    );
}

function date(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head :title="t('saas.admin.promotions.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">{{ t('saas.admin.promotions.title') }}</h1>
            <Button
                variant="default"
                @click="
                    creating = !creating;
                    editingId = null;
                    form = emptyForm();
                "
            >
                {{ t('saas.admin.promotions.create') }}
            </Button>
        </div>

        <Card v-if="creating || editingId !== null">
            <CardHeader>
                <CardTitle>
                    {{
                        editingId !== null
                            ? t('saas.admin.promotions.edit')
                            : t('saas.admin.promotions.create')
                    }}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-3" @submit.prevent="submit">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div class="grid gap-1">
                            <Label for="promo-key">{{ t('saas.admin.promotions.key') }}</Label>
                            <Input id="promo-key" v-model="form.key" required />
                        </div>
                        <div class="grid gap-1">
                            <Label>{{ t('saas.admin.promotions.planVersion') }}</Label>
                            <Select v-model="form.plan_version_id">
                                <SelectTrigger>
                                    <SelectValue
                                        :placeholder="t('saas.admin.grants.selectVersion')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="version in planVersions"
                                        :key="version.id"
                                        :value="version.id"
                                    >
                                        {{ version.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1">
                            <Label for="promo-code">{{ t('saas.admin.promotions.code') }}</Label>
                            <Input id="promo-code" v-model="form.code" />
                        </div>
                        <div class="grid gap-1">
                            <Label>{{ t('saas.admin.promotions.active') }}</Label>
                            <Switch v-model="form.active" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-5">
                        <div class="grid gap-1">
                            <Label for="promo-starts">{{ t('saas.admin.promotions.startsAt') }}</Label>
                            <Input id="promo-starts" v-model="form.starts_at" type="date" required />
                        </div>
                        <div class="grid gap-1">
                            <Label for="promo-ends">{{ t('saas.admin.promotions.endsAt') }}</Label>
                            <Input id="promo-ends" v-model="form.ends_at" type="date" required />
                        </div>
                        <div class="grid gap-1">
                            <Label for="promo-days">{{ t('saas.admin.promotions.benefitDays') }}</Label>
                            <Input id="promo-days" v-model="form.benefit_days" type="number" min="1" required />
                        </div>
                        <div class="grid gap-1">
                            <Label for="promo-max">{{ t('saas.admin.promotions.maxRedemptions') }}</Label>
                            <Input id="promo-max" v-model="form.max_redemptions" type="number" min="1" />
                        </div>
                        <div class="grid gap-1">
                            <Label for="promo-per-user">{{ t('saas.admin.promotions.perUserLimit') }}</Label>
                            <Input id="promo-per-user" v-model="form.per_user_limit" type="number" min="1" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="
                                creating = false;
                                editingId = null;
                            "
                        >
                            {{ t('common.actions.cancel') }}
                        </Button>
                        <Button type="submit">{{ t('saas.admin.promotions.save') }}</Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>{{ t('saas.admin.promotions.listTitle') }}</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('saas.admin.promotions.key') }}</TableHead>
                            <TableHead>{{ t('saas.admin.promotions.code') }}</TableHead>
                            <TableHead>{{ t('saas.admin.grants.plan') }}</TableHead>
                            <TableHead>{{ t('saas.admin.promotions.window') }}</TableHead>
                            <TableHead>{{ t('saas.admin.promotions.benefitDays') }}</TableHead>
                            <TableHead>{{ t('saas.admin.promotions.limits') }}</TableHead>
                            <TableHead>{{ t('saas.admin.promotions.redemptions') }}</TableHead>
                            <TableHead>{{ t('saas.admin.promotions.active') }}</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="promotion in promotions" :key="promotion.id">
                            <TableCell class="font-mono text-xs">{{ promotion.key }}</TableCell>
                            <TableCell class="font-mono text-xs">
                                {{ promotion.code ?? '—' }}
                            </TableCell>
                            <TableCell>
                                {{ promotion.plan }} v{{ promotion.plan_version }}
                            </TableCell>
                            <TableCell class="text-xs">
                                {{ date(promotion.starts_at) }} → {{ date(promotion.ends_at) }}
                            </TableCell>
                            <TableCell>{{ promotion.benefit_days }}</TableCell>
                            <TableCell class="text-xs">
                                max: {{ promotion.max_redemptions ?? '∞' }} · user:
                                {{ promotion.per_user_limit ?? '∞' }}
                            </TableCell>
                            <TableCell>{{ promotion.redemptions_count }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="promotion.active ? 'default' : 'outline'"
                                >
                                    {{ promotion.active }}
                                </Badge>
                            </TableCell>
                            <TableCell class="flex gap-1">
                                <Button size="sm" variant="outline" @click="startEdit(promotion)">
                                    {{ t('common.actions.edit') }}
                                </Button>
                                <Button size="sm" variant="ghost" @click="toggleActive(promotion)">
                                    {{ promotion.active
                                        ? t('saas.admin.promotions.deactivate')
                                        : t('saas.admin.promotions.activate') }}
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
