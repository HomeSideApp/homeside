<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    url: string;
    mimeType: string;
    filename: string;
}>();
const { t } = useI18n();
const zoom = ref(1);
const isPdf = computed(() => props.mimeType === 'application/pdf');
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-2">
            <p class="truncate text-sm font-medium">{{ filename }}</p>
            <div v-if="!isPdf" class="flex gap-1">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :aria-label="t('economy.ui.zoomOut')"
                    @click="zoom = Math.max(0.5, zoom - 0.25)"
                    >−</Button
                ><Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :aria-label="t('economy.ui.zoomIn')"
                    @click="zoom = Math.min(3, zoom + 0.25)"
                    >+</Button
                >
            </div>
        </div>
        <iframe
            v-if="isPdf"
            :src="url"
            :title="filename"
            class="h-[65vh] w-full rounded-lg border"
        />
        <div
            v-else
            class="max-h-[65vh] overflow-auto rounded-lg border bg-muted p-3"
        >
            <img
                :src="url"
                :alt="filename"
                class="mx-auto max-w-none origin-top"
                :style="{ transform: `scale(${zoom})` }"
            />
        </div>
    </div>
</template>
