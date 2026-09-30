<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { lookup } from '@/routes/contacts';

const { t } = useI18n();

const props = defineProps<{
    householdId: string;
}>();

/**
 * Contact returned by the lookup endpoint used for the email autocomplete.
 */
type ContactOption = {
    id: string;
    display_name: string;
    avatar_url: string | null;
    emails: string[];
};

const open = ref(false);

const form = useForm({
    email: '',
});

const results = ref<ContactOption[]>([]);
const showResults = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | undefined;

/**
 * Search the viewer's saved contacts matching the typed email.
 */
function searchContacts(): void {
    const needle = form.email.trim();

    if (needle.length < 2) {
        results.value = [];
        showResults.value = false;

        return;
    }

    fetch(
        lookup({
            query: {
                q: needle,
                by: 'email',
            },
        }).url,
        { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
    )
        .then((response) => (response.ok ? response.json() : { contacts: [] }))
        .then((payload: { contacts: ContactOption[] }) => {
            results.value = payload.contacts;
            showResults.value = payload.contacts.length > 0;
        });
}

/**
 * Debounced trigger for the contact email search.
 */
function onEmailInput(): void {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(searchContacts, 250);
}

/**
 * Fill the invitation email from a picked contact.
 */
function pick(contact: ContactOption): void {
    form.email = contact.emails[0] ?? contact.display_name;
    showResults.value = false;
    results.value = [];
}

function submit() {
    showResults.value = false;
    form.post(`/households/${props.householdId}/invite`, {
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">
                {{ t('households.invite.trigger') }}
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('households.invite.title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('households.invite.description') }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submit" class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        :placeholder="t('households.invite.emailPlaceholder')"
                        :class="{ 'border-destructive': form.errors.email }"
                        @input="onEmailInput"
                        @blur="showResults = false"
                    />
                    <ul
                        v-if="showResults && results.length"
                        class="max-h-48 overflow-auto rounded-md border bg-popover shadow-sm"
                    >
                        <li v-for="contact in results" :key="contact.id">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                                @mousedown.prevent="pick(contact)"
                            >
                                <span class="font-medium">{{
                                    contact.display_name
                                }}</span>
                                <span
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ contact.emails[0] }}
                                </span>
                            </button>
                        </li>
                    </ul>
                    <p
                        v-if="form.errors.email"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{ t('households.invite.submit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
