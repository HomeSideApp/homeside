<script setup lang="ts">
import { Head, Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

defineProps<{
    user: { id: number; name: string; email: string; roles: Array<{ name: string }> };
    roles: Array<{ id: number; name: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Usuarios', href: '/admin/users' }, { title: 'Editar', href: '#' }] },
});
</script>

<template>
    <Head :title="`Editar Usuario: ${user.name}`" />

    <div class="flex flex-col gap-6 px-8 py-6 max-w-2xl">
        <h1 class="text-2xl font-bold">Editar Usuario: {{ user.name }}</h1>

        <Form :action="`/admin/users/${user.id}`" method="POST" v-slot="{ errors, processing, hasErrors, validate }" class="space-y-6">
            <input type="hidden" name="_method" value="PUT">

            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input id="name" name="name" :default-value="user.name" required @blur="validate" @input="validate" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" name="email" type="email" :default-value="user.email" required @blur="validate" />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="role">Rol</Label>
                <Select name="role" :default-value="user.roles[0]?.slug" @value-change="validate">
                    <SelectTrigger>
                        <SelectValue placeholder="Seleccionar rol" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="role in roles" :key="role.id" :value="role.slug">
                            {{ role.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.role" />
            </div>

            <Button :disabled="processing || hasErrors">Guardar</Button>
        </Form>
    </div>
</template>
