<script setup lang="ts">
import { Head, Form } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

defineProps<{
    roles: Array<{ id: number; name: string }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Usuarios', href: '/admin/users' }, { title: 'Crear', href: '#' }] },
});
</script>

<template>
    <Head title="Crear Usuario" />

    <div class="flex flex-col gap-6 px-8 py-6 max-w-2xl">
        <h1 class="text-2xl font-bold">Crear Usuario</h1>

        <Form action="/admin/users" method="POST" v-slot="{ errors, processing, hasErrors, validate }" class="space-y-6">
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input id="name" name="name" required @blur="validate" @input="validate" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input id="email" name="email" type="email" required @blur="validate" />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="role">Rol</Label>
                <Select name="role" @value-change="validate">
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

            <Button :disabled="processing || hasErrors">Crear</Button>
        </Form>
    </div>
</template>
