<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Plus, Star, Trash2 } from '@lucide/vue';
import { onUnmounted, ref } from 'vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAuthorization } from '@/composables/useAuthorization';
import { destroy, edit, show, store } from '@/routes/contacts';
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
    household: { id: string; name: string };
    contacts: Contact[];
    filters: { search: string; label: string | null };
    labels: ContactLabel[];
    households: Array<{ id: string; name: string }>;
}>();

const pageUrl = `/households/${props.household.id}/contacts`;
setLayoutProps({
    breadcrumbs: [
        { title: 'nav.households', href: '/households' },
        {
            title: props.household.name,
            href: `/households/${props.household.id}`,
        },
        { title: 'Contactos del hogar' },
    ],
});

const { can } = useAuthorization();
const search = ref(props.filters.search);
let searchTimeout: ReturnType<typeof setTimeout> | undefined;
const createOpen = ref(false);
const pendingDelete = ref<Contact | null>(null);

const form = useForm<ContactFormData>({
    household_id: props.household.id,
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

/**
 * Reload the household contacts table with the current search and label filters.
 */
function reloadContacts(label: string | null = props.filters.label): void {
    router.get(
        pageUrl,
        {
            search: search.value.trim() || undefined,
            label: label || undefined,
        },
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

/**
 * Store the new household contact and return to this page.
 */
function submit(): void {
    form.transform((data) => ({
        ...data,
        emails: data.emails.filter((item) => item.value.trim()),
        phones: data.phones.filter((item) => item.value.trim()),
        addresses: data.addresses.filter((item) => item.value.trim()),
        urls: data.urls.filter((item) => item.value.trim()),
    })).post(`${store().url}?back=household`, {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            form.reset();
            form.household_id = props.household.id;
        },
    });
}

/**
 * Delete the contact selected in the confirmation dialog.
 */
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
    <Head :title="`Contactos · ${household.name}`" />
    <div class="px-4 py-6">
        <Heading
            title="Contactos del hogar"
            description="Personas y organizaciones compartidas con este hogar"
        />

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
                <Button
                    v-if="can('contacts.create') && can('contacts.home.create')"
                    @click="createOpen = true"
                >
                    <Plus data-icon="inline-start" /> Nuevo contacto
                </Button>
            </div>

            <div
                v-if="props.labels.length"
                class="flex flex-wrap items-center gap-1"
            >
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    :class="{ 'bg-muted': props.filters.label === null }"
                    @click="selectLabel(null)"
                    >Todos</Button
                >
                <Badge
                    v-for="label in props.labels"
                    :key="label.id"
                    as="button"
                    type="button"
                    variant="secondary"
                    class="cursor-pointer"
                    :class="{
                        'ring-1 ring-ring': props.filters.label === label.id,
                    }"
                    @click="selectLabel(label.id)"
                    >{{ label.name }}</Badge
                >
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
                            <TableHead class="text-right">Acciones</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="contact in contacts" :key="contact.id">
                            <TableCell>
                                <Link
                                    :href="show(contact.id).url"
                                    class="flex items-center gap-3 font-medium hover:underline"
                                >
                                    <ContactAvatar
                                        :id="contact.id"
                                        :name="contact.display_name"
                                        :avatar-url="contact.avatar_url"
                                    />
                                    <span class="min-w-0 truncate">{{
                                        contact.display_name
                                    }}</span>
                                    <Star
                                        v-if="contact.is_favorite"
                                        class="size-3 fill-current text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </TableCell>
                            <TableCell class="max-w-64 truncate">{{
                                contact.email || '—'
                            }}</TableCell>
                            <TableCell>{{ contact.phone || '—' }}</TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="label in contact.labels"
                                        :key="label.id"
                                        as="button"
                                        type="button"
                                        variant="secondary"
                                        class="cursor-pointer"
                                        @click="selectLabel(label.id)"
                                        >{{ label.name }}</Badge
                                    >
                                    <span
                                        v-if="contact.labels.length === 0"
                                        class="text-muted-foreground"
                                        >—</span
                                    >
                                </div>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        v-if="
                                            can('contacts.update') ||
                                            can('contacts.home.update')
                                        "
                                        variant="ghost"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="edit(contact.id).url"
                                            >Editar</Link
                                        >
                                    </Button>
                                    <Button
                                        v-if="
                                            can('contacts.delete') ||
                                            can('contacts.home.delete')
                                        "
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="pendingDelete = contact"
                                    >
                                        <Trash2
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <Empty v-if="contacts.length === 0">
                    <EmptyHeader>
                        <EmptyTitle>Sin contactos</EmptyTitle>
                        <EmptyDescription
                            v-if="props.filters.search || props.filters.label"
                        >
                            No hay contactos que coincidan con los filtros.
                        </EmptyDescription>
                        <EmptyDescription v-else>
                            Crea un contacto o comparte uno existente con el
                            hogar.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            </div>
        </section>

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

        <AlertDialog
            :open="pendingDelete !== null"
            @update:open="pendingDelete = $event ? pendingDelete : null"
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >¿Eliminar
                        {{ pendingDelete?.display_name }}?</AlertDialogTitle
                    >
                    <AlertDialogDescription>
                        El contacto se eliminará de la libreta del hogar.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="pendingDelete = null"
                        >Cancelar</AlertDialogCancel
                    >
                    <AlertDialogAction @click="confirmDelete"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
