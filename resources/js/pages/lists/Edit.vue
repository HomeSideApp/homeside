<script setup lang="ts">
import { Head, Link, Form, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useShoppingListRoutes } from '@/composables/useShoppingListRoutes';

const { t } = useI18n();
const props = defineProps<{
    list: {
        id: string;
        name: string;
    };
    household: { id: string; name: string } | null;
}>();

const listRoutes = useShoppingListRoutes(
    computed(() => props.household?.id ?? null),
);

const deleteForm = useForm({});

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Listas', href: '/lists' },
            { title: 'Editar', href: '#' },
        ],
    },
});

function deleteList() {
    deleteForm.delete(listRoutes.destroy(props.list.id));
}
</script>

<template>
    <Head :title="t('lists.edit.title', { name: list.name })" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('lists.edit.title', { name: list.name })"
            :description="t('lists.edit.description')"
        />

        <Form
            :action="listRoutes.update(list.id)"
            method="PUT"
            v-slot="{ errors, processing, hasErrors, validate }"
            class="space-y-6"
        >
            <FieldGroup>
                <Field>
                    <FieldLabel for="name">{{
                        t('lists.edit.name')
                    }}</FieldLabel>
                    <Input
                        id="name"
                        name="name"
                        :default-value="list.name"
                        required
                        :placeholder="t('lists.edit.namePlaceholder')"
                        @blur="validate"
                        @input="validate"
                    />
                    <InputError :message="errors.name" />
                </Field>
            </FieldGroup>

            <div class="flex gap-2">
                <Button type="submit" :disabled="processing || hasErrors">{{
                    t('common.actions.save')
                }}</Button>
                <Button variant="outline" as-child>
                    <Link :href="listRoutes.show(list.id)">{{
                        t('common.actions.cancel')
                    }}</Link>
                </Button>

                <div class="ml-auto">
                    <AlertDialog>
                        <AlertDialogTrigger as-child>
                            <Button variant="destructive">{{
                                t('lists.edit.delete')
                            }}</Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>{{
                                    t('lists.edit.deleteTitle')
                                }}</AlertDialogTitle>
                                <AlertDialogDescription>
                                    {{
                                        t('lists.edit.deleteDescription', {
                                            name: list.name,
                                        })
                                    }}
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>{{
                                    t('common.actions.cancel')
                                }}</AlertDialogCancel>
                                <AlertDialogAction @click="deleteList">
                                    {{ t('lists.edit.delete') }}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </div>
        </Form>
    </div>
</template>
