<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, Clock } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as personalImportsIndex } from '@/routes/economy/me/imports';
import { index as householdImportsIndex } from '@/routes/households/economy/imports';

const props = defineProps<{
    pending: {
        personal: number;
        household: number;
        failed_personal: number;
        failed_household: number;
    };
    householdId: string | null;
}>();

const { t } = useI18n();

type DashboardAlert = {
    key: string;
    tone: 'warning' | 'destructive';
    count: number;
    label: string;
    href: string;
};

/**
 * Build the import alerts for both scopes, skipping the ones without anything to report.
 */
const alerts = computed<DashboardAlert[]>(() => {
    const items: DashboardAlert[] = [];
    const householdId = props.householdId;

    if (props.pending.failed_household > 0 && householdId) {
        items.push({
            key: 'failed-household',
            tone: 'destructive',
            count: props.pending.failed_household,
            label: t('dashboard.failedImportsHousehold'),
            href: householdImportsIndex.url(householdId),
        });
    }

    if (props.pending.failed_personal > 0) {
        items.push({
            key: 'failed-personal',
            tone: 'destructive',
            count: props.pending.failed_personal,
            label: t('dashboard.failedImportsPersonal'),
            href: personalImportsIndex.url(),
        });
    }

    if (props.pending.household > 0 && householdId) {
        items.push({
            key: 'pending-household',
            tone: 'warning',
            count: props.pending.household,
            label: t('dashboard.pendingImportsHousehold'),
            href: householdImportsIndex.url(householdId),
        });
    }

    if (props.pending.personal > 0) {
        items.push({
            key: 'pending-personal',
            tone: 'warning',
            count: props.pending.personal,
            label: t('dashboard.pendingImportsPersonal'),
            href: personalImportsIndex.url(),
        });
    }

    return items;
});
</script>

<template>
    <div v-if="alerts.length" class="flex flex-col gap-2">
        <div
            v-for="alert in alerts"
            :key="alert.key"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-4 py-3 text-sm"
            :class="
                alert.tone === 'destructive'
                    ? 'border-destructive/40 bg-destructive/5 text-destructive'
                    : 'border-warning bg-warning/40 text-warning-foreground'
            "
        >
            <span class="flex items-center gap-2">
                <AlertTriangle
                    v-if="alert.tone === 'destructive'"
                    class="size-4"
                />
                <Clock v-else class="size-4" />
                <span>{{ alert.label }}</span>
                <Badge variant="secondary">{{ alert.count }}</Badge>
            </span>
            <Button as-child variant="ghost" size="sm">
                <Link :href="alert.href">
                    {{ t('dashboard.reviewImports') }}
                </Link>
            </Button>
        </div>
    </div>
</template>
