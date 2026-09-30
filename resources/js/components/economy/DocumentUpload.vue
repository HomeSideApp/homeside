<script setup lang="ts">
import { FileCheck2, FileUp } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';

const file = defineModel<File | null>({ required: true });
const props = defineProps<{
    error?: string;
    disabled?: boolean;
    validate?: () => void;
}>();
const { locale, t } = useI18n();
const localError = ref<string | null>(null);

function selectFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selected = input.files?.[0] ?? null;
    localError.value = null;

    if (selected && selected.size > 10 * 1024 * 1024) {
        localError.value = t('economy.ui.documentTooLarge');
        file.value = null;
        input.value = '';
        props.validate?.();

        return;
    }

    file.value = selected;
    props.validate?.();
}

function formatFileSize(sizeInBytes: number): string {
    return new Intl.NumberFormat(locale.value, {
        maximumFractionDigits: 2,
        minimumFractionDigits: 2,
    }).format(sizeInBytes / 1024 / 1024);
}
</script>

<template>
    <Field :data-invalid="Boolean(props.error || localError)">
        <FieldLabel
            for="economy-document"
            class="flex min-h-40 cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-6 text-center"
            :class="{ 'border-primary bg-primary/5': file }"
        >
            <FileCheck2 v-if="file" class="text-primary" />
            <FileUp v-else />
            <span class="font-medium">
                {{
                    file
                        ? t('economy.ui.documentSelected', { name: file.name })
                        : t('economy.ui.chooseDocument')
                }}
            </span>
            <FieldDescription>
                {{
                    file
                        ? t('economy.ui.documentSize', {
                              size: formatFileSize(file.size),
                          })
                        : t('economy.ui.documentHint')
                }}
            </FieldDescription>
        </FieldLabel>
        <Input
            id="economy-document"
            class="sr-only"
            type="file"
            accept="image/jpeg,image/png,image/webp,image/heic,application/pdf"
            :disabled="props.disabled"
            @change="selectFile"
        />
        <FieldError v-if="props.error || localError">{{
            props.error ?? localError
        }}</FieldError>
    </Field>
</template>
