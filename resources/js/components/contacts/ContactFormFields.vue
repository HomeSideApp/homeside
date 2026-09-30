<script setup lang="ts">
import {
    Building2,
    CalendarDays,
    Camera,
    Link2,
    Mail,
    MapPin,
    Phone,
    Plus,
    Search,
    StickyNote,
    Trash2,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
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
import { Textarea } from '@/components/ui/textarea';
import { lookup } from '@/routes/contacts';
import type { ContactFormData } from '@/types/contacts';

const form = defineModel<ContactFormData & { errors: Record<string, string> }>({
    required: true,
});
defineProps<{
    households?: Array<{ id: string; name: string }>;
    avatarUrl?: string | null;
    editing?: boolean;
    canRemovePhoto?: boolean;
    hidePhoto?: boolean;
}>();

const valueSections = [
    {
        kind: 'emails',
        title: 'Correos electrónicos',
        singular: 'Correo electrónico',
        add: 'Añadir correo',
        icon: Mail,
        inputType: 'email',
    },
    {
        kind: 'phones',
        title: 'Teléfonos',
        singular: 'Teléfono',
        add: 'Añadir teléfono',
        icon: Phone,
        inputType: 'tel',
    },
    {
        kind: 'addresses',
        title: 'Direcciones',
        singular: 'Dirección',
        add: 'Añadir dirección',
        icon: MapPin,
        inputType: 'text',
    },
    {
        kind: 'urls',
        title: 'Sitios web',
        singular: 'Sitio web',
        add: 'Añadir sitio web',
        icon: Link2,
        inputType: 'url',
    },
] as const;

interface ContactOption {
    id: string;
    display_name: string;
}
const suggestions = ref<Record<number, ContactOption[]>>({});
const searching = ref<number | null>(null);

async function searchContact(index: number): Promise<void> {
    const query = form.value.relations[index]?.name?.trim();

    if (!query || query.length < 2) {
        suggestions.value[index] = [];

        return;
    }

    searching.value = index;

    try {
        const response = await fetch(
            lookup({
                query: {
                    q: query,
                    scope: form.value.household_id ?? undefined,
                },
            }).url,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            suggestions.value[index] = (
                (await response.json()) as { contacts: ContactOption[] }
            ).contacts;
        }
    } catch {
        suggestions.value[index] = [];
    } finally {
        searching.value = null;
    }
}

function selectContact(index: number, contact: ContactOption): void {
    const relation = form.value.relations[index];

    if (relation) {
        relation.related_contact_id = contact.id;
        relation.name = contact.display_name;
        suggestions.value[index] = [];
    }
}

function setPhoto(event: Event): void {
    form.value.photo = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (form.value.photo) {
        form.value.remove_photo = false;
    }
}
</script>

<template>
    <div class="flex flex-col gap-7">
        <section
            class="grid gap-3 border-b pb-7 sm:grid-cols-[1.25rem_minmax(0,1fr)] sm:gap-4"
        >
            <UserRound
                class="mt-0.5 hidden size-5 text-muted-foreground sm:block"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h2 class="mb-4 text-sm font-semibold">Identidad</h2>
                <FieldGroup class="gap-4">
                    <Field v-if="!editing && households?.length">
                        <FieldLabel for="contact-scope">Visibilidad</FieldLabel>
                        <Select v-model="form.household_id">
                            <SelectTrigger id="contact-scope">
                                <SelectValue placeholder="Privado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="null"
                                        >Privado</SelectItem
                                    >
                                    <SelectItem
                                        v-for="household in households"
                                        :key="household.id"
                                        :value="household.id"
                                    >
                                        {{ household.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <FieldError v-if="form.errors.household_id">{{
                            form.errors.household_id
                        }}</FieldError>
                    </Field>
                    <Field>
                        <FieldLabel for="contact-type">Tipo</FieldLabel>
                        <Select v-model="form.type">
                            <SelectTrigger id="contact-type"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="person"
                                        >Persona</SelectItem
                                    >
                                    <SelectItem value="organization"
                                        >Organización</SelectItem
                                    >
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field :data-invalid="Boolean(form.errors.display_name)">
                        <FieldLabel for="contact-name"
                            >Nombre mostrado</FieldLabel
                        >
                        <Input
                            id="contact-name"
                            v-model="form.display_name"
                            required
                            :aria-invalid="Boolean(form.errors.display_name)"
                        />
                        <FieldError v-if="form.errors.display_name">{{
                            form.errors.display_name
                        }}</FieldError>
                    </Field>
                    <template v-if="form.type === 'person'">
                        <Field>
                            <FieldLabel for="contact-given">Nombre</FieldLabel>
                            <Input
                                id="contact-given"
                                v-model="form.given_name"
                            />
                        </Field>
                        <Field>
                            <FieldLabel for="contact-additional"
                                >Segundo nombre</FieldLabel
                            >
                            <Input
                                id="contact-additional"
                                v-model="form.additional_name"
                            />
                        </Field>
                        <Field>
                            <FieldLabel for="contact-family"
                                >Apellidos</FieldLabel
                            >
                            <Input
                                id="contact-family"
                                v-model="form.family_name"
                            />
                        </Field>
                        <Field>
                            <FieldLabel for="contact-nickname"
                                >Apodo</FieldLabel
                            >
                            <Input
                                id="contact-nickname"
                                v-model="form.nickname"
                            />
                        </Field>
                    </template>
                </FieldGroup>
            </div>
        </section>

        <section
            class="grid gap-3 border-b pb-7 sm:grid-cols-[1.25rem_minmax(0,1fr)] sm:gap-4"
        >
            <Building2
                class="mt-0.5 hidden size-5 text-muted-foreground sm:block"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h2 class="mb-4 text-sm font-semibold">Trabajo</h2>
                <FieldGroup class="gap-4">
                    <Field>
                        <FieldLabel for="contact-org">Organización</FieldLabel>
                        <Input id="contact-org" v-model="form.organization" />
                    </Field>
                    <Field>
                        <FieldLabel for="contact-title">Cargo</FieldLabel>
                        <Input id="contact-title" v-model="form.job_title" />
                    </Field>
                </FieldGroup>
            </div>
        </section>

        <section
            v-for="section in valueSections"
            :key="section.kind"
            class="grid gap-3 border-b pb-7 sm:grid-cols-[1.25rem_minmax(0,1fr)] sm:gap-4"
        >
            <component
                :is="section.icon"
                class="mt-0.5 hidden size-5 text-muted-foreground sm:block"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h2 class="mb-4 text-sm font-semibold">{{ section.title }}</h2>
                <FieldGroup class="gap-4">
                    <Field
                        v-for="(value, valueIndex) in form[section.kind]"
                        :key="valueIndex"
                        :data-invalid="
                            Boolean(
                                form.errors[
                                    `${section.kind}.${valueIndex}.value`
                                ],
                            )
                        "
                    >
                        <FieldLabel :for="`${section.kind}-${valueIndex}`"
                            >{{ section.singular }}
                            {{ valueIndex + 1 }}</FieldLabel
                        >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <Input
                                :id="`${section.kind}-${valueIndex}`"
                                v-model="value.value"
                                :type="section.inputType"
                                :aria-invalid="
                                    Boolean(
                                        form.errors[
                                            `${section.kind}.${valueIndex}.value`
                                        ],
                                    )
                                "
                                class="min-w-0 flex-1"
                            />
                            <Input
                                :model-value="value.type ?? ''"
                                :aria-label="`Etiqueta del ${section.singular.toLowerCase()} ${valueIndex + 1}`"
                                @update:model-value="
                                    value.type = String($event)
                                "
                                class="sm:w-32"
                                placeholder="Etiqueta"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Quitar ${section.singular.toLowerCase()} ${valueIndex + 1}`"
                                @click="
                                    form[section.kind].splice(valueIndex, 1)
                                "
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </div>
                        <FieldError
                            v-if="
                                form.errors[
                                    `${section.kind}.${valueIndex}.value`
                                ]
                            "
                        >
                            {{
                                form.errors[
                                    `${section.kind}.${valueIndex}.value`
                                ]
                            }}
                        </FieldError>
                        <label
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Checkbox
                                :model-value="value.preferred"
                                @update:model-value="
                                    value.preferred = $event === true
                                "
                            />
                            Preferido
                        </label>
                    </Field>
                </FieldGroup>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="mt-3"
                    @click="
                        form[section.kind].push({
                            value: '',
                            type: null,
                            preferred: false,
                        })
                    "
                >
                    <Plus class="size-4" aria-hidden="true" />
                    {{ section.add }}
                </Button>
            </div>
        </section>

        <section
            class="grid gap-3 border-b pb-7 sm:grid-cols-[1.25rem_minmax(0,1fr)] sm:gap-4"
        >
            <CalendarDays
                class="mt-0.5 hidden size-5 text-muted-foreground sm:block"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h2 class="mb-4 text-sm font-semibold">Fechas de interés</h2>
                <FieldGroup class="gap-4">
                    <Field v-if="form.type === 'person'">
                        <FieldLabel for="contact-birthday"
                            >Cumpleaños</FieldLabel
                        >
                        <Input
                            id="contact-birthday"
                            v-model="form.birthday"
                            type="date"
                        />
                        <FieldError v-if="form.errors.birthday">{{
                            form.errors.birthday
                        }}</FieldError>
                    </Field>
                    <Field
                        v-for="(date, dateIndex) in form.dates"
                        :key="dateIndex"
                    >
                        <FieldLabel :for="`contact-date-${dateIndex}`"
                            >Fecha {{ dateIndex + 1 }}</FieldLabel
                        >
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Select v-model="date.kind">
                                <SelectTrigger
                                    :aria-label="`Tipo de fecha ${dateIndex + 1}`"
                                    class="sm:w-40"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="anniversary"
                                            >Aniversario</SelectItem
                                        >
                                        <SelectItem value="custom"
                                            >Otra fecha</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Input
                                :id="`contact-date-${dateIndex}`"
                                v-model="date.value"
                                type="date"
                                class="min-w-0 flex-1"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Quitar fecha ${dateIndex + 1}`"
                                @click="form.dates.splice(dateIndex, 1)"
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </div>
                        <Input
                            v-if="date.kind === 'custom'"
                            :model-value="date.label ?? ''"
                            :aria-label="`Nombre de la fecha ${dateIndex + 1}`"
                            @update:model-value="date.label = String($event)"
                            placeholder="Nombre de la fecha"
                        />
                        <FieldError
                            v-if="form.errors[`dates.${dateIndex}.value`]"
                            >{{
                                form.errors[`dates.${dateIndex}.value`]
                            }}</FieldError
                        >
                    </Field>
                </FieldGroup>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="mt-3"
                    @click="
                        form.dates.push({
                            kind: 'custom',
                            label: '',
                            value: '',
                        })
                    "
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Añadir fecha
                </Button>
            </div>
        </section>

        <section
            class="grid gap-3 border-b pb-7 sm:grid-cols-[1.25rem_minmax(0,1fr)] sm:gap-4"
        >
            <UsersRound
                class="mt-0.5 hidden size-5 text-muted-foreground sm:block"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h2 class="mb-4 text-sm font-semibold">Relaciones</h2>
                <FieldGroup class="gap-4">
                    <Field
                        v-for="(relation, relationIndex) in form.relations"
                        :key="relationIndex"
                    >
                        <FieldLabel :for="`contact-relation-${relationIndex}`"
                            >Relación {{ relationIndex + 1 }}</FieldLabel
                        >
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <Select v-model="relation.type">
                                <SelectTrigger
                                    :aria-label="`Tipo de relación ${relationIndex + 1}`"
                                    class="sm:w-44"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="parent"
                                            >Padre o madre</SelectItem
                                        >
                                        <SelectItem value="child"
                                            >Hijo o hija</SelectItem
                                        >
                                        <SelectItem value="sibling"
                                            >Hermano o hermana</SelectItem
                                        >
                                        <SelectItem value="spouse"
                                            >Pareja</SelectItem
                                        >
                                        <SelectItem value="friend"
                                            >Amigo</SelectItem
                                        >
                                        <SelectItem value="colleague"
                                            >Compañero</SelectItem
                                        >
                                        <SelectItem value="emergency"
                                            >Emergencia</SelectItem
                                        >
                                        <SelectItem value="other"
                                            >Otra</SelectItem
                                        >
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <Input
                                :id="`contact-relation-${relationIndex}`"
                                :model-value="relation.name ?? ''"
                                @update:model-value="
                                    relation.name = String($event)
                                "
                                @input="relation.related_contact_id = null"
                                class="min-w-0 flex-1"
                                placeholder="Nombre o busca un contacto"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                :aria-label="`Buscar contacto para relación ${relationIndex + 1}`"
                                :disabled="searching === relationIndex"
                                @click="searchContact(relationIndex)"
                            >
                                <Search aria-hidden="true" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="`Quitar relación ${relationIndex + 1}`"
                                @click="form.relations.splice(relationIndex, 1)"
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </div>
                        <p
                            v-if="relation.related_contact_id"
                            class="text-xs text-muted-foreground"
                        >
                            Vinculado a un contacto de la aplicación
                        </p>
                        <div
                            v-if="suggestions[relationIndex]?.length"
                            class="flex flex-col rounded-md border"
                        >
                            <button
                                v-for="candidate in suggestions[relationIndex]"
                                :key="candidate.id"
                                type="button"
                                class="p-2 text-left hover:bg-accent"
                                @click="selectContact(relationIndex, candidate)"
                            >
                                {{ candidate.display_name }}
                            </button>
                        </div>
                        <FieldError
                            v-if="
                                form.errors[
                                    `relations.${relationIndex}.related_contact_id`
                                ]
                            "
                            >{{
                                form.errors[
                                    `relations.${relationIndex}.related_contact_id`
                                ]
                            }}</FieldError
                        >
                        <FieldError
                            v-if="
                                form.errors[`relations.${relationIndex}.name`]
                            "
                            >{{
                                form.errors[`relations.${relationIndex}.name`]
                            }}</FieldError
                        >
                    </Field>
                </FieldGroup>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="mt-3"
                    @click="
                        form.relations.push({
                            type: 'other',
                            related_contact_id: null,
                            name: '',
                        })
                    "
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Añadir relación
                </Button>
            </div>
        </section>

        <section
            class="grid gap-3 border-b pb-7 sm:grid-cols-[1.25rem_minmax(0,1fr)] sm:gap-4"
        >
            <StickyNote
                class="mt-0.5 hidden size-5 text-muted-foreground sm:block"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <Field>
                    <FieldLabel for="contact-notes">Notas</FieldLabel>
                    <Textarea
                        id="contact-notes"
                        v-model="form.notes"
                        rows="4"
                    />
                    <FieldError v-if="form.errors.notes">{{
                        form.errors.notes
                    }}</FieldError>
                </Field>
            </div>
        </section>

        <section
            v-if="!hidePhoto"
            class="grid gap-3 sm:grid-cols-[1.25rem_minmax(0,1fr)] sm:gap-4"
        >
            <Camera
                class="mt-0.5 hidden size-5 text-muted-foreground sm:block"
                aria-hidden="true"
            />
            <Field>
                <FieldLabel for="contact-photo">Foto</FieldLabel>
                <img
                    v-if="avatarUrl && !form.remove_photo"
                    :src="avatarUrl"
                    alt=""
                    class="size-16 rounded-full object-cover"
                />
                <Input
                    id="contact-photo"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    @change="setPhoto"
                />
                <p class="text-xs text-muted-foreground">
                    JPEG, PNG o WebP, hasta 3 MB.
                </p>
                <label
                    v-if="editing && avatarUrl && canRemovePhoto"
                    class="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="form.remove_photo"
                        :disabled="form.photo !== null"
                        @update:model-value="
                            form.remove_photo = $event === true
                        "
                    />
                    Eliminar foto actual
                </label>
                <FieldError v-if="form.errors.photo">{{
                    form.errors.photo
                }}</FieldError>
            </Field>
        </section>
    </div>
</template>
