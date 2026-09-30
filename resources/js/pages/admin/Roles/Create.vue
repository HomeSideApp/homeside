<script setup lang="ts">
import { Head, Form } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Permission {
    id: string;
    name: string;
    group: string;
}

const props = defineProps<{
    permissions: Permission[];
}>();

// Estado reactivo de permisos: todos desactivados por defecto
const checkedState = reactive<Record<string, boolean>>(
    Object.fromEntries(props.permissions.map((perm) => [perm.name, false])),
);

// Permisos agrupados por grupo
const groupedPermissions = computed(() => {
    return props.permissions.reduce<Record<string, Permission[]>>((acc, perm) => {
        (acc[perm.group] ??= []).push(perm);

        return acc;
    }, {});
});

// Nombres de los grupos
const groupNames = computed(() => Object.keys(groupedPermissions.value));

// ¿Están todos los permisos de un grupo activados?
function isGroupFullyChecked(group: string): boolean {
    const perms = groupedPermissions.value[group];

    return perms.every((p) => checkedState[p.name]);
}

// ¿Están algunos (pero no todos) activados?
function isGroupIndeterminate(group: string): boolean {
    const perms = groupedPermissions.value[group];
    const checked = perms.filter((p) => checkedState[p.name]).length;

    return checked > 0 && checked < perms.length;
}

// Toggle todos los permisos de un grupo
function toggleGroup(group: string, checked: boolean): void {
    const perms = groupedPermissions.value[group];
    perms.forEach((p) => {
        checkedState[p.name] = checked;
    });
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles', href: '/admin/roles' },
            { title: 'Crear', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Crear Rol" />

    <div class="flex flex-col gap-6 px-8 py-6 max-w-3xl">
        <h1 class="text-2xl font-bold">Crear Rol</h1>

        <Form action="/admin/roles" method="POST" v-slot="{ errors, processing, hasErrors, validate }" class="space-y-6">
            <!-- Hidden inputs para permisos activados -->
            <template v-for="(checked, name) in checkedState" :key="'hp-' + name">
                <input v-if="checked" type="hidden" name="permissions[]" :value="name" />
            </template>

            <!-- Nombre del rol -->
            <div class="grid gap-2">
                <Label for="name">Nombre del Rol</Label>
                <Input
                    id="name"
                    name="name"
                    required
                    placeholder="Ej: editor"
                    @blur="validate"
                    @input="validate"
                />
                <InputError :message="errors.name" />
            </div>

            <!-- Permisos agrupados -->
            <div class="flex flex-col gap-4">
                <Card v-for="group in groupNames" :key="group">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-lg capitalize">{{ group }}</CardTitle>
                        <div class="flex items-center gap-2">
                            <Checkbox
                                :id="`group-${group}`"
                                :model-value="isGroupIndeterminate(group) ? 'indeterminate' : isGroupFullyChecked(group)"
                                @update:model-value="toggleGroup(group, $event === true)"
                            />
                            <Label :for="`group-${group}`" class="text-sm text-muted-foreground cursor-pointer">
                                Seleccionar todo
                            </Label>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-3">
                            <div
                                v-for="perm in groupedPermissions[group]"
                                :key="perm.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`perm-${perm.id}`"
                                    v-model="checkedState[perm.name]"
                                />
                                <Label :for="`perm-${perm.id}`" class="text-sm cursor-pointer">
                                    {{ perm.name }}
                                </Label>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <InputError :message="errors.permissions" />

            <Button type="submit" :disabled="processing || hasErrors">Crear Rol</Button>
        </Form>
    </div>
</template>
