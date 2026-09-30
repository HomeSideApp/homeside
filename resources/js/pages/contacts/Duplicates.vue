<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft, Merge } from '@lucide/vue';
import { computed, ref } from 'vue';
import IconAction from '@/components/admin/IconAction.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { index, show } from '@/routes/contacts';
import { store } from '@/routes/contacts/duplicates';

interface DuplicateGroup {
    primary: { id: string; name: string };
    duplicates: Array<{ id: string; name: string }>;
    matches: string[];
}
const props = defineProps<{ groups: DuplicateGroup[] }>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Contactos', href: index().url },
        { title: 'Duplicados' },
    ],
});

const selected = ref<string[]>([]);
const confirmationOpen = ref(false);
const processing = ref(false);
const selectedGroups = computed(() =>
    props.groups.filter((group) => selected.value.includes(group.primary.id)),
);
const selectedCount = computed(() =>
    selectedGroups.value.reduce(
        (total, group) => total + group.duplicates.length,
        0,
    ),
);

function toggle(id: string, checked: boolean): void {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((item) => item !== id);
}

function confirmMerge(): void {
    if (!selectedGroups.value.length) {
        return;
    }

    processing.value = true;
    router.post(
        store().url,
        {
            groups: selectedGroups.value.map((group) => ({
                primary_id: group.primary.id,
                duplicate_ids: group.duplicates.map((item) => item.id),
            })),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                confirmationOpen.value = false;
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Duplicados de contactos" />
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-6 sm:px-8">
        <div class="flex items-center justify-between gap-4">
            <IconAction
                label="Volver a contactos"
                :href="index().url"
                variant="outline"
            >
                <ArrowLeft aria-hidden="true" />
            </IconAction>
            <Button
                v-if="groups.length"
                :disabled="!selectedGroups.length || processing"
                @click="confirmationOpen = true"
            >
                <Merge data-icon="inline-start" />
                Confirmar {{ selectedCount }}
                {{ selectedCount === 1 ? 'fusión' : 'fusiones' }}
            </Button>
        </div>

        <div>
            <h1 class="text-3xl font-semibold tracking-tight">
                Posibles duplicados
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Coincidencias por correo o teléfono dentro de la misma libreta.
                Revisa cada propuesta antes de fusionarla. Los datos locales y
                todas las fuentes se conservarán.
            </p>
        </div>

        <Card v-if="groups.length === 0">
            <CardHeader>
                <CardTitle>No hay coincidencias pendientes</CardTitle>
                <CardDescription>
                    Los contactos con el mismo correo o teléfono aparecerán aquí
                    para revisarlos.
                </CardDescription>
            </CardHeader>
        </Card>

        <Card v-for="group in groups" :key="group.primary.id">
            <CardHeader>
                <CardTitle class="flex items-center gap-3">
                    <Checkbox
                        :model-value="selected.includes(group.primary.id)"
                        :aria-label="`Seleccionar la fusión de ${group.primary.name}`"
                        @update:model-value="
                            (checked) =>
                                toggle(group.primary.id, checked === true)
                        "
                    />
                    <Link
                        :href="show(group.primary.id).url"
                        class="hover:underline"
                    >
                        {{ group.primary.name }}
                    </Link>
                    <Badge variant="secondary">Se conserva</Badge>
                </CardTitle>
                <CardDescription>
                    {{ group.duplicates.length }}
                    {{
                        group.duplicates.length === 1
                            ? 'contacto coincidente'
                            : 'contactos coincidentes'
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-3">
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="match in group.matches"
                        :key="match"
                        variant="outline"
                    >
                        {{ match }}
                    </Badge>
                </div>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="duplicate in group.duplicates"
                        :key="duplicate.id"
                    >
                        <Link
                            :href="show(duplicate.id).url"
                            class="text-sm hover:underline"
                        >
                            {{ duplicate.name }}
                        </Link>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>

    <AlertDialog v-model:open="confirmationOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle
                    >Confirmar fusión de contactos</AlertDialogTitle
                >
                <AlertDialogDescription>
                    Se fusionarán {{ selectedCount }}
                    {{ selectedCount === 1 ? 'contacto' : 'contactos' }} en
                    {{ selectedGroups.length }}
                    {{ selectedGroups.length === 1 ? 'grupo' : 'grupos' }}. Sus
                    fuentes, etiquetas y vínculos se conservarán. Esta acción no
                    se puede deshacer automáticamente.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction :disabled="processing" @click="confirmMerge">
                    Fusionar
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
