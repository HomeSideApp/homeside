<script setup lang="ts">
import { Head, Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const { t } = useI18n();

const props = defineProps<{
    category: { id: string; name: string; color: string; is_active: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Categorías', href: '/admin/categories' }, { title: 'Editar', href: '#' }] },
});

const colorValue = ref(props.category.color);
</script>

<template>
    <Head :title="t('admin.categories.editTitle', { name: category.name })" />

    <div class="flex flex-col gap-6 px-8 py-6 max-w-2xl">
        <h1 class="text-2xl font-bold">{{ t('admin.categories.editTitle', { name: category.name }) }}</h1>

        <Form :action="`/admin/categories/${category.id}`" method="POST" v-slot="{ errors, processing, hasErrors, validate }" class="space-y-6">
            <input type="hidden" name="_method" value="PUT">

            <div class="grid gap-2">
                <Label for="name">{{ t('admin.categories.name') }}</Label>
                <Input id="name" name="name" :default-value="category.name" required @blur="validate" @input="validate" />
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
                <input type="checkbox" name="is_active" value="1" :checked="category.is_active" class="h-4 w-4">
                <Label for="is_active">{{ t('admin.categories.active') }}</Label>
            </div>

            <Button :disabled="processing || hasErrors">{{ t('common.actions.save') }}</Button>
        </Form>
    </div>
</template>
