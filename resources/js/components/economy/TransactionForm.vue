<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ContactPicker from '@/components/contacts/ContactPicker.vue';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldContent,
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
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useEconomyDestinations } from '@/composables/useEconomyDestinations';
import {
    store as personalStore,
    update as personalUpdate,
} from '@/routes/economy/me';
import {
    store as householdStore,
    update as householdUpdate,
} from '@/routes/households/economy';
import ItemEditor from './ItemEditor.vue';
import ParticipantSelector from './ParticipantSelector.vue';
import TaxEditor from './TaxEditor.vue';
import type {
    EconomicAccount,
    EconomyItem,
    EconomyMember,
    EconomyParticipant,
    EconomyTax,
    EconomyTransaction,
    RecurrenceFrequency,
} from './types';

/**
 * Radix Select cannot carry a `null` value, so the private scope travels as a sentinel.
 */
const PRIVATE_DESTINATION = '__private__';

const { t } = useI18n();
const props = defineProps<{
    household: { id: string; name: string } | null;
    transaction?: EconomyTransaction | null;
    members: EconomyMember[];
    membersByHousehold?: Record<string, EconomyMember[]>;
    splitTypesByHousehold?: Record<string, 'equal' | 'fixed' | 'percentage'>;
    accounts?: EconomicAccount[];
}>();

const availableAccounts = computed<EconomicAccount[]>(
    () => props.accounts ?? [],
);
const selectedAccount = computed(() =>
    availableAccounts.value.find((account) => account.id === form.account_id),
);
const amountStep = computed(() =>
    selectedAccount.value
        ? String(1 / 10 ** selectedAccount.value.decimal_places)
        : '0.01',
);
const isEditing = Boolean(props.transaction?.id);
const isGeneratedOccurrence = Boolean(props.transaction?.recurrence_parent_id);
const recurrenceEnabled = ref(Boolean(props.transaction?.recurrence_frequency));
const weekdayOptions = [
    { value: 1, key: 'monday' },
    { value: 2, key: 'tuesday' },
    { value: 3, key: 'wednesday' },
    { value: 4, key: 'thursday' },
    { value: 5, key: 'friday' },
    { value: 6, key: 'saturday' },
    { value: 7, key: 'sunday' },
] as const;

function isoWeekdayFromDate(date: string | null | undefined): number {
    if (!date) {
        const today = new Date();
        const weekday = today.getDay();

        return weekday === 0 ? 7 : weekday;
    }

    const [year, month, day] = date.slice(0, 10).split('-').map(Number);
    const weekday = new Date(year, month - 1, day).getDay();

    return weekday === 0 ? 7 : weekday;
}

function localTodayDate(): string {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/**
 * Destination selector state.
 *
 * A context route (household economy) locks the destination to that household; elsewhere the
 * selector is editable and defaults to the user's preferred destination, so the same form serves
 * private and every household without duplicating screens.
 */
const { destinations, defaultDestinationId } = useEconomyDestinations();
const destinationLocked = computed(() => props.household !== null);
const destination = ref<string>(
    props.household?.id ??
        (defaultDestinationId.value === null
            ? PRIVATE_DESTINATION
            : defaultDestinationId.value),
);

const destinationOptions = computed(() => {
    if (destinationLocked.value) {
        return destinations.value.filter(
            (option) => option.id === props.household!.id,
        );
    }

    return destinations.value;
});

const isHouseholdDestination = computed(
    () => destination.value !== PRIVATE_DESTINATION,
);

const currentHouseholdId = computed<string | null>(() =>
    isHouseholdDestination.value ? destination.value : null,
);

const submitUrl = computed(() =>
    isEditing
        ? currentHouseholdId.value
            ? householdUpdate.url({
                  household: currentHouseholdId.value,
                  transaction: props.transaction!.id,
              })
            : personalUpdate.url(props.transaction!.id)
        : currentHouseholdId.value
          ? householdStore.url(currentHouseholdId.value)
          : personalStore.url(),
);

/**
 * Members of the destination household, needed by the participant selector. Members of households
 * the user is not currently viewing are provided by the controller in one eager-loaded query.
 */
const destinationMembers = computed<EconomyMember[]>(() =>
    currentHouseholdId.value
        ? (props.membersByHousehold?.[currentHouseholdId.value] ??
          props.members)
        : [],
);

/**
 * The split default configured for the current destination household, falling back to equal
 * shares so the selector stays usable when no household is selected yet.
 */
const destinationSplitType = computed<'equal' | 'fixed' | 'percentage'>(
    () =>
        (currentHouseholdId.value
            ? props.splitTypesByHousehold?.[currentHouseholdId.value]
            : undefined) ?? 'equal',
);

/**
 * Switching destination changes the scope options that make sense: a private transaction is always
 * personal, so participants are cleared and scope forced back to `personal`.
 */
function onDestinationChange(): void {
    if (!isHouseholdDestination.value) {
        form.scope = 'personal';
        form.participants = [];
    }

    form.validate('scope');
}
const items: EconomyItem[] = (props.transaction?.items ?? []).map((item) => ({
    ...item,
}));
const taxes: EconomyTax[] = (props.transaction?.taxes ?? []).map((tax) => ({
    ...tax,
    amount: tax.tax_amount,
}));
const participants: EconomyParticipant[] = (
    props.transaction?.participants ?? []
).map((participant) => ({
    household_member_id: participant.household_member.id,
    split_type: participant.split_type,
    amount: participant.amount,
    percentage: participant.percentage,
}));

const form = useForm(isEditing ? 'put' : 'post', () => submitUrl.value, {
    type: props.transaction?.type ?? 'expense',
    scope: props.household
        ? (props.transaction?.scope ?? 'personal')
        : 'personal',
    title: props.transaction?.title ?? '',
    amount: props.transaction?.amount ?? '',
    currency: props.transaction?.currency ?? 'EUR',
    account_id: props.transaction?.account?.id ?? '',
    place: props.transaction?.place ?? '',
    occurred_at:
        props.transaction?.occurred_at?.slice(0, 10) ??
        (isEditing ? '' : localTodayDate()),
    recurrence_frequency:
        props.transaction?.recurrence_frequency ??
        (null as RecurrenceFrequency | null),
    recurrence_interval: String(props.transaction?.recurrence_interval ?? 1),
    recurrence_weekdays:
        props.transaction?.recurrence_weekdays ??
        (props.transaction?.recurrence_frequency === 'weekly'
            ? [isoWeekdayFromDate(props.transaction.occurred_at)]
            : []),
    recurrence_ends_at: props.transaction?.recurrence_ends_at ?? '',
    notes: props.transaction?.notes ?? '',
    contact_id: props.transaction?.contact?.id ?? null,
    contact_name: props.transaction?.contact?.display_name ?? null,
    items,
    taxes,
    participants,
});

function validateField(field?: string): void {
    if (field) {
        form.validate(field as never);

        return;
    }

    form.validate();
}

function setOccurredAtToToday(): void {
    form.occurred_at = localTodayDate();
    form.validate('occurred_at');
}

function toggleRecurrence(enabled: boolean): void {
    recurrenceEnabled.value = enabled;

    if (enabled) {
        form.recurrence_frequency ??= 'monthly';

        if (!form.occurred_at) {
            setOccurredAtToToday();
        }

        return;
    }

    form.recurrence_frequency = null;
    form.recurrence_interval = '1';
    form.recurrence_weekdays = [];
    form.recurrence_ends_at = '';
    form.clearErrors(
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_weekdays',
        'recurrence_ends_at',
    );
}

function toggleWeekday(weekday: number, selected: boolean): void {
    form.recurrence_weekdays = selected
        ? [...new Set([...form.recurrence_weekdays, weekday])].sort(
              (first, second) => first - second,
          )
        : form.recurrence_weekdays.filter((value) => value !== weekday);
    form.validate('recurrence_weekdays');
}

watch(
    () => form.recurrence_frequency,
    (frequency) => {
        if (frequency === 'weekly' && form.recurrence_weekdays.length === 0) {
            form.recurrence_weekdays = [isoWeekdayFromDate(form.occurred_at)];
        }

        form.validate('recurrence_frequency');
        form.validate('recurrence_weekdays');
    },
);

watch(
    () => form.scope,
    (scope) => {
        if (scope === 'personal') {
            form.participants = [];
        }
    },
);
</script>

<template>
    <form class="max-w-4xl" @submit.prevent="form.submit()">
        <Card>
            <CardHeader
                ><CardTitle class="text-base">{{
                    t('economy.transaction.formTitle')
                }}</CardTitle></CardHeader
            >
            <CardContent class="flex flex-col gap-6">
                <Alert v-if="form.hasErrors" variant="destructive">
                    <AlertTitle>{{
                        t('economy.ui.validationErrors')
                    }}</AlertTitle>
                    <AlertDescription>
                        <ul class="list-disc pl-4">
                            <li
                                v-for="(message, field) in form.errors"
                                :key="field"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </AlertDescription>
                </Alert>
                <FieldGroup>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Field
                            ><FieldLabel>{{ t('economy.ui.type') }}</FieldLabel
                            ><Select
                                v-model="form.type"
                                @update:model-value="form.validate('type')"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent
                                    ><SelectGroup
                                        ><SelectItem value="expense">{{
                                            t('economy.transaction.typeExpense')
                                        }}</SelectItem
                                        ><SelectItem value="income">{{
                                            t('economy.transaction.typeIncome')
                                        }}</SelectItem></SelectGroup
                                    ></SelectContent
                                ></Select
                            ></Field
                        >
                        <Field
                            ><FieldLabel>{{
                                t('economy.destination.label')
                            }}</FieldLabel
                            ><Select
                                v-model="destination"
                                :disabled="destinationLocked"
                                @update:model-value="onDestinationChange"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent
                                    ><SelectGroup
                                        ><SelectItem
                                            v-for="option in destinationOptions"
                                            :key="
                                                option.id ?? PRIVATE_DESTINATION
                                            "
                                            :value="
                                                option.id ?? PRIVATE_DESTINATION
                                            "
                                            >{{ option.label }}</SelectItem
                                        ></SelectGroup
                                    ></SelectContent
                                ></Select
                            ></Field
                        >
                        <Field v-if="isHouseholdDestination"
                            ><FieldLabel>{{ t('economy.ui.scope') }}</FieldLabel
                            ><Select
                                v-model="form.scope"
                                @update:model-value="form.validate('scope')"
                                ><SelectTrigger><SelectValue /></SelectTrigger
                                ><SelectContent
                                    ><SelectGroup
                                        ><SelectItem value="personal">{{
                                            t('economy.transaction.personal')
                                        }}</SelectItem
                                        ><SelectItem value="shared">{{
                                            t('economy.transaction.shared')
                                        }}</SelectItem></SelectGroup
                                    ></SelectContent
                                ></Select
                            ></Field
                        >
                        <Field :data-invalid="Boolean(form.errors.title)"
                            ><FieldLabel for="title">{{
                                t('economy.transaction.title')
                            }}</FieldLabel
                            ><Input
                                id="title"
                                v-model="form.title"
                                :aria-invalid="Boolean(form.errors.title)"
                                @blur="form.validate('title')"
                                @input="form.validate('title')"
                            /><FieldError v-if="form.errors.title">{{
                                form.errors.title
                            }}</FieldError></Field
                        >
                        <Field :data-invalid="Boolean(form.errors.amount)"
                            ><FieldLabel for="amount">{{
                                t('economy.transaction.amount')
                            }}</FieldLabel
                            ><Input
                                id="amount"
                                v-model="form.amount"
                                type="number"
                                :step="amountStep"
                                :min="amountStep"
                                :aria-invalid="Boolean(form.errors.amount)"
                                @blur="form.validate('amount')"
                                @input="form.validate('amount')"
                            /><FieldError v-if="form.errors.amount">{{
                                form.errors.amount
                            }}</FieldError></Field
                        >
                        <Field :data-invalid="Boolean(form.errors.currency)"
                            ><FieldLabel for="currency">{{
                                t('economy.transaction.currency')
                            }}</FieldLabel
                            ><Input
                                id="currency"
                                v-model="form.currency"
                                maxlength="10"
                                :aria-invalid="Boolean(form.errors.currency)"
                                @blur="form.validate('currency')"
                                @input="form.validate('currency')"
                            /><FieldError v-if="form.errors.currency">{{
                                form.errors.currency
                            }}</FieldError></Field
                        >
                        <Field
                            v-if="availableAccounts.length"
                            :data-invalid="Boolean(form.errors.account_id)"
                            ><FieldLabel for="account_id">{{
                                t('economy.account.label')
                            }}</FieldLabel
                            ><Select
                                v-model="form.account_id"
                                @update:model-value="
                                    form.validate('account_id')
                                "
                            >
                                <SelectTrigger id="account_id">
                                    <SelectValue
                                        :placeholder="t('economy.account.none')"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem value="">
                                            {{ t('economy.account.none') }}
                                        </SelectItem>
                                        <SelectItem
                                            v-for="account in availableAccounts"
                                            :key="account.id"
                                            :value="account.id"
                                        >
                                            {{ account.name }}
                                            ({{ account.currency }})
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldDescription>{{
                                t('economy.account.help')
                            }}</FieldDescription
                            ><FieldError v-if="form.errors.account_id">{{
                                form.errors.account_id
                            }}</FieldError></Field
                        >
                        <Field
                            ><FieldLabel for="place">{{
                                t('economy.transaction.place')
                            }}</FieldLabel
                            ><Input
                                id="place"
                                v-model="form.place"
                                @blur="form.validate('place')"
                                @input="form.validate('place')"
                        /></Field>
                        <Field
                            ><FieldLabel for="occurred_at">{{
                                t('economy.transaction.date')
                            }}</FieldLabel>
                            <div class="flex gap-2">
                                <Input
                                    id="occurred_at"
                                    v-model="form.occurred_at"
                                    class="min-w-0 flex-1"
                                    type="date"
                                    @blur="form.validate('occurred_at')"
                                    @input="form.validate('occurred_at')"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="setOccurredAtToToday"
                                >
                                    {{ t('economy.ui.today') }}
                                </Button>
                            </div></Field
                        >
                        <Alert
                            v-if="isGeneratedOccurrence"
                            class="md:col-span-2"
                        >
                            <AlertTitle>{{
                                t('economy.recurrence.generatedTitle')
                            }}</AlertTitle>
                            <AlertDescription>{{
                                t('economy.recurrence.generatedDescription')
                            }}</AlertDescription>
                        </Alert>
                        <Field
                            v-else
                            orientation="horizontal"
                            class="rounded-lg border p-4 md:col-span-2"
                        >
                            <FieldContent>
                                <FieldLabel for="recurrence-enabled">{{
                                    t('economy.recurrence.enabled')
                                }}</FieldLabel>
                                <FieldDescription>{{
                                    t('economy.recurrence.description')
                                }}</FieldDescription>
                            </FieldContent>
                            <Switch
                                id="recurrence-enabled"
                                :checked="recurrenceEnabled"
                                @update:checked="toggleRecurrence"
                            />
                        </Field>
                        <div
                            v-if="recurrenceEnabled && !isGeneratedOccurrence"
                            class="grid gap-4 rounded-lg border p-4 md:col-span-2 md:grid-cols-3"
                        >
                            <Field
                                :data-invalid="
                                    Boolean(form.errors.recurrence_frequency)
                                "
                            >
                                <FieldLabel>{{
                                    t('economy.recurrence.frequency')
                                }}</FieldLabel>
                                <Select v-model="form.recurrence_frequency">
                                    <SelectTrigger
                                        :aria-invalid="
                                            Boolean(
                                                form.errors
                                                    .recurrence_frequency,
                                            )
                                        "
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="daily">{{
                                                t(
                                                    'economy.recurrence.frequencies.daily',
                                                )
                                            }}</SelectItem>
                                            <SelectItem value="weekly">{{
                                                t(
                                                    'economy.recurrence.frequencies.weekly',
                                                )
                                            }}</SelectItem>
                                            <SelectItem value="monthly">{{
                                                t(
                                                    'economy.recurrence.frequencies.monthly',
                                                )
                                            }}</SelectItem>
                                            <SelectItem value="yearly">{{
                                                t(
                                                    'economy.recurrence.frequencies.yearly',
                                                )
                                            }}</SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <FieldError
                                    v-if="form.errors.recurrence_frequency"
                                    >{{
                                        form.errors.recurrence_frequency
                                    }}</FieldError
                                >
                            </Field>
                            <Field
                                :data-invalid="
                                    Boolean(form.errors.recurrence_interval)
                                "
                            >
                                <FieldLabel for="recurrence_interval">{{
                                    t('economy.recurrence.interval')
                                }}</FieldLabel>
                                <Input
                                    id="recurrence_interval"
                                    v-model="form.recurrence_interval"
                                    type="number"
                                    min="1"
                                    max="365"
                                    :aria-invalid="
                                        Boolean(form.errors.recurrence_interval)
                                    "
                                    @blur="form.validate('recurrence_interval')"
                                    @input="
                                        form.validate('recurrence_interval')
                                    "
                                />
                                <FieldError
                                    v-if="form.errors.recurrence_interval"
                                    >{{
                                        form.errors.recurrence_interval
                                    }}</FieldError
                                >
                            </Field>
                            <Field
                                :data-invalid="
                                    Boolean(form.errors.recurrence_ends_at)
                                "
                            >
                                <FieldLabel for="recurrence_ends_at">{{
                                    t('economy.recurrence.endsAt')
                                }}</FieldLabel>
                                <Input
                                    id="recurrence_ends_at"
                                    v-model="form.recurrence_ends_at"
                                    type="date"
                                    :min="form.occurred_at || undefined"
                                    :aria-invalid="
                                        Boolean(form.errors.recurrence_ends_at)
                                    "
                                    @blur="form.validate('recurrence_ends_at')"
                                    @input="form.validate('recurrence_ends_at')"
                                />
                                <FieldDescription>{{
                                    t('economy.recurrence.noEnd')
                                }}</FieldDescription>
                                <FieldError
                                    v-if="form.errors.recurrence_ends_at"
                                    >{{
                                        form.errors.recurrence_ends_at
                                    }}</FieldError
                                >
                            </Field>
                            <Field
                                v-if="form.recurrence_frequency === 'weekly'"
                                class="md:col-span-3"
                                :data-invalid="
                                    Boolean(form.errors.recurrence_weekdays)
                                "
                            >
                                <FieldLabel>{{
                                    t('economy.recurrence.weekdaysLabel')
                                }}</FieldLabel>
                                <FieldDescription>{{
                                    t('economy.recurrence.weekdaysDescription')
                                }}</FieldDescription>
                                <div
                                    class="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7"
                                >
                                    <Field
                                        v-for="weekday in weekdayOptions"
                                        :key="weekday.value"
                                        orientation="horizontal"
                                        class="rounded-md border p-3"
                                    >
                                        <Checkbox
                                            :id="`recurrence-weekday-${weekday.value}`"
                                            :model-value="
                                                form.recurrence_weekdays.includes(
                                                    weekday.value,
                                                )
                                            "
                                            @update:model-value="
                                                toggleWeekday(
                                                    weekday.value,
                                                    Boolean($event),
                                                )
                                            "
                                        />
                                        <FieldContent>
                                            <FieldLabel
                                                :for="`recurrence-weekday-${weekday.value}`"
                                                >{{
                                                    t(
                                                        `economy.recurrence.weekdays.${weekday.key}`,
                                                    )
                                                }}</FieldLabel
                                            >
                                        </FieldContent>
                                    </Field>
                                </div>
                                <FieldError
                                    v-if="form.errors.recurrence_weekdays"
                                    >{{
                                        form.errors.recurrence_weekdays
                                    }}</FieldError
                                >
                            </Field>
                        </div>
                    </div>
                    <Field
                        ><FieldLabel for="notes">{{
                            t('economy.ui.notes')
                        }}</FieldLabel
                        ><Textarea
                            id="notes"
                            v-model="form.notes"
                            @blur="form.validate('notes')"
                            @input="form.validate('notes')"
                    /></Field>
                    <div
                        v-if="
                            !isHouseholdDestination || form.scope === 'personal'
                        "
                        class="max-w-80"
                    >
                        <ContactPicker
                            v-model="form.contact_id"
                            :label="t('economy.ui.linkedContact')"
                            :placeholder="
                                t('economy.ui.linkedContactPlaceholder')
                            "
                            :selected-name="form.contact_name"
                            :scope="currentHouseholdId"
                            :error="form.errors.contact_id"
                        />
                    </div>
                </FieldGroup>
                <ParticipantSelector
                    v-if="isHouseholdDestination && form.scope === 'shared'"
                    v-model="form.participants"
                    :members="destinationMembers"
                    :amount="form.amount"
                    :currency="form.currency"
                    :default-split-type="destinationSplitType"
                    :error="form.errors.participants"
                    :validate="validateField"
                />
                <Accordion type="multiple">
                    <AccordionItem value="items"
                        ><AccordionTrigger
                            >{{ t('economy.transaction.items') }} ({{
                                form.items.length
                            }})</AccordionTrigger
                        ><AccordionContent
                            ><ItemEditor
                                v-model="form.items"
                                :errors="form.errors"
                                :validate="validateField" /></AccordionContent
                    ></AccordionItem>
                    <AccordionItem value="taxes"
                        ><AccordionTrigger
                            >{{ t('economy.transaction.taxes') }} ({{
                                form.taxes.length
                            }})</AccordionTrigger
                        ><AccordionContent
                            ><TaxEditor
                                v-model="form.taxes"
                                :errors="form.errors"
                                :validate="validateField" /></AccordionContent
                    ></AccordionItem>
                </Accordion>
            </CardContent>
            <CardFooter class="justify-end"
                ><Button type="submit" :disabled="form.processing"
                    ><Spinner
                        v-if="form.processing"
                        data-icon="inline-start"
                    />{{ t('common.actions.save') }}</Button
                ></CardFooter
            >
        </Card>
    </form>
</template>
