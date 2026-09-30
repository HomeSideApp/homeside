<script setup lang="ts">
import { X, Upload } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    modelValue: File | null;
    currentUrl?: string | null;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: File | null];
}>();

const previewUrl = ref<string | null>(props.currentUrl ?? null);
const fileInputRef = ref<HTMLInputElement | null>(null);

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (file) {
        emit('update:modelValue', file);
        previewUrl.value = URL.createObjectURL(file);
    }
}

function removeImage() {
    emit('update:modelValue', null);
    previewUrl.value = null;

    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function triggerUpload() {
    fileInputRef.value?.click();
}
</script>

<template>
    <div class="space-y-2">
        <input
            ref="fileInputRef"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleFileChange"
        />

        <div v-if="previewUrl" class="relative inline-block">
            <img
                :src="previewUrl"
                alt="Vista previa"
                class="h-40 w-40 rounded-md border object-cover"
            />
            <Button
                type="button"
                variant="destructive"
                size="icon"
                class="absolute -right-2 -top-2 h-6 w-6 rounded-full"
                @click="removeImage"
            >
                <X class="h-3 w-3" />
            </Button>
        </div>

        <Button type="button" variant="outline" size="sm" @click="triggerUpload">
            <Upload class="mr-2 h-4 w-4" />
            {{ previewUrl ? 'Cambiar imagen' : 'Subir imagen' }}
        </Button>

        <InputError :message="error" />
    </div>
</template>
