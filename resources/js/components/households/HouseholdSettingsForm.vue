<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { avatarFallbackColors } from '@/lib/avatarFallbackColors';

const { t } = useI18n();

const props = defineProps<{
    form: {
        name: string;
        description: string;
        color: string;
        image: File | null;
        errors: Record<string, string>;
    };
    imagePreview: string | null;
    imageRemoved: boolean;
    showName?: boolean;
}>();

const form = toRef(props, 'form');

const emit = defineEmits<{
    (e: 'update:imagePreview', value: string | null): void;
    (e: 'update:imageRemoved', value: boolean): void;
}>();

function onImageChange(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (file) {
        form.value.image = file;
        emit('update:imageRemoved', false);
        emit('update:imagePreview', URL.createObjectURL(file));
    }
}

function removeImage() {
    form.value.image = null;
    emit('update:imageRemoved', true);
    emit('update:imagePreview', null);
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('households.form.basicInfo') }}</CardTitle>
            <CardDescription>{{ t('households.form.basicInfoDescription') }}</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4">
            <Field v-if="showName !== false" :label="t('households.form.name')" :error="form.errors.name">
                <Input
                    v-model="form.name"
                    :placeholder="t('households.form.namePlaceholder')"
                    :class="{ 'border-destructive': form.errors.name }"
                />
            </Field>

            <Field :label="t('households.form.description')" :error="form.errors.description">
                <Textarea
                    v-model="form.description"
                    :placeholder="t('households.form.descriptionPlaceholder')"
                    :class="{ 'border-destructive': form.errors.description }"
                />
            </Field>

            <div>
                <Label>{{ t('households.form.color') }}</Label>
                <p class="text-sm text-muted-foreground mb-2">{{ t('households.form.colorHint') }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="color in avatarFallbackColors"
                        :key="color"
                        type="button"
                        class="size-8 rounded-full border-2 transition-all hover:scale-110"
                        :class="form.color === color ? 'border-foreground scale-110' : 'border-transparent'"
                        :style="{ backgroundColor: color }"
                        @click="form.color = form.color === color ? '' : color"
                    />
                    <div class="relative size-8">
                        <input
                            type="color"
                            :value="form.color || '#3b82f6'"
                            class="absolute inset-0 size-8 cursor-pointer rounded-full border-2 border-dashed border-muted-foreground/50 opacity-0"
                            @input="(e: Event) => form.color = (e.target as HTMLInputElement).value"
                        />
                        <div class="flex size-8 items-center justify-center rounded-full border-2 border-dashed border-muted-foreground/50">
                            <span class="text-xs">+</span>
                        </div>
                    </div>
                </div>
                <p v-if="form.errors.color" class="mt-1 text-sm text-destructive">
                    {{ form.errors.color }}
                </p>
            </div>

            <div>
                <Label>{{ t('households.form.image') }}</Label>
                <div class="mt-2 flex items-center gap-4">
                    <div
                        v-if="imagePreview"
                        class="relative h-24 w-24 overflow-hidden rounded-lg border"
                    >
                        <img
                            :src="imagePreview"
                            alt="Preview"
                            class="h-full w-full object-cover"
                        />
                        <button
                            type="button"
                            @click="removeImage"
                            class="absolute right-1 top-1 rounded-full bg-background/80 p-0.5 hover:bg-background"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>
                    <label
                        class="flex h-24 w-24 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed text-muted-foreground hover:border-primary hover:text-primary"
                    >
                        <ImagePlus class="mb-1 h-6 w-6" />
                        <span class="text-xs">{{ t('households.form.uploadImage') }}</span>
                        <input
                            type="file"
                            accept="image/*"
                            class="hidden"
                            @change="onImageChange"
                        />
                    </label>
                </div>
                <p v-if="form.errors.image" class="mt-1 text-sm text-destructive">
                    {{ form.errors.image }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
