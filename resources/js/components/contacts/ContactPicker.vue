<script setup lang="ts">
import { ContactRound, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { lookup } from '@/routes/contacts';

/**
 * Lightweight contact picker backed by the contacts lookup endpoint.
 *
 * Used where a contact must be linked to another entity (transactions, invitations):
 * the caller owns the selected id through `modelValue`.
 */
export interface ContactOption {
    id: string;
    display_name: string;
    avatar_url: string | null;
    emails: string[];
}

const model = defineModel<string | null>({ required: true });
const props = defineProps<{
    label?: string;
    placeholder?: string;
    error?: string;
    /** Restrict the search to the contacts of a single household. */
    scope?: string | null;
    /** Prefill shown when only the id is known. */
    selectedName?: string | null;
}>();

const query = ref('');
const selected = ref<ContactOption | null>(
    props.selectedName
        ? { id: model.value ?? '', display_name: props.selectedName, avatar_url: null, emails: [] }
        : null,
);
const results = ref<ContactOption[]>([]);
const searching = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

function search(): void {
    const needle = query.value.trim();

    if (needle.length < 2) {
        results.value = [];

        return;
    }

    searching.value = true;

    fetch(
        lookup({
            query: {
                q: needle,
                scope: props.scope ?? undefined,
            },
        }).url,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
    )
        .then((response) => (response.ok ? response.json() : { contacts: [] }))
        .then((payload: { contacts: ContactOption[] }) => {
            results.value = payload.contacts.filter(
                (contact) => contact.id !== model.value,
            );
        })
        .finally(() => {
            searching.value = false;
        });
}

function onInput(): void {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(search, 250);
}

function pick(contact: ContactOption): void {
    selected.value = contact;
    model.value = contact.id;
    query.value = '';
    results.value = [];
}

function clear(): void {
    selected.value = null;
    model.value = null;
    query.value = '';
    results.value = [];
}

watch(model, (value) => {
    if (value === null) {
        selected.value = null;
    }
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <label v-if="props.label" class="text-sm font-medium">{{
            props.label
        }}</label>

        <div
            v-if="selected"
            class="flex items-center justify-between gap-2 rounded-md border px-3 py-2"
        >
            <span class="flex min-w-0 items-center gap-2">
                <img
                    v-if="selected.avatar_url"
                    :src="selected.avatar_url"
                    alt=""
                    class="size-7 rounded-full object-cover"
                />
                <ContactRound
                    v-else
                    class="size-5 text-muted-foreground"
                    aria-hidden="true"
                />
                <span class="min-w-0 truncate text-sm font-medium">{{
                    selected.display_name
                }}</span>
                <Badge
                    v-if="selected.emails[0]"
                    variant="secondary"
                    class="hidden sm:inline-flex"
                    >{{ selected.emails[0] }}</Badge
                >
            </span>
            <Button
                type="button"
                variant="ghost"
                size="icon-sm"
                :aria-label="'Quitar contacto'"
                @click="clear"
            >
                <X class="size-4" aria-hidden="true" />
            </Button>
        </div>

        <div v-else class="relative">
            <Input
                v-model="query"
                type="search"
                :placeholder="props.placeholder ?? 'Buscar contacto...'"
                :class="{ 'border-destructive': props.error }"
                :aria-invalid="props.error ? 'true' : undefined"
                @input="onInput"
            />
            <ul
                v-if="results.length > 0"
                class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <li v-for="contact in results" :key="contact.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="pick(contact)"
                    >
                        <img
                            v-if="contact.avatar_url"
                            :src="contact.avatar_url"
                            alt=""
                            class="size-6 rounded-full object-cover"
                        />
                        <ContactRound
                            v-else
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 truncate">{{
                            contact.display_name
                        }}</span>
                        <span
                            v-if="contact.emails[0]"
                            class="ml-auto truncate text-xs text-muted-foreground"
                            >{{ contact.emails[0] }}</span
                        >
                    </button>
                </li>
            </ul>
            <p
                v-else-if="searching"
                class="text-xs text-muted-foreground"
            >
                Buscando...
            </p>
        </div>

        <p v-if="props.error" class="text-sm text-destructive">
            {{ props.error }}
        </p>
    </div>
</template>
