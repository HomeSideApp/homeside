<script setup lang="ts">
import { Head, Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuthorization } from '@/composables/useAuthorization';
import { store } from '@/routes/admin/users';

defineProps<{
    roles: Array<{ id: string; name: string; slug: string }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Usuarios', href: '/admin/users' },
            { title: 'Crear', href: '#' },
        ],
    },
});
const { t } = useI18n();
const { can } = useAuthorization();
const selectedRoles = ref<string[]>(['user']);
const canCreateHouseholds = ref(true);
function toggleRole(slug: string, checked: unknown): void {
    selectedRoles.value = checked
        ? [...selectedRoles.value, slug]
        : selectedRoles.value.filter((value) => value !== slug);
}
</script>

<template>
    <Head title="Crear Usuario" />

    <div class="flex max-w-2xl flex-col gap-6 px-8 py-6">
        <h1 class="text-2xl font-bold">Crear Usuario</h1>

        <Form
            :action="store.url()"
            method="POST"
            v-slot="{ errors, processing, hasErrors, validate }"
            class="space-y-6"
        >
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    @blur="validate"
                    @input="validate"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    required
                    @blur="validate"
                />
                <InputError :message="errors.email" />
            </div>

            <div v-if="can('admin.users.assign-roles')" class="grid gap-3">
                <Label>{{ t('households.access.platformRoles') }}</Label>
                <input type="hidden" name="roles[]" value="user" />
                <template
                    v-for="role in roles.filter((item) => item.slug !== 'user')"
                    :key="role.id"
                >
                    <Label class="flex items-center gap-2"
                        ><Checkbox
                            :model-value="selectedRoles.includes(role.slug)"
                            @update:model-value="toggleRole(role.slug, $event)"
                        />{{ role.name }}</Label
                    >
                    <input
                        v-if="selectedRoles.includes(role.slug)"
                        type="hidden"
                        name="roles[]"
                        :value="role.slug"
                    />
                </template>
                <InputError :message="errors.roles" />
                <Label class="flex items-center gap-2"
                    ><Checkbox v-model="canCreateHouseholds" />{{
                        t('households.access.canCreateHouseholds')
                    }}</Label
                >
                <input
                    type="hidden"
                    name="can_create_households"
                    :value="canCreateHouseholds ? '1' : '0'"
                />
            </div>

            <Button :disabled="processing || hasErrors">Crear</Button>
        </Form>
    </div>
</template>
