<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ImagePlus, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { EconomyAttachment } from './types';

const { t } = useI18n();

const props = defineProps<{
    attachments: EconomyAttachment[];
    /** URL that receives a new upload as a multipart `image` field. */
    storeUrl: string;
    /** Builds the URL that serves a given attachment image. */
    fileUrlFor: (attachment: EconomyAttachment) => string;
    /** Builds the URL that deletes a given attachment. */
    destroyUrlFor: (attachment: EconomyAttachment) => string;
    max?: number;
    readonly?: boolean;
}>();

const maxAttachments = computed(() => props.max ?? 5);
const pendingDelete = ref<EconomyAttachment | null>(null);
const lightbox = ref<EconomyAttachment | null>(null);
const input = ref<HTMLInputElement | null>(null);

const canUpload = computed(
    () => !props.readonly && props.attachments.length < maxAttachments.value,
);

function onSelected(event: Event): void {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (!file) {
        return;
    }

    const form = new FormData();
    form.append('image', file);

    router.post(props.storeUrl, form, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            if (input.value) {
                input.value.value = '';
            }
        },
    });
}

function confirmDelete(): void {
    if (!pendingDelete.value) {
        return;
    }

    router.delete(props.destroyUrlFor(pendingDelete.value), {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    });
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <p class="text-sm font-medium">{{ t('economy.attachments.title') }}</p>
        <div class="flex flex-wrap gap-3">
            <div
                v-for="attachment in attachments"
                :key="attachment.id"
                class="group relative size-24 overflow-hidden rounded-lg border"
            >
                <img
                    :src="fileUrlFor(attachment)"
                    :alt="attachment.original_filename"
                    class="size-full object-cover"
                    loading="lazy"
                />
                <button
                    type="button"
                    class="absolute inset-0 cursor-zoom-in"
                    :aria-label="t('economy.attachments.open')"
                    @click="lightbox = attachment"
                />
                <Button
                    v-if="!readonly && attachment.is_mine"
                    variant="destructive"
                    size="icon-sm"
                    class="absolute right-1 top-1"
                    :aria-label="t('economy.attachments.remove')"
                    @click.stop="pendingDelete = attachment"
                >
                    <Trash2 aria-hidden="true" />
                </Button>
            </div>
        </div>

        <div v-if="canUpload" class="flex items-center gap-2">
            <input
                ref="input"
                type="file"
                accept="image/*"
                class="sr-only"
                @change="onSelected"
            />
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="input?.click()"
                        >
                            <ImagePlus data-icon="inline-start" aria-hidden="true" />
                            {{ t('economy.attachments.add') }}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>{{
                        t('economy.attachments.limit', {
                            max: maxAttachments,
                        })
                    }}</TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <span class="text-xs text-muted-foreground">
                {{ attachments.length }}/{{ maxAttachments }}
            </span>
        </div>

        <AlertDialog
            :open="pendingDelete !== null"
            @update:open="pendingDelete = null"
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        t('economy.attachments.removeConfirmTitle')
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        t('economy.attachments.removeConfirmDetail', {
                            name: pendingDelete?.original_filename,
                        })
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('economy.ui.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete">{{
                        t('economy.attachments.remove')
                    }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <div
            v-if="lightbox"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-6"
            role="dialog"
            aria-modal="true"
            @click.self="lightbox = null"
        >
            <Button
                variant="secondary"
                size="icon-sm"
                class="absolute right-6 top-6"
                :aria-label="t('economy.ui.close')"
                @click="lightbox = null"
            >
                <X aria-hidden="true" />
            </Button>
            <img
                :src="fileUrlFor(lightbox)"
                :alt="lightbox.original_filename"
                class="max-h-full max-w-full object-contain"
            />
        </div>
    </div>
</template>
