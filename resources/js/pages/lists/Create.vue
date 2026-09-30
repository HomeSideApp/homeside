<script setup lang="ts">
import { Head, Link, Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';

const { t } = useI18n();
const props = defineProps<{
    household: { id: string; name: string } | null;
}>();

const listRoutes = useShoppingListRoutes(
    computed(() => props.household?.id ?? null),
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Listas', href: '/lists' },
            { title: 'Crear', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="t('lists.create.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('lists.create.title')"
            :description="
                t(
                    household
                        ? 'lists.create.description'
                        : 'lists.create.privateDescription',
                )
            "
        />

        <Form
            :action="listRoutes.store()"
            method="POST"
            v-slot="{ errors, processing, hasErrors, validate }"
            class="space-y-6"
        >
            <FieldGroup>
                <Field>
                    <FieldLabel for="name">{{
                        t('lists.create.name')
                    }}</FieldLabel>
                    <Input
                        id="name"
                        name="name"
                        required
                        :placeholder="t('lists.create.namePlaceholder')"
                        @blur="validate"
                        @input="validate"
                    />
                    <InputError :message="errors.name" />
                </Field>
            </FieldGroup>

            <div class="flex gap-2">
                <Button type="submit" :disabled="processing || hasErrors">{{
                    t('common.actions.create')
                }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="listRoutes.index()">{{
                        t('common.actions.cancel')
                    }}</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
