<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import IconAction from '@/components/admin/IconAction.vue';
import ContactAvatar from '@/components/contacts/ContactAvatar.vue';
import ContactFormFields from '@/components/contacts/ContactFormFields.vue';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { index, show, update } from '@/routes/contacts';
import { update as updateLabels } from '@/routes/contacts/labels';
import type {
    ContactFormData,
    ContactRecordData,
    ContactRelation,
} from '@/types/contacts';

interface Contact {
    id: string;
    type: 'person' | 'organization';
    display_name: string;
    household_id: string | null;
    avatar_url: string | null;
    can_remove_photo: boolean;
    records: ContactRecordData[];
    labels: Array<{
        id: string;
        name: string;
        manual: boolean;
        imported: boolean;
    }>;
}

const props = defineProps<{
    contact: Contact;
    labels: Array<{ id: string; name: string }>;
    sources: Array<{ id: string; name: string }>;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Contactos', href: index().url },
        { title: props.contact.display_name, href: show(props.contact.id).url },
        { title: 'Editar' },
    ],
});

const selectedRecord = computed(
    () =>
        props.contact.records.find((record) => record.source_id === null) ??
        props.contact.records[0],
);
const relationTypes = [
    'parent',
    'child',
    'sibling',
    'spouse',
    'friend',
    'colleague',
    'emergency',
    'other',
];
const form = useForm<ContactFormData>({
    household_id: props.contact.household_id,
    type: props.contact.type,
    display_name: props.contact.display_name,
    given_name: selectedRecord.value?.given_name ?? '',
    family_name: selectedRecord.value?.family_name ?? '',
    additional_name: selectedRecord.value?.additional_name ?? '',
    nickname: selectedRecord.value?.nickname ?? '',
    organization: selectedRecord.value?.organization ?? '',
    job_title: selectedRecord.value?.job_title ?? '',
    birthday: selectedRecord.value?.birthday ?? '',
    notes: selectedRecord.value?.notes ?? '',
    emails: selectedRecord.value?.emails.map((value) => ({ ...value })) ?? [],
    phones: selectedRecord.value?.phones.map((value) => ({ ...value })) ?? [],
    addresses:
        selectedRecord.value?.addresses.map((value) => ({ ...value })) ?? [],
    urls: selectedRecord.value?.urls.map((value) => ({ ...value })) ?? [],
    dates:
        selectedRecord.value?.dates
            .filter(
                (date) =>
                    date.value_type === 'date' &&
                    (date.kind === 'anniversary' || date.kind === 'custom'),
            )
            .map((date) => ({ ...date })) ?? [],
    relations:
        selectedRecord.value?.relations
            .filter((relation) => relation.name)
            .map((relation) => ({
                type: relationTypes.includes(relation.type)
                    ? (relation.type as ContactRelation['type'])
                    : 'other',
                related_contact_id: relation.related_contact_id,
                name: relation.name,
            })) ?? [],
    photo: null,
    remove_photo: false,
});
const contactFields = ref(form);
const photoInput = ref<HTMLInputElement | null>(null);
const photoPreview = ref<string | null>(null);
const shownPhoto = computed(
    () =>
        photoPreview.value ||
        (!form.remove_photo ? props.contact.avatar_url : null),
);
const labelsOpen = ref(false);
const labelForm = useForm({
    label_ids: props.contact.labels
        .filter(
            (label) =>
                label.manual &&
                props.labels.some((available) => available.id === label.id),
        )
        .map((label) => label.id),
});
const origins = computed(() => {
    const sourceNames = new Map(
        props.sources.map((source) => [source.id, source.name]),
    );

    return [
        ...new Set(
            props.contact.records.map((record) =>
                record.source_id
                    ? sourceNames.get(record.source_id) || 'Fuente externa'
                    : 'Contacto local',
            ),
        ),
    ];
});

function choosePhoto(): void {
    photoInput.value?.click();
}

function clearPhotoPreview(): void {
    if (photoPreview.value) {
        URL.revokeObjectURL(photoPreview.value);
        photoPreview.value = null;
    }
}

function setPhoto(event: Event): void {
    clearPhotoPreview();
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.photo = file;

    if (file) {
        form.remove_photo = false;
        photoPreview.value = URL.createObjectURL(file);
    }
}

function cancelNewPhoto(): void {
    clearPhotoPreview();
    form.photo = null;

    if (photoInput.value) {
        photoInput.value.value = '';
    }
}

function toggleRemovePhoto(): void {
    form.remove_photo = !form.remove_photo;
}

onUnmounted(clearPhotoPreview);

function toggleLabel(id: string, checked: boolean): void {
    labelForm.label_ids = checked
        ? [...labelForm.label_ids, id]
        : labelForm.label_ids.filter((labelId) => labelId !== id);
}

function saveLabels(): void {
    labelForm.put(updateLabels(props.contact.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            labelsOpen.value = false;
        },
    });
}

function save(): void {
    form.transform((data) => ({
        ...Object.fromEntries(
            Object.entries(data).filter(([key]) => key !== 'household_id'),
        ),
        _method: 'put',
        emails: data.emails.filter((item) => item.value.trim()),
        phones: data.phones.filter((item) => item.value.trim()),
        addresses: data.addresses.filter((item) => item.value.trim()),
        urls: data.urls.filter((item) => item.value.trim()),
    })).post(update(props.contact.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Editar ${contact.display_name}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-8">
        <div class="flex items-center justify-between gap-4">
            <IconAction
                label="Volver al contacto"
                :href="show(contact.id).url"
                variant="outline"
            >
                <ArrowLeft aria-hidden="true" />
            </IconAction>
            <Button
                type="submit"
                form="contact-edit-form"
                :disabled="form.processing || !form.isDirty"
            >
                Guardar
            </Button>
        </div>

        <header class="flex flex-col gap-5 sm:flex-row sm:items-center">
            <div class="relative w-fit">
                <ContactAvatar
                    :id="contact.id"
                    :name="form.display_name || contact.display_name"
                    :avatar-url="shownPhoto"
                    size="large"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    class="absolute -right-1 -bottom-1 rounded-full bg-background"
                    aria-label="Cambiar foto del contacto"
                    @click="choosePhoto"
                >
                    <Pencil aria-hidden="true" />
                </Button>
                <input
                    ref="photoInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                    aria-label="Seleccionar foto del contacto"
                    @change="setPhoto"
                />
            </div>
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">Editar contacto</p>
                <h1 class="text-3xl font-semibold tracking-tight break-words">
                    {{ form.display_name || contact.display_name }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        contact.household_id
                            ? 'Contacto del hogar'
                            : 'Contacto privado'
                    }}
                </p>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                    <Button
                        v-if="form.photo"
                        type="button"
                        variant="link"
                        size="sm"
                        class="h-auto p-0"
                        @click="cancelNewPhoto"
                    >
                        Cancelar nueva foto
                    </Button>
                    <Button
                        v-else-if="contact.can_remove_photo"
                        type="button"
                        variant="link"
                        size="sm"
                        class="h-auto p-0"
                        @click="toggleRemovePhoto"
                    >
                        {{
                            form.remove_photo ? 'Conservar foto' : 'Quitar foto'
                        }}
                    </Button>
                    <span class="text-xs text-muted-foreground"
                        >JPEG, PNG o WebP · máximo 3 MB</span
                    >
                </div>
                <p
                    v-if="form.errors.photo"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.photo }}
                </p>
            </div>
        </header>

        <div class="flex max-w-2xl flex-col gap-6">
            <div class="flex flex-wrap items-center gap-2 border-b pb-6">
                <Badge
                    v-for="label in contact.labels"
                    :key="label.id"
                    variant="secondary"
                >
                    {{ label.name }}
                </Badge>
                <span
                    v-if="contact.labels.length === 0"
                    class="text-sm text-muted-foreground"
                    >Sin etiquetas</span
                >
                <IconAction
                    label="Editar etiquetas"
                    variant="outline"
                    @click="labelsOpen = true"
                >
                    <Pencil aria-hidden="true" />
                </IconAction>
            </div>

            <form
                id="contact-edit-form"
                class="flex flex-col gap-6"
                @submit.prevent="save"
            >
                <p class="text-sm text-muted-foreground">
                    Los cambios se guardan en un registro local y no modifican
                    la fuente externa.
                </p>
                <ContactFormFields v-model="contactFields" editing hide-photo />
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" as-child>
                        <Link :href="show(contact.id).url">Cancelar</Link>
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.isDirty"
                        >Guardar cambios</Button
                    >
                </div>
            </form>

            <Card>
                <CardHeader>
                    <CardTitle>Fuentes</CardTitle>
                    <CardDescription
                        >Origen de la información de este
                        contacto.</CardDescription
                    >
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Badge
                        v-for="origin in origins"
                        :key="origin"
                        variant="outline"
                        >{{ origin }}</Badge
                    >
                    <span
                        v-if="origins.length === 0"
                        class="text-sm text-muted-foreground"
                        >Sin fuentes disponibles.</span
                    >
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="labelsOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar etiquetas</DialogTitle>
                    <DialogDescription
                        >Selecciona las etiquetas personales de este
                        contacto.</DialogDescription
                    >
                </DialogHeader>
                <form class="flex flex-col gap-5" @submit.prevent="saveLabels">
                    <FieldGroup v-if="labels.length" class="gap-3">
                        <Field
                            v-for="label in labels"
                            :key="label.id"
                            orientation="horizontal"
                            class="items-center"
                        >
                            <Checkbox
                                :id="`contact-label-${label.id}`"
                                :model-value="
                                    labelForm.label_ids.includes(label.id)
                                "
                                @update:model-value="
                                    toggleLabel(label.id, $event === true)
                                "
                            />
                            <FieldLabel :for="`contact-label-${label.id}`">{{
                                label.name
                            }}</FieldLabel>
                        </Field>
                    </FieldGroup>
                    <p v-else class="text-sm text-muted-foreground">
                        Crea una etiqueta desde la lista de contactos para
                        asignarla aquí.
                    </p>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="labelsOpen = false"
                            >Cancelar</Button
                        >
                        <Button
                            type="submit"
                            :disabled="labelForm.processing || !labels.length"
                            >Guardar etiquetas</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
