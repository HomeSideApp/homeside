<script setup lang="ts">
import {
    Head,
    router,
    setLayoutProps,
    useForm,
    useHttp,
} from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChevronDown,
    FolderSearch,
    Pencil,
    RefreshCw,
    Search,
    Server,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import IconAction from '@/components/admin/IconAction.vue';
import Heading from '@/components/Heading.vue';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAuthorization } from '@/composables/useAuthorization';
import { index as contactsIndex } from '@/routes/contacts';
import {
    index,
    store,
    update,
    destroy,
    test,
    testConnection,
    discover,
    preview,
    discoverServer,
    sync,
} from '@/routes/contacts/sources';
import {
    connect as googleConnect,
    reconnect as googleReconnect,
    store as googleStore,
} from '@/routes/contacts/sources/google';

interface Collection {
    id?: string;
    remote_id: string;
    name: string;
    enabled?: boolean;
    read_only: boolean;
    last_synced_at?: string | null;
}
interface Source {
    id: string;
    name: string;
    provider: string;
    household_id: string | null;
    provider_configuration: { server_url?: string; favorite_label?: string };
    enabled: boolean;
    sync_enabled: boolean;
    last_synced_at: string | null;
    last_sync_status: string | null;
    last_sync_error: string | null;
    collections: Collection[];
}
interface Provider {
    key: string;
    name: string;
    description: string;
    authentication_type: string;
}

const props = defineProps<{
    sources: Source[];
    providers: Provider[];
    households: Array<{ id: string; name: string }>;
    googleDraft: { email: string } | null;
}>();
setLayoutProps({
    breadcrumbs: [
        { title: 'Contactos', href: contactsIndex().url },
        { title: 'Fuentes', href: index().url },
    ],
});

const { can } = useAuthorization();
const googleDraftOpen = ref(Boolean(props.googleDraft));
const googleForm = useForm({
    name: props.googleDraft ? `Google: ${props.googleDraft.email}` : '',
    favorite_label: 'Starred',
    selected_collections: ['all'],
});
function saveGoogleDraft() {
    googleForm.post(googleStore().url, {
        onSuccess: () => {
            googleDraftOpen.value = false;
        },
    });
}

const formOpen = ref(false);
const editingSourceId = ref<string | null>(null);
const expandedSourceId = ref<string | null>(null);
const pendingDelete = ref<Source | null>(null);
const discoveryInput = ref('');
const discoveryError = ref<string | null>(null);
const discovering = ref(false);
const testing = ref(false);
const availableCollections = ref<Collection[]>([]);
const previewFingerprint = ref<string | null>(null);

const form = useForm({
    provider: 'carddav',
    household_id: null as string | null,
    name: '',
    server_url: '',
    favorite_label: 'Starred',
    username: '',
    app_password: '',
    enabled: true,
    sync_enabled: true,
    selected_collections: [] as string[],
});

const previewRequest = useHttp<
    {
        provider: string;
        source_id: string | null;
        server_url: string;
        username: string;
        app_password: string;
    },
    { collections: Collection[] }
>({
    provider: 'carddav',
    source_id: null,
    server_url: '',
    username: '',
    app_password: '',
});
const serverRequest = useHttp<
    { domain: string; username: string; app_password: string },
    { server_url: string }
>({ domain: '', username: '', app_password: '' });

const fingerprint = computed(() =>
    JSON.stringify([
        form.provider,
        editingSourceId.value,
        form.server_url,
        form.username,
        form.app_password,
    ]),
);
const hasCurrentDiscovery = computed(
    () => previewFingerprint.value === fingerprint.value,
);
const canSave = computed(
    () =>
        hasCurrentDiscovery.value &&
        form.selected_collections.length > 0 &&
        !form.processing &&
        !discovering.value,
);

function openCreate(provider: string): void {
    editingSourceId.value = null;
    form.reset();
    form.clearErrors();
    form.provider = provider;
    availableCollections.value = [];
    previewFingerprint.value = null;
    discoveryInput.value = '';
    discoveryError.value = null;
    formOpen.value = true;
}

function openEdit(source: Source): void {
    editingSourceId.value = source.id;
    form.reset();
    form.clearErrors();
    form.provider = source.provider;
    form.household_id = source.household_id;
    form.name = source.name;
    form.server_url = source.provider_configuration.server_url ?? '';
    form.favorite_label =
        source.provider_configuration.favorite_label ?? 'Starred';
    form.username = '';
    form.app_password = '';
    form.enabled = source.enabled;
    form.sync_enabled = source.sync_enabled;
    availableCollections.value = source.collections;
    form.selected_collections = source.collections
        .filter((collection) => collection.enabled)
        .map((collection) => collection.remote_id);
    previewFingerprint.value =
        source.collections.length > 0 ? fingerprint.value : null;
    discoveryInput.value = form.server_url;
    discoveryError.value = null;
    formOpen.value = true;
}

async function previewCollections(): Promise<void> {
    if (
        !form.server_url ||
        (editingSourceId.value === null &&
            (!form.username || !form.app_password))
    ) {
        discoveryError.value =
            'Introduce la URL, el usuario y la contraseña de aplicación.';

        return;
    }

    discovering.value = true;
    discoveryError.value = null;
    previewFingerprint.value = null;
    previewRequest.provider = form.provider;
    previewRequest.source_id = editingSourceId.value;
    previewRequest.server_url = form.server_url;
    previewRequest.username = form.username;
    previewRequest.app_password = form.app_password;
    const requestedFingerprint = fingerprint.value;

    try {
        await previewRequest.post(preview().url, {
            onSuccess: (data) => {
                if (requestedFingerprint !== fingerprint.value) {
                    return;
                }

                const oldSelection = new Set(form.selected_collections);
                availableCollections.value = data.collections;
                form.selected_collections = data.collections
                    .filter((collection) =>
                        oldSelection.has(collection.remote_id),
                    )
                    .map((collection) => collection.remote_id);
                previewFingerprint.value = requestedFingerprint;

                if (data.collections.length === 0) {
                    discoveryError.value =
                        'No se encontraron libretas de contactos.';
                }
            },
        });
    } catch {
        discoveryError.value =
            'No se pudieron descubrir las libretas. Comprueba la conexión y las credenciales.';
    } finally {
        discovering.value = false;
        previewRequest.app_password = '';
    }
}

async function findServer(): Promise<void> {
    if (!discoveryInput.value || !form.username || !form.app_password) {
        discoveryError.value =
            'Introduce el dominio, el usuario y la contraseña de aplicación.';

        return;
    }

    discovering.value = true;
    discoveryError.value = null;
    serverRequest.domain = discoveryInput.value;
    serverRequest.username = form.username;
    serverRequest.app_password = form.app_password;
    let foundUrl: string | null = null;

    try {
        await serverRequest.post(discoverServer().url, {
            onSuccess: (data) => {
                foundUrl = data.server_url;
            },
        });
    } catch {
        discoveryError.value =
            'No se encontró un servidor CardDAV. Comprueba el dominio y las credenciales.';
    } finally {
        discovering.value = false;
        serverRequest.app_password = '';
    }

    if (foundUrl) {
        form.server_url = foundUrl;
        await previewCollections();
    }
}

function toggleCollection(remoteId: string, checked: boolean): void {
    form.selected_collections = checked
        ? [...form.selected_collections, remoteId]
        : form.selected_collections.filter((id) => id !== remoteId);
}

function submit(): void {
    if (!canSave.value) {
        form.setError(
            'selected_collections',
            'Descubre y selecciona al menos una libreta.',
        );

        return;
    }

    form.transform((data) =>
        editingSourceId.value === null
            ? data
            : {
                  name: data.name,
                  server_url: data.server_url,
                  favorite_label: data.favorite_label,
                  username: data.username || null,
                  app_password: data.app_password || null,
                  enabled: data.enabled,
                  sync_enabled: data.sync_enabled,
                  selected_collections: data.selected_collections,
              },
    );
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            formOpen.value = false;
        },
    };

    if (editingSourceId.value) {
        form.put(update(editingSourceId.value).url, options);
    } else {
        form.post(store().url, options);
    }
}

function testStored(source: Source): void {
    router.post(test(source.id).url, {}, { preserveScroll: true });
}

function testDraft(): void {
    if (!form.username || !form.app_password) {
        return;
    }

    testing.value = true;
    router.post(
        testConnection().url,
        {
            provider: form.provider,
            server_url: form.server_url,
            username: form.username,
            app_password: form.app_password,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                testing.value = false;
            },
        },
    );
}

function confirmDelete(): void {
    if (!pendingDelete.value) {
        return;
    }

    router.delete(destroy(pendingDelete.value.id).url, {
        preserveScroll: true,
        onFinish: () => {
            pendingDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Fuentes de contactos" />
    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                title="Fuentes de contactos"
                description="Elige qué libretas de cada cuenta quieres importar"
            />
            <IconAction
                label="Volver a contactos"
                :href="contactsIndex().url"
                variant="outline"
            >
                <ArrowLeft aria-hidden="true" />
            </IconAction>
        </div>

        <div v-if="can('contacts.sources.create')" class="flex flex-wrap gap-2">
            <template v-for="provider in props.providers" :key="provider.key">
                <Button v-if="provider.key === 'google'" as-child
                    ><a :href="googleConnect().url"
                        ><Server data-icon="inline-start" />Añadir
                        {{ provider.name }}</a
                    ></Button
                >
                <Button v-else type="button" @click="openCreate(provider.key)">
                    <Server data-icon="inline-start" />
                    Añadir {{ provider.name }}
                </Button>
            </template>
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Fuente</TableHead>
                        <TableHead>Proveedor</TableHead>
                        <TableHead>Visibilidad</TableHead>
                        <TableHead>Libretas</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead>Última sincronización</TableHead>
                        <TableHead class="text-right">Acciones</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-for="source in props.sources" :key="source.id">
                        <TableRow>
                            <TableCell class="font-medium">{{
                                source.name
                            }}</TableCell>
                            <TableCell>{{
                                props.providers.find(
                                    (provider) =>
                                        provider.key === source.provider,
                                )?.name ?? source.provider
                            }}</TableCell>
                            <TableCell>{{
                                source.household_id ? 'Hogar' : 'Privado'
                            }}</TableCell>
                            <TableCell>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :aria-label="
                                        'Ver libretas de ' + source.name
                                    "
                                    @click="
                                        expandedSourceId =
                                            expandedSourceId === source.id
                                                ? null
                                                : source.id
                                    "
                                >
                                    {{
                                        source.collections.filter(
                                            (collection) => collection.enabled,
                                        ).length
                                    }}
                                    / {{ source.collections.length }}
                                    <ChevronDown
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Button>
                            </TableCell>
                            <TableCell
                                ><Badge
                                    :variant="
                                        source.enabled ? 'secondary' : 'outline'
                                    "
                                    >{{
                                        source.last_sync_status ??
                                        (source.enabled
                                            ? 'Pendiente'
                                            : 'Inactiva')
                                    }}</Badge
                                ></TableCell
                            >
                            <TableCell>{{
                                source.last_synced_at ?? 'Nunca'
                            }}</TableCell>
                            <TableCell>
                                <div class="flex justify-end gap-1">
                                    <IconAction
                                        v-if="source.provider === 'google'"
                                        :label="'Reconectar ' + source.name"
                                        :href="googleReconnect(source.id).url"
                                        external
                                        permission="contacts.sources.update"
                                        variant="outline"
                                        ><RefreshCw aria-hidden="true"
                                    /></IconAction>
                                    <IconAction
                                        v-else
                                        :label="'Editar ' + source.name"
                                        permission="contacts.sources.update"
                                        variant="outline"
                                        @click="openEdit(source)"
                                        ><Pencil aria-hidden="true"
                                    /></IconAction>
                                    <IconAction
                                        :label="
                                            'Probar conexión de ' + source.name
                                        "
                                        permission="contacts.sources.sync"
                                        variant="outline"
                                        @click="testStored(source)"
                                        ><Search aria-hidden="true"
                                    /></IconAction>
                                    <IconAction
                                        :label="
                                            'Descubrir libretas de ' +
                                            source.name
                                        "
                                        permission="contacts.sources.sync"
                                        variant="outline"
                                        @click="
                                            router.post(
                                                discover(source.id).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        "
                                        ><FolderSearch aria-hidden="true"
                                    /></IconAction>
                                    <IconAction
                                        :label="'Sincronizar ' + source.name"
                                        permission="contacts.sources.sync"
                                        variant="outline"
                                        @click="
                                            router.post(
                                                sync(source.id).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        "
                                        ><RefreshCw aria-hidden="true"
                                    /></IconAction>
                                    <IconAction
                                        :label="'Desconectar ' + source.name"
                                        permission="contacts.sources.delete"
                                        variant="destructive"
                                        @click="pendingDelete = source"
                                        ><Trash2 aria-hidden="true"
                                    /></IconAction>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="expandedSourceId === source.id">
                            <TableCell colspan="7">
                                <div
                                    v-if="source.collections.length"
                                    class="grid gap-2 sm:grid-cols-2"
                                >
                                    <div
                                        v-for="collection in source.collections"
                                        :key="collection.remote_id"
                                        class="flex items-center gap-2 rounded-md border p-2"
                                    >
                                        <Badge
                                            :variant="
                                                collection.enabled
                                                    ? 'default'
                                                    : 'outline'
                                            "
                                            >{{
                                                collection.enabled
                                                    ? 'Importar'
                                                    : 'Sin importar'
                                            }}</Badge
                                        >
                                        <span>{{ collection.name }}</span>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-muted-foreground">
                                    Todavía no se han descubierto libretas.
                                    Edita la fuente para elegirlas.
                                </p>
                                <p
                                    v-if="source.last_sync_error"
                                    class="mt-2 text-sm text-destructive"
                                >
                                    {{ source.last_sync_error }}
                                </p>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-if="props.sources.length === 0">
                        <TableCell colspan="7">
                            <Empty
                                ><EmptyHeader
                                    ><EmptyTitle>Sin fuentes</EmptyTitle
                                    ><EmptyDescription
                                        >Añade un proveedor para importar
                                        contactos.</EmptyDescription
                                    ></EmptyHeader
                                ></Empty
                            >
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Dialog
            :open="googleDraftOpen"
            @update:open="
                (open) => {
                    googleDraftOpen = open;
                }
            "
        >
            <DialogContent>
                <DialogHeader
                    ><DialogTitle>Importar contactos de Google</DialogTitle
                    ><DialogDescription
                        >Cuenta {{ props.googleDraft?.email }}. Elige la
                        colección y guarda la fuente para iniciar la
                        importación.</DialogDescription
                    ></DialogHeader
                >
                <form
                    id="google-source-form"
                    class="flex flex-col gap-4"
                    @submit.prevent="saveGoogleDraft"
                >
                    <FieldGroup>
                        <Field
                            ><FieldLabel for="google-source-name"
                                >Nombre de la fuente</FieldLabel
                            ><Input
                                id="google-source-name"
                                v-model="googleForm.name"
                                required
                            /><FieldError v-if="googleForm.errors.name">{{
                                googleForm.errors.name
                            }}</FieldError></Field
                        >
                        <Field
                            ><FieldLabel for="google-favorite-label"
                                >Etiqueta de favoritos</FieldLabel
                            ><Input
                                id="google-favorite-label"
                                v-model="googleForm.favorite_label"
                                required
                            /><FieldError
                                v-if="googleForm.errors.favorite_label"
                                >{{
                                    googleForm.errors.favorite_label
                                }}</FieldError
                            ></Field
                        >
                        <Field>
                            <FieldLabel>Contactos a importar</FieldLabel>
                            <label class="flex items-center gap-2"
                                ><input
                                    type="checkbox"
                                    :checked="
                                        googleForm.selected_collections.includes(
                                            'all',
                                        )
                                    "
                                    @change="
                                        googleForm.selected_collections = (
                                            $event.target as HTMLInputElement
                                        ).checked
                                            ? ['all']
                                            : []
                                    "
                                />Todos los contactos</label
                            >
                            <FieldError
                                v-if="googleForm.errors.selected_collections"
                                >{{
                                    googleForm.errors.selected_collections
                                }}</FieldError
                            >
                        </Field>
                    </FieldGroup>
                </form>
                <DialogFooter
                    ><Button
                        type="submit"
                        form="google-source-form"
                        :disabled="
                            googleForm.processing ||
                            googleForm.selected_collections.length === 0
                        "
                        >Guardar e importar</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="formOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editingSourceId ? 'Editar fuente' : 'Añadir fuente'
                    }}</DialogTitle>
                    <DialogDescription
                        >Descubre las libretas y selecciona al menos una antes
                        de guardar.</DialogDescription
                    >
                </DialogHeader>
                <form
                    id="source-form"
                    class="flex flex-col gap-4"
                    @submit.prevent="submit"
                >
                    <FieldGroup>
                        <Field>
                            <FieldLabel for="source-name"
                                >Nombre de la cuenta</FieldLabel
                            >
                            <Input
                                id="source-name"
                                v-model="form.name"
                                required
                            />
                            <FieldError v-if="form.errors.name">{{
                                form.errors.name
                            }}</FieldError>
                        </Field>
                        <Field v-if="!editingSourceId">
                            <FieldLabel for="source-scope"
                                >Visibilidad</FieldLabel
                            >
                            <Select v-model="form.household_id">
                                <SelectTrigger id="source-scope"
                                    ><SelectValue placeholder="Privado"
                                /></SelectTrigger>
                                <SelectContent
                                    ><SelectGroup>
                                        <SelectItem :value="null"
                                            >Privado</SelectItem
                                        >
                                        <SelectItem
                                            v-for="household in props.households"
                                            :key="household.id"
                                            :value="household.id"
                                            >{{ household.name }}</SelectItem
                                        >
                                    </SelectGroup></SelectContent
                                >
                            </Select>
                        </Field>
                        <Field v-if="form.provider === 'carddav'">
                            <FieldLabel for="source-domain"
                                >Dominio para buscar CardDAV</FieldLabel
                            >
                            <div class="flex flex-wrap gap-2">
                                <Input
                                    id="source-domain"
                                    v-model="discoveryInput"
                                    class="min-w-48 flex-1"
                                    placeholder="cloud.example.com"
                                    @keydown.enter.prevent="findServer"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    :disabled="
                                        discovering ||
                                        !discoveryInput ||
                                        !form.username ||
                                        !form.app_password
                                    "
                                    @click="findServer"
                                    >Buscar servidor</Button
                                >
                            </div>
                        </Field>
                        <Field>
                            <FieldLabel for="source-url"
                                >URL del servidor</FieldLabel
                            >
                            <Input
                                id="source-url"
                                v-model="form.server_url"
                                type="url"
                                required
                            />
                            <FieldError v-if="form.errors.server_url">{{
                                form.errors.server_url
                            }}</FieldError>
                        </Field>
                        <Field>
                            <FieldLabel for="source-favorite-label"
                                >Etiqueta de favoritos</FieldLabel
                            >
                            <Input
                                id="source-favorite-label"
                                v-model="form.favorite_label"
                                maxlength="80"
                                required
                            />
                            <FieldDescription
                                >Los contactos con esta etiqueta aparecerán en
                                Favoritos. Valor predeterminado:
                                Starred.</FieldDescription
                            >
                            <FieldError v-if="form.errors.favorite_label">{{
                                form.errors.favorite_label
                            }}</FieldError>
                        </Field>
                        <Field>
                            <FieldLabel for="source-username"
                                >Usuario</FieldLabel
                            >
                            <Input
                                id="source-username"
                                v-model="form.username"
                                autocomplete="username"
                                :placeholder="
                                    editingSourceId
                                        ? 'Dejar vacío para conservar'
                                        : undefined
                                "
                                :required="!editingSourceId"
                            />
                            <FieldError v-if="form.errors.username">{{
                                form.errors.username
                            }}</FieldError>
                        </Field>
                        <Field>
                            <FieldLabel for="source-password"
                                >Contraseña de aplicación</FieldLabel
                            >
                            <Input
                                id="source-password"
                                v-model="form.app_password"
                                type="password"
                                autocomplete="new-password"
                                :placeholder="
                                    editingSourceId
                                        ? 'Dejar vacío para conservar'
                                        : undefined
                                "
                                :required="!editingSourceId"
                            />
                            <FieldError v-if="form.errors.app_password">{{
                                form.errors.app_password
                            }}</FieldError>
                        </Field>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="discovering || !form.server_url"
                                @click="previewCollections"
                                >{{
                                    discovering
                                        ? 'Descubriendo...'
                                        : 'Descubrir libretas'
                                }}</Button
                            >
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="
                                    testing ||
                                    !form.server_url ||
                                    !form.username ||
                                    !form.app_password
                                "
                                @click="testDraft"
                                >{{
                                    testing
                                        ? 'Comprobando...'
                                        : 'Probar conexión'
                                }}</Button
                            >
                        </div>
                        <FieldError v-if="discoveryError">{{
                            discoveryError
                        }}</FieldError>
                        <Field
                            v-if="
                                hasCurrentDiscovery &&
                                availableCollections.length
                            "
                        >
                            <FieldLabel
                                >Libretas que quieres importar</FieldLabel
                            >
                            <div
                                class="flex flex-col gap-2 rounded-md border p-3"
                            >
                                <label
                                    v-for="collection in availableCollections"
                                    :key="collection.remote_id"
                                    class="flex cursor-pointer items-center gap-3"
                                >
                                    <input
                                        type="checkbox"
                                        class="size-4"
                                        :checked="
                                            form.selected_collections.includes(
                                                collection.remote_id,
                                            )
                                        "
                                        @change="
                                            toggleCollection(
                                                collection.remote_id,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    <span>{{ collection.name }}</span>
                                </label>
                            </div>
                        </Field>
                        <FieldError v-if="form.errors.selected_collections">{{
                            form.errors.selected_collections
                        }}</FieldError>
                        <Field v-if="editingSourceId" orientation="horizontal"
                            ><FieldLabel for="source-enabled"
                                >Fuente activa</FieldLabel
                            ><Switch
                                id="source-enabled"
                                v-model:checked="form.enabled"
                        /></Field>
                        <Field v-if="editingSourceId" orientation="horizontal"
                            ><FieldLabel for="source-sync-enabled"
                                >Sincronización automática</FieldLabel
                            ><Switch
                                id="source-sync-enabled"
                                v-model:checked="form.sync_enabled"
                        /></Field>
                    </FieldGroup>
                </form>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="formOpen = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        form="source-form"
                        :disabled="!canSave"
                        >{{
                            editingSourceId
                                ? 'Guardar cambios'
                                : 'Guardar e importar'
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog
            :open="pendingDelete !== null"
            @update:open="
                (open: boolean) => {
                    if (!open) pendingDelete = null;
                }
            "
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >¿Desconectar
                        {{ pendingDelete?.name }}?</AlertDialogTitle
                    >
                    <AlertDialogDescription
                        >Se detendrá la sincronización. Los contactos importados
                        conservarán su historial.</AlertDialogDescription
                    >
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancelar</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete"
                        >Desconectar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
