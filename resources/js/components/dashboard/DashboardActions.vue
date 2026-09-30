<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { FileUp, Plus } from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { create as personalExpenseCreate } from '@/routes/economy/me';
import { create as personalImportCreate } from '@/routes/economy/me/imports';

const props = defineProps<{
    canCreateExpense: boolean;
    canImportTicket: boolean;
}>();

const { t } = useI18n();

type DashboardAction = {
    key: string;
    label: string;
    href: string;
    icon: Component;
    allowed: boolean;
};

/**
 * Primary dashboard actions.
 *
 * The destination is chosen inside the transaction and import forms, so these buttons only open
 * the corresponding screen: there is no longer a selector here deciding the scope.
 */
const actions = computed<(DashboardAction & { allowed: boolean })[]>(() => [
    {
        key: 'expense',
        label: t('dashboard.newExpense'),
        href: personalExpenseCreate.url(),
        icon: Plus,
        allowed: props.canCreateExpense,
    },
    {
        key: 'import',
        label: t('dashboard.importTicket'),
        href: personalImportCreate.url(),
        icon: FileUp,
        allowed: props.canImportTicket,
    },
]);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <template v-for="(action, index) in actions" :key="action.key">
            <Button
                v-if="action.allowed"
                as-child
                :variant="index === 0 ? 'default' : 'outline'"
                size="sm"
            >
                <Link :href="action.href">
                    <component :is="action.icon" data-icon="inline-start" />
                    {{ action.label }}
                </Link>
            </Button>
        </template>
    </div>
</template>
