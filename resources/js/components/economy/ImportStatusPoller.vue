<script setup lang="ts">
import { usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';

const props = defineProps<{ status: string }>();
const { t } = useI18n();
const label = computed(
    () =>
        ({
            pending: t('economy.ui.statusPending'),
            processing: t('economy.ui.statusProcessing'),
            ready_for_review: t('economy.ui.statusReady'),
            failed: t('economy.ui.statusFailed'),
            confirmed: t('economy.ui.statusConfirmed'),
            discarded: t('economy.ui.statusDiscarded'),
        })[props.status] ?? t('economy.ui.statusUnknown'),
);
const { start, stop } = usePoll(
    2000,
    { only: ['importData'] },
    { autoStart: false },
);

watch(
    () => props.status,
    (status) => (['pending', 'processing'].includes(status) ? start() : stop()),
    { immediate: true },
);
</script>

<template>
    <div class="flex items-center gap-3">
        <Badge variant="secondary">{{ label }}</Badge>
        <Skeleton
            v-if="status === 'pending' || status === 'processing'"
            class="h-2 w-40"
        />
    </div>
</template>
