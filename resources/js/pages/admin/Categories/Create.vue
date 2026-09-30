<script setup lang="ts">
import { Head, Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const { t } = useI18n();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Categorías', href: '/admin/categories' }, { title: 'Crear', href: '#' }] },
});

const colorValue = ref('#3b82f6');
</script>

<template>
    <Head :title="t('admin.categories.createTitle')" />

    <div class="flex flex-col gap-6 px-8 py-6 max-w-2xl">
        <h1 class="text-2xl font-bold">{{ t('admin.categories.createTitle') }}</h1>

        <Form action="/admin/categories" method="POST" v-slot="{ errors, processing, hasErrors, validate }" class="space-y-6">
            <div class="grid gap-2">
                <Label for="name">{{ t('admin.categories.name') }}</Label>
                <Input id="name" name="name" required :placeholder="t('admin.categories.namePlaceholder')" @blur="validate" @input="validate" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="color">{{ t('admin.categories.color') }}</Label>
                <InputError :message="errors.color" />
                <div class="flex items-center gap-2">
                    <input id="color" name="color" type="color" :value="colorValue" @input="colorValue = ($event.target as HTMLInputElement).value" class="flex h-10 w-16 rounded-md border border-input bg-background px-1 py-1 ring-offset-background file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" required />
                    <Input :model-value="colorValue" class="flex-1" disabled />
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked class="h-4 w-4">
                <Label for="is_active">{{ t('admin.categories.active') }}</Label>
            </div>

            <Button :disabled="processing || hasErrors">{{ t('admin.categories.createTitle') }}</Button>
        </Form>
    </div>
</template>
