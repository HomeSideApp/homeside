<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Plus, Server, Star, Trash2 } from '@lucide/vue';
import { computed, onUnmounted, ref } from 'vue';
import IconAction from '@/components/admin/IconAction.vue';
import ContactAvatar from '@/components/contacts/ContactAvatar.vue';
import ContactFormFields from '@/components/contacts/ContactFormFields.vue';
import Heading from '@/components/Heading.vue';
import SearchInput from '@/components/SearchInput.vue';
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
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAuthorization } from '@/composables/useAuthorization';
import { index, show, store } from '@/routes/contacts';
import { index as duplicatesIndex } from '@/routes/contacts/duplicates';
import {
    destroy as destroyLabel,
    store as storeLabel,
} from '@/routes/contacts/labels';
import { index as sourcesIndex } from '@/routes/contacts/sources';
import type { ContactFormData } from '@/types/contacts';

interface ContactLabel {
    id: string;
    name: string;
    user_id: string | null;
    imported: boolean;
}
interface Contact {
    id: string;
    type: 'person' | 'organization';
    display_name: string;
    household_id: string | null;
    avatar_url: string | null;
    email: string | null;
    phone: string | null;
    is_favorite: boolean;
    labels: ContactLabel[];
}
const props = defineProps<{
    contacts: Contact[];
    duplicate_count: number;
    households: Array<{ id: string; name: string }>;
    filters: { search: string; label: string | null };
    labels: ContactLabel[];
}>();
setLayoutProps({ breadcrumbs: [{ title: 'Contactos', href: index().url }] });

const { can } = useAuthorization();
const search = ref(props.filters.search);
const contactGroups = computed(() => {
    const favorites = props.contacts.filter((contact) => contact.is_favorite);
    const others = props.contacts.filter((contact) => !contact.is_favorite);

    return [
        ...(favorites.length
            ? [{ title: 'Favoritos', favorite: true, contacts: favorites }]
            : []),
        ...(others.length
            ? [{ title: 'Contactos', favorite: false, contacts: others }]
            : []),
    ];
});
let searchTimeout: ReturnType<typeof setTimeout> | undefined;
const createOpen = ref(false);
const labelOpen = ref(false);
const pendingLabel = ref<ContactLabel | null>(null);
const labelForm = useForm({ name: '' });
const form = useForm<ContactFormData>({
    household_id: null,
    type: 'person',
    display_name: '',
    given_name: '',
    family_name: '',
    additional_name: '',
    nickname: '',
    organization: '',
    job_title: '',
    birthday: '',
    notes: '',
    emails: [{ value: '', type: 'home', preferred: true }],
    phones: [{ value: '', type: 'mobile', preferred: true }],
    addresses: [],
    urls: [],
    dates: [],
    relations: [],
    photo: null,
    remove_photo: false,
});
const contactFields = ref(form);

function reloadContacts(label: string | null = props.filters.label): void {
    router.get(
        index({
            query: {
                search: search.value.trim() || undefined,
                label: label || undefined,
            },
        }).url,
        {},
        {
            only: ['contacts', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function onSearchInput(): void {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => reloadContacts(), 300);
}

function selectLabel(label: string | null): void {
    clearTimeout(searchTimeout);
    reloadContacts(label);
}

onUnmounted(() => clearTimeout(searchTimeout));

function submitLabel(): void {
    labelForm.post(storeLabel().url, {
        preserveScroll: true,
        onSuccess: () => {
            labelOpen.value = false;
            labelForm.reset();
        },
    });
}

function confirmDeleteLabel(): void {
    if (!pendingLabel.value) {
        return;
    }

    router.delete(destroyLabel(pendingLabel.value.id).url, {
        preserveScroll: true,
        onFinish: () => {
            pendingLabel.value = null;
        },
    });
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        emails: data.emails.filter((item) => item.value.trim()),
        phones: data.phones.filter((item) => item.value.trim()),
        addresses: data.addresses.filter((item) => item.value.trim()),
        urls: data.urls.filter((item) => item.value.trim()),
    })).post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Contactos" />
    <div class="px-4 py-6">
        <Heading
            title="Contactos"
            description="Personas y organizaciones privadas o compartidas con el hogar"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1"
                    aria-label="Etiquetas de contactos"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        class="w-full justify-start"
                        :class="{ 'bg-muted': props.filters.label === null }"
                        @click="selectLabel(null)"
                        >Todos los contactos</Button
                    >
                    <div
                        v-for="label in props.labels"
                        :key="label.id"
                        class="flex items-center"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            class="min-w-0 flex-1 justify-start truncate"
                            :class="{
                                'bg-muted': props.filters.label === label.id,
                            }"
                            @click="selectLabel(label.id)"
                            >{{ label.name }}</Button
                        >
                        <IconAction
                            v-if="
                                can('contacts.create') &&
                                label.user_id !== null &&
                                !label.imported
                            "
                            :label="'Eliminar etiqueta ' + label.name"
                            variant="ghost"
                            @click="pendingLabel = label"
                            ><Trash2 aria-hidden="true"
                        /></IconAction>
                    </div>
                    <Button
                        v-if="can('contacts.create')"
                        type="button"
                        variant="ghost"
                        class="w-full justify-start"
                        @click="labelOpen = true"
                        ><Plus data-icon="inline-start" /> Nueva
                        etiqueta</Button
                    >
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="min-w-0 flex-1">
                <section class="space-y-6">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <SearchInput
                            v-model="search"
                            always-open
                            input-class="w-full sm:w-80"
                            placeholder="Buscar contactos..."
                            @update:model-value="onSearchInput"
                        />
                        <div class="flex gap-2">
                            <Button variant="outline" as-child>
                                <Link :href="duplicatesIndex().url"
                                    >Duplicados<span v-if="duplicate_count">
                                        ({{ duplicate_count }})</span
                                    ></Link
                                >
                            </Button>
                            <Button variant="outline" as-child>
                                <Link :href="sourcesIndex().url"
                                    ><Server data-icon="inline-start" />
                                    Fuentes</Link
                                >
                            </Button>
                            <Button
                                v-if="can('contacts.create')"
                                @click="createOpen = true"
                            >
                                <Plus data-icon="inline-start" /> Nuevo contacto
                            </Button>
                        </div>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ contacts.length }}
                        {{ contacts.length === 1 ? 'contacto' : 'contactos' }}
                    </p>
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nombre</TableHead>
                                    <TableHead>Correo electrónico</TableHead>
                                    <TableHead>Número de teléfono</TableHead>
                                    <TableHead>Etiquetas</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template
                                    v-for="group in contactGroups"
                                    :key="group.title"
                                >
                                    <TableRow
                                        class="bg-muted/30 hover:bg-muted/30"
                                    >
                                        <TableCell
                                            colspan="4"
                                            class="py-3 font-medium"
                                        >
                                            <span
                                                class="inline-flex items-center gap-2"
                                            >
                                                <Star
                                                    v-if="group.favorite"
                                                    class="size-4 fill-current"
                                                    aria-hidden="true"
                                                />
                                                {{ group.title }} ({{
                                                    group.contacts.length
                                                }})
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow
                                        v-for="contact in group.contacts"
                                        :key="contact.id"
                                    >
                                        <TableCell>
                                            <Link
                                                :href="show(contact.id).url"
                                                class="flex items-center gap-3 font-medium hover:underline"
                                            >
                                                <ContactAvatar
                                                    :id="contact.id"
                                                    :name="contact.display_name"
                                                    :avatar-url="
                                                        contact.avatar_url
                                                    "
                                                />
                                                <span
                                                    class="min-w-0 truncate"
                                                    >{{
                                                        contact.display_name
                                                    }}</span
                                                >
                                            </Link>
                                        </TableCell>
                                        <TableCell class="max-w-64 truncate">{{
                                            contact.email || '—'
                                        }}</TableCell>
                                        <TableCell>{{
                                            contact.phone || '—'
                                        }}</TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap gap-1">
                                                <Badge
                                                    v-for="label in contact.labels"
                                                    :key="label.id"
                                                    as="button"
                                                    type="button"
                                                    variant="secondary"
                                                    class="cursor-pointer"
                                                    @click="
                                                        selectLabel(label.id)
                                                    "
                                                    >{{ label.name }}</Badge
                                                >
                                                <span
                                                    v-if="
                                                        contact.labels
                                                            .length === 0
                                                    "
                                                    class="text-muted-foreground"
                                                    >—</span
                                                >
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                        <Empty v-if="contacts.length === 0">
                            <EmptyHeader>
                                <EmptyTitle>Sin contactos</EmptyTitle>
                                <EmptyDescription
                                    v-if="
                                        props.filters.search ||
                                        props.filters.label
                                    "
                                >
                                    No hay contactos que coincidan con los
                                    filtros.
                                </EmptyDescription>
                                <EmptyDescription v-else>
                                    Crea un contacto o conecta una fuente
                                    CardDAV.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </div>
                </section>
            </div>
        </div>

        <Dialog v-model:open="labelOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Nueva etiqueta</DialogTitle>
                    <DialogDescription
                        >Crea una etiqueta para organizar tus
                        contactos.</DialogDescription
                    >
                </DialogHeader>
                <form id="contact-label-form" @submit.prevent="submitLabel">
                    <label for="contact-label-name" class="text-sm font-medium"
                        >Nombre</label
                    >
                    <Input
                        id="contact-label-name"
                        v-model="labelForm.name"
                        maxlength="80"
                        required
                    />
                    <p
                        v-if="labelForm.errors.name"
                        class="text-sm text-destructive"
                    >
                        {{ labelForm.errors.name }}
                    </p>
                </form>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="labelOpen = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        form="contact-label-form"
                        :disabled="labelForm.processing"
                        >Crear</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog
            :open="pendingLabel !== null"
            @update:open="pendingLabel = $event ? pendingLabel : null"
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >¿Eliminar {{ pendingLabel?.name }}?</AlertDialogTitle
                    >
                    <AlertDialogDescription>
                        La etiqueta desaparecerá de los contactos, que se
                        conservarán.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="pendingLabel = null"
                        >Cancelar</AlertDialogCancel
                    >
                    <AlertDialogAction @click="confirmDeleteLabel"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
        <Dialog v-model:open="createOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader
                    ><DialogTitle>Nuevo contacto</DialogTitle
                    ><DialogDescription
                        >Rellena los datos que quieras conservar de este
                        contacto.</DialogDescription
                    ></DialogHeader
                >
                <form
                    id="contact-create-form"
                    class="flex flex-col gap-5"
                    @submit.prevent="submit"
                >
                    <ContactFormFields
                        v-model="contactFields"
                        :households="props.households"
                    />
                </form>
                <DialogFooter
                    ><Button
                        type="button"
                        variant="outline"
                        @click="createOpen = false"
                        >Cancelar</Button
                    ><Button
                        type="submit"
                        form="contact-create-form"
                        :disabled="form.processing"
                        >Guardar</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </div>
</template>
