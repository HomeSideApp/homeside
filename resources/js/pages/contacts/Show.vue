<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    Link2,
    Mail,
    MapPin,
    Pencil,
    Phone,
    StickyNote,
    Trash2,
    UserRound,
    UsersRound,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import IconAction from '@/components/admin/IconAction.vue';
import ContactAvatar from '@/components/contacts/ContactAvatar.vue';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy, edit, index } from '@/routes/contacts';
import { update as useRemoteFields } from '@/routes/contacts/remote-fields';
import type {
    ContactRecordData,
    ContactRelation,
    ContactValue,
} from '@/types/contacts';

interface Contact {
    id: string;
    display_name: string;
    household_id: string | null;
    avatar_url: string | null;
    records: ContactRecordData[];
    labels: Array<{ id: string; name: string }>;
}

const props = defineProps<{
    contact: Contact;
    household_name: string | null;
    sources: Array<{ id: string; name: string }>;
    can: { update: boolean; delete: boolean };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Contactos', href: index().url },
        { title: props.contact.display_name },
    ],
});

const deleteOpen = ref(false);
const remoteOpen = ref(false);
const selectedRemote = ref<ContactRecordData | null>(null);
const remoteForm = useForm({ record_id: '', fields: [] as string[] });
type RemoteField =
    | 'formatted_name'
    | 'given_name'
    | 'family_name'
    | 'additional_name'
    | 'nickname'
    | 'organization'
    | 'job_title'
    | 'birthday'
    | 'notes'
    | 'emails'
    | 'phones'
    | 'addresses'
    | 'urls'
    | 'dates'
    | 'relations'
    | 'photo';
const remoteFields: Array<{ key: RemoteField; label: string }> = [
    { key: 'formatted_name', label: 'Nombre' },
    { key: 'given_name', label: 'Nombre de pila' },
    { key: 'family_name', label: 'Apellidos' },
    { key: 'additional_name', label: 'Segundo nombre' },
    { key: 'nickname', label: 'Apodo' },
    { key: 'organization', label: 'Empresa' },
    { key: 'job_title', label: 'Puesto' },
    { key: 'birthday', label: 'Cumpleaños' },
    { key: 'notes', label: 'Notas' },
    { key: 'emails', label: 'Correos' },
    { key: 'phones', label: 'Teléfonos' },
    { key: 'addresses', label: 'Direcciones' },
    { key: 'urls', label: 'Sitios web' },
    { key: 'dates', label: 'Fechas importantes' },
    { key: 'relations', label: 'Relaciones' },
    { key: 'photo', label: 'Fotografía' },
];
const localRecord = computed(
    () =>
        props.contact.records.find((record) => record.source_id === null) ??
        null,
);
const externalRecords = computed(() =>
    props.contact.records.filter((record) => record.source_id !== null),
);
const sourceNames = computed(
    () => new Map(props.sources.map((source) => [source.id, source.name])),
);
function fieldValue(
    record: ContactRecordData | null,
    key: RemoteField,
): string {
    if (!record) {
        return '—';
    }

    if (key === 'photo') {
        return record.has_photo ? 'Fotografía disponible' : 'Sin fotografía';
    }

    if (
        key === 'emails' ||
        key === 'phones' ||
        key === 'addresses' ||
        key === 'urls'
    ) {
        return (
            record[key]
                .map((item) => item.value)
                .filter(Boolean)
                .join(' · ') || '—'
        );
    }

    if (key === 'dates') {
        return (
            record.dates
                .map((item) =>
                    item.label ? `${item.label}: ${item.value}` : item.value,
                )
                .filter(Boolean)
                .join(' · ') || '—'
        );
    }

    if (key === 'relations') {
        return (
            record.relations
                .map((item) => item.name || item.external_value || '')
                .filter(Boolean)
                .join(' · ') || '—'
        );
    }

    return record[key] || '—';
}
function isDifferent(key: RemoteField): boolean {
    if (!selectedRemote.value || !localRecord.value) {
        return false;
    }

    if (key === 'photo') {
        return (
            selectedRemote.value.has_photo &&
            selectedRemote.value.photo_checksum !==
                localRecord.value.photo_checksum
        );
    }

    return (
        JSON.stringify(selectedRemote.value[key]) !==
        JSON.stringify(localRecord.value[key])
    );
}
function openRemote(record: ContactRecordData): void {
    selectedRemote.value = record;
    remoteForm.record_id = record.id;
    remoteForm.fields = [];
    remoteForm.clearErrors();
    remoteOpen.value = true;
}
function toggleRemoteField(key: RemoteField, checked: boolean): void {
    remoteForm.fields = checked
        ? [...remoteForm.fields, key]
        : remoteForm.fields.filter((field) => field !== key);
}
function applyRemoteFields(): void {
    if (!selectedRemote.value || remoteForm.fields.length === 0) {
        return;
    }

    remoteForm.put(useRemoteFields(props.contact.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            remoteOpen.value = false;
        },
    });
}
const orderedRecords = computed(() =>
    localRecord.value ? [localRecord.value] : externalRecords.value,
);

type ValueKind = 'emails' | 'phones' | 'addresses' | 'urls';

function uniqueValues(kind: ValueKind): ContactValue[] {
    const seen = new Set<string>();

    return orderedRecords.value
        .flatMap((record) => record[kind])
        .filter((item) => {
            const value = item.value.trim();
            const key = value.toLocaleLowerCase();

            if (!value || seen.has(key)) {
                return false;
            }

            seen.add(key);

            return true;
        });
}

function firstText(
    field: 'organization' | 'job_title' | 'nickname' | 'notes',
): string | null {
    if (localRecord.value) {
        return localRecord.value[field]?.trim() || null;
    }

    return (
        orderedRecords.value
            .map((record) => record[field]?.trim())
            .find((value) => Boolean(value)) ?? null
    );
}

function formatDate(value: string): string {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return value;
    }

    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('es-ES', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          }).format(date);
}

const emails = computed(() => uniqueValues('emails'));
const phones = computed(() => uniqueValues('phones'));
const addresses = computed(() => uniqueValues('addresses'));
const urls = computed(() => uniqueValues('urls'));
const organization = computed(() => firstText('organization'));
const jobTitle = computed(() => firstText('job_title'));
const nickname = computed(() => firstText('nickname'));
const notes = computed(() => firstText('notes'));
const dates = computed(() => {
    const seen = new Set<string>();

    return orderedRecords.value
        .flatMap((record) => [
            ...(record.birthday
                ? [{ label: 'Cumpleaños', value: record.birthday }]
                : []),
            ...record.dates.map((date) => ({
                label:
                    date.label ||
                    (date.kind === 'anniversary' ? 'Aniversario' : 'Fecha'),
                value: date.value,
            })),
        ])
        .filter((date) => {
            const key =
                date.label === 'Fecha'
                    ? `${date.label}\0${date.value}`
                    : date.label;

            if (seen.has(key)) {
                return false;
            }

            seen.add(key);

            return true;
        });
});

const relationNames: Record<ContactRelation['type'], string> = {
    parent: 'Padre o madre',
    child: 'Hijo o hija',
    sibling: 'Hermano o hermana',
    spouse: 'Pareja',
    friend: 'Amistad',
    colleague: 'Colega',
    emergency: 'Contacto de emergencia',
    other: 'Relación',
};
const relations = computed(() => {
    const seen = new Set<string>();

    return orderedRecords.value
        .flatMap((record) => record.relations)
        .filter((relation) => {
            const name = relation.name || relation.external_value;
            const key = `${relation.type}\0${name}`;

            if (!name || seen.has(key)) {
                return false;
            }

            seen.add(key);

            return true;
        });
});
const hasDetails = computed(
    () =>
        emails.value.length > 0 ||
        phones.value.length > 0 ||
        addresses.value.length > 0 ||
        urls.value.length > 0 ||
        dates.value.length > 0 ||
        relations.value.length > 0 ||
        Boolean(
            organization.value ||
            jobTitle.value ||
            nickname.value ||
            notes.value,
        ),
);

function confirmDelete(): void {
    router.delete(destroy(props.contact.id).url, {
        onFinish: () => {
            deleteOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="contact.display_name" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-6 sm:px-8">
        <div class="flex items-center justify-between gap-4">
            <IconAction
                label="Volver a contactos"
                :href="index().url"
                variant="outline"
            >
                <ArrowLeft aria-hidden="true" />
            </IconAction>
            <div class="flex items-center gap-2">
                <Button v-if="can.update" as-child>
                    <Link :href="edit(contact.id).url">
                        <Pencil class="size-4" aria-hidden="true" />
                        Editar
                    </Link>
                </Button>
                <IconAction
                    v-if="can.delete"
                    :label="`Eliminar ${contact.display_name}`"
                    variant="destructive"
                    @click="deleteOpen = true"
                >
                    <Trash2 aria-hidden="true" />
                </IconAction>
            </div>
        </div>

        <section class="flex flex-col gap-5 sm:flex-row sm:items-center">
            <ContactAvatar
                :id="contact.id"
                :name="contact.display_name"
                :avatar-url="contact.avatar_url"
                size="large"
            />
            <div class="min-w-0 flex-1">
                <h1 class="text-3xl font-semibold tracking-tight break-words">
                    {{ contact.display_name }}
                </h1>
                <p
                    v-if="jobTitle || organization"
                    class="mt-1 text-muted-foreground"
                >
                    {{ [jobTitle, organization].filter(Boolean).join(' · ') }}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Badge variant="outline">
                        {{ household_name || 'Contacto privado' }}
                    </Badge>
                    <Badge
                        v-for="label in contact.labels"
                        :key="label.id"
                        variant="secondary"
                    >
                        {{ label.name }}
                    </Badge>
                </div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(17rem,2fr)]">
            <div class="flex min-w-0 flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Datos de contacto</CardTitle>
                        <CardDescription
                            >Información disponible en sus registros locales y
                            externos.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex flex-col gap-5">
                        <p
                            v-if="!hasDetails"
                            class="text-sm text-muted-foreground"
                        >
                            Aún no hay más datos para este contacto.
                        </p>
                        <div
                            v-if="emails.length"
                            class="flex items-start gap-3"
                        >
                            <Mail
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">
                                    Correo electrónico
                                </p>
                                <p
                                    v-for="email in emails"
                                    :key="email.value"
                                    class="text-sm break-all text-muted-foreground"
                                >
                                    {{ email.value }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="phones.length"
                            class="flex items-start gap-3"
                        >
                            <Phone
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">
                                    Número de teléfono
                                </p>
                                <p
                                    v-for="phone in phones"
                                    :key="phone.value"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ phone.value }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="addresses.length"
                            class="flex items-start gap-3"
                        >
                            <MapPin
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Dirección</p>
                                <p
                                    v-for="address in addresses"
                                    :key="address.value"
                                    class="text-sm break-words whitespace-pre-line text-muted-foreground"
                                >
                                    {{ address.value }}
                                </p>
                            </div>
                        </div>
                        <div v-if="urls.length" class="flex items-start gap-3">
                            <Link2
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Sitios web</p>
                                <p
                                    v-for="url in urls"
                                    :key="url.value"
                                    class="text-sm break-all text-muted-foreground"
                                >
                                    {{ url.value }}
                                </p>
                            </div>
                        </div>
                        <div v-if="dates.length" class="flex items-start gap-3">
                            <CalendarDays
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Fechas</p>
                                <p
                                    v-for="date in dates"
                                    :key="`${date.label}-${date.value}`"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ date.label }} ·
                                    {{ formatDate(date.value) }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="organization || jobTitle"
                            class="flex items-start gap-3"
                        >
                            <Building2
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Trabajo</p>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        [jobTitle, organization]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </p>
                            </div>
                        </div>
                        <div v-if="nickname" class="flex items-start gap-3">
                            <UserRound
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Apodo</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ nickname }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="relations.length"
                            class="flex items-start gap-3"
                        >
                            <UsersRound
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Relaciones</p>
                                <p
                                    v-for="relation in relations"
                                    :key="`${relation.type}-${relation.name}-${relation.external_value}`"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ relationNames[relation.type] }} ·
                                    {{
                                        relation.name || relation.external_value
                                    }}
                                </p>
                            </div>
                        </div>
                        <div v-if="notes" class="flex items-start gap-3">
                            <StickyNote
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-medium">Notas</p>
                                <p
                                    class="text-sm break-words whitespace-pre-wrap text-muted-foreground"
                                >
                                    {{ notes }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Fuentes</CardTitle>
                        <CardDescription
                            >Origen de la información de este
                            contacto.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <div
                            v-if="localRecord"
                            class="flex items-center justify-between gap-3"
                        >
                            <Badge variant="outline">Contacto local</Badge>
                            <span class="text-xs text-muted-foreground"
                                >Tiene prioridad</span
                            >
                        </div>
                        <div
                            v-for="record in externalRecords"
                            :key="record.id"
                            class="flex items-center justify-between gap-3"
                        >
                            <Badge variant="outline">
                                {{
                                    sourceNames.get(record.source_id ?? '') ||
                                    'Fuente externa'
                                }}
                            </Badge>
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openRemote(record)"
                            >
                                Ver datos
                            </Button>
                        </div>
                        <p
                            v-if="!localRecord && !externalRecords.length"
                            class="text-sm text-muted-foreground"
                        >
                            Sin fuentes disponibles.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="flex min-w-0 flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Gastos vinculados</CardTitle>
                        <CardDescription
                            >Actividad económica relacionada con este
                            contacto.</CardDescription
                        >
                    </CardHeader>
                    <CardContent
                        class="flex items-start gap-3 text-sm text-muted-foreground"
                    >
                        <Wallet class="size-5 shrink-0" aria-hidden="true" />
                        <p>
                            La vinculación de gastos con contactos se añadirá
                            aquí.
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Más información vinculada</CardTitle>
                        <CardDescription
                            >Un espacio para futuras relaciones con otros
                            módulos.</CardDescription
                        >
                    </CardHeader>
                    <CardContent
                        class="flex items-start gap-3 text-sm text-muted-foreground"
                    >
                        <Link2 class="size-5 shrink-0" aria-hidden="true" />
                        <p>
                            Los datos que se relacionen con este contacto
                            aparecerán aquí.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog v-model:open="remoteOpen">
            <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle
                        >Datos de
                        {{
                            sourceNames.get(selectedRemote?.source_id ?? '') ||
                            'la fuente remota'
                        }}</DialogTitle
                    >
                    <DialogDescription>
                        La copia local conserva la prioridad. Selecciona los
                        campos remotos que quieras aplicar.
                    </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-2">
                    <div
                        v-for="field in remoteFields"
                        :key="field.key"
                        class="grid gap-2 rounded-md border p-3 sm:grid-cols-[auto_9rem_1fr]"
                    >
                        <Checkbox
                            v-if="
                                can.update &&
                                localRecord &&
                                (field.key !== 'photo' ||
                                    selectedRemote?.has_photo)
                            "
                            :model-value="remoteForm.fields.includes(field.key)"
                            :aria-label="`Usar ${field.label.toLowerCase()} de la fuente remota`"
                            @update:model-value="
                                (checked) =>
                                    toggleRemoteField(
                                        field.key,
                                        checked === true,
                                    )
                            "
                        />
                        <span v-else class="size-4" />
                        <span class="text-sm font-medium">{{
                            field.label
                        }}</span>
                        <div class="min-w-0 text-sm break-words">
                            <p>{{ fieldValue(selectedRemote, field.key) }}</p>
                            <p
                                v-if="localRecord && isDifferent(field.key)"
                                class="text-xs text-muted-foreground"
                            >
                                Local: {{ fieldValue(localRecord, field.key) }}
                            </p>
                        </div>
                    </div>
                </div>
                <p
                    v-if="
                        remoteForm.errors.fields || remoteForm.errors.record_id
                    "
                    class="text-sm text-destructive"
                >
                    {{
                        remoteForm.errors.fields || remoteForm.errors.record_id
                    }}
                </p>
                <DialogFooter>
                    <Button variant="outline" @click="remoteOpen = false"
                        >Cerrar</Button
                    >
                    <Button
                        v-if="can.update && localRecord"
                        :disabled="
                            remoteForm.processing ||
                            remoteForm.fields.length === 0
                        "
                        @click="applyRemoteFields"
                    >
                        Aplicar campos seleccionados
                    </Button>
                    <Button v-else-if="can.update" as-child>
                        <Link :href="edit(contact.id).url"
                            >Crear copia local al editar</Link
                        >
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog v-model:open="deleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >¿Eliminar {{ contact.display_name }}?</AlertDialogTitle
                    >
                    <AlertDialogDescription>
                        Se eliminará este contacto y sus datos asociados.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
