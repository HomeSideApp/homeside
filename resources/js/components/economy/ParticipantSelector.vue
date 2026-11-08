<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldError,
    FieldLabel,
    FieldLegend,
    FieldSeparator,
    FieldSet,
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
import type { EconomyMember, EconomyParticipant } from './types';

type SplitType = 'equal' | 'fixed' | 'percentage';

const participants = defineModel<EconomyParticipant[]>({ required: true });
const { t } = useI18n();
const props = withDefaults(
    defineProps<{
        members: EconomyMember[];
        amount: string;
        defaultSplitType?: SplitType;
        currency?: string;
        error?: string;
        validate?: (field?: string) => void;
    }>(),
    { defaultSplitType: 'equal', currency: '' },
);

/**
 * Last split type chosen in the selector, kept even before any participant
 * exists so the choice is not lost when the first member gets checked.
 */
const splitChoice = ref<SplitType>(props.defaultSplitType);

const splitType = computed<SplitType>({
    get: () => participants.value[0]?.split_type ?? splitChoice.value,
    set: (value: SplitType) => {
        splitChoice.value = value;
        participants.value.forEach((participant) => {
            participant.split_type = value;
        });
        props.validate?.('participants');
    },
});

watch(
    () => props.defaultSplitType,
    (value) => {
        if (participants.value.length === 0) {
            splitChoice.value = value;
        }
    },
);

const allocatedAmount = computed(() =>
    participants.value.reduce(
        (total, participant) => total + (Number(participant.amount) || 0),
        0,
    ),
);
const allocatedPercentage = computed(() =>
    participants.value.reduce(
        (total, participant) => total + (participant.percentage ?? 0),
        0,
    ),
);
const totalAmount = computed(() => Number(props.amount) || 0);
const amountBalanced = computed(
    () => Math.abs(allocatedAmount.value - totalAmount.value) < 0.005,
);

function selected(memberId: string): boolean {
    return participants.value.some(
        (participant) => participant.household_member_id === memberId,
    );
}

function toggle(memberId: string, checked: boolean): void {
    if (checked) {
        participants.value.push({
            household_member_id: memberId,
            split_type: splitType.value,
            amount: '',
            percentage: null,
        });
        props.validate?.('participants');

        return;
    }

    participants.value = participants.value.filter(
        (participant) => participant.household_member_id !== memberId,
    );
    props.validate?.('participants');
}

function updateFixedAmount(memberId: string, value: string | number): void {
    const participant = participants.value.find(
        (item) => item.household_member_id === memberId,
    );

    if (participant) {
        participant.amount = String(value);
        props.validate?.('participants');
    }
}

function updatePercentage(memberId: string, value: string | number): void {
    const participant = participants.value.find(
        (item) => item.household_member_id === memberId,
    );

    if (participant) {
        participant.percentage = value === '' ? null : Number(value);
        props.validate?.('participants');
    }
}

/**
 * A row is anonymous when it has neither a household membership nor a name. Named external rows
 * keep this flag false even while their name input is still empty.
 */
function isAnonymousParticipant(participant: EconomyParticipant): boolean {
    return (
        participant.household_member_id === null &&
        (participant.display_name === null ||
            participant.display_name === undefined)
    );
}

/** Rows that belong to neither the household member list nor the single unnamed share. */
const nonMemberParticipants = computed(() =>
    participants.value.filter(
        (participant) => participant.household_member_id === null,
    ),
);

/**
 * The single unnamed portion of the split, which carries `share_count` instead of an identity.
 */
const anonymousParticipants = computed(() =>
    participants.value.filter(isAnonymousParticipant),
);

function addExternalParticipant(): void {
    participants.value.push({
        household_member_id: null,
        contact_id: null,
        display_name: '',
        share_count: 1,
        split_type: splitType.value,
        amount: '',
        percentage: null,
    });
    props.validate?.('participants');
}

function addAnonymousShare(): void {
    participants.value.push({
        household_member_id: null,
        contact_id: null,
        display_name: null,
        share_count: 1,
        split_type: splitType.value,
        amount: '',
        percentage: null,
    });
    props.validate?.('participants');
}

function removeParticipant(participant: EconomyParticipant): void {
    participants.value = participants.value.filter(
        (item) => item !== participant,
    );
    props.validate?.('participants');
}

function updateDisplayName(
    participant: EconomyParticipant,
    value: string | number,
): void {
    participant.display_name = String(value);
    props.validate?.('participants');
}

function updateShareCount(
    participant: EconomyParticipant,
    value: string | number,
): void {
    participant.share_count = value === '' ? 1 : Number(value);
    props.validate?.('participants');
}

function updateAmount(
    participant: EconomyParticipant,
    value: string | number,
): void {
    participant.amount = String(value);
    props.validate?.('participants');
}

function updateParticipantPercentage(
    participant: EconomyParticipant,
    value: string | number,
): void {
    participant.percentage = value === '' ? null : Number(value);
    props.validate?.('participants');
}
</script>

<template>
    <FieldSet>
        <FieldLegend>{{ t('economy.ui.splitTitle') }}</FieldLegend>
        <div class="flex flex-col gap-1">
            <label class="text-sm font-medium">{{
                t('economy.ui.splitType')
            }}</label>
            <Select v-model="splitType">
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent
                    ><SelectGroup
                        ><SelectItem value="equal">{{
                            t('economy.ui.splitEqual')
                        }}</SelectItem
                        ><SelectItem value="fixed">{{
                            t('economy.ui.splitFixed')
                        }}</SelectItem
                        ><SelectItem value="percentage">{{
                            t('economy.ui.splitPercentage')
                        }}</SelectItem></SelectGroup
                    ></SelectContent
                >
            </Select>
        </div>
        <div class="flex flex-col gap-3">
            <div
                v-for="member in props.members"
                :key="member.id"
                class="flex items-center gap-3"
            >
                <Checkbox
                    :model-value="selected(member.id)"
                    :aria-label="member.user.name"
                    @update:model-value="toggle(member.id, Boolean($event))"
                />
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <Avatar class="size-6 shrink-0">
                        <AvatarImage
                            v-if="member.contact?.avatar_url"
                            :src="member.contact.avatar_url"
                            :alt="member.user.name"
                        />
                        <AvatarFallback>
                            {{ member.user.name.charAt(0).toUpperCase() }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex min-w-0 flex-col">
                        <span class="text-sm leading-none font-medium">{{
                            member.user.name
                        }}</span>
                        <span
                            v-if="
                                member.contact &&
                                (member.contact.email || member.contact.phone)
                            "
                            class="truncate text-xs text-muted-foreground"
                        >
                            {{
                                [member.contact.email, member.contact.phone]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </span>
                    </div>
                </div>
                <div
                    v-if="selected(member.id) && splitType === 'fixed'"
                    class="relative w-32 shrink-0"
                >
                    <Input
                        :model-value="
                            participants.find(
                                (item) =>
                                    item.household_member_id === member.id,
                            )?.amount ?? ''
                        "
                        class="pr-10 text-right"
                        type="number"
                        min="0"
                        step="0.01"
                        :placeholder="t('economy.ui.fixedAmountPlaceholder')"
                        :aria-label="t('economy.ui.fixedAmount')"
                        @update:model-value="
                            updateFixedAmount(member.id, $event)
                        "
                        @blur="props.validate?.('participants')"
                    />
                    <span
                        v-if="props.currency"
                        class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground"
                        aria-hidden="true"
                        >{{ props.currency }}</span
                    >
                </div>
                <div
                    v-else-if="
                        selected(member.id) && splitType === 'percentage'
                    "
                    class="relative w-28 shrink-0"
                >
                    <Input
                        :model-value="
                            participants.find(
                                (item) =>
                                    item.household_member_id === member.id,
                            )?.percentage ?? ''
                        "
                        class="pr-6 text-right"
                        type="number"
                        min="0"
                        max="100"
                        placeholder="0"
                        :aria-label="t('economy.ui.percentage')"
                        @update:model-value="
                            updatePercentage(member.id, $event)
                        "
                        @blur="props.validate?.('participants')"
                    />
                    <span
                        class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground"
                        aria-hidden="true"
                        >%</span
                    >
                </div>
            </div>
        </div>
        <FieldSeparator class="mt-1">{{
            t('economy.ui.externalParticipantsTitle')
        }}</FieldSeparator>
        <div class="flex flex-col gap-3">
            <div
                v-for="(participant, index) in nonMemberParticipants"
                :key="`participant-${index}`"
                class="flex flex-wrap items-end gap-2 rounded-lg border p-3"
            >
                <Field
                    v-if="isAnonymousParticipant(participant)"
                    class="w-24 shrink-0"
                >
                    <FieldLabel :for="`participant-share-count-${index}`">{{
                        t('economy.ui.anonymousShareCount')
                    }}</FieldLabel>
                    <Input
                        :id="`participant-share-count-${index}`"
                        :model-value="participant.share_count ?? 1"
                        type="number"
                        min="1"
                        max="20"
                        step="1"
                        :aria-invalid="Boolean(props.error)"
                        @update:model-value="
                            updateShareCount(participant, $event)
                        "
                        @blur="props.validate?.('participants')"
                    />
                </Field>
                <Field v-else class="min-w-0 flex-1">
                    <FieldLabel :for="`participant-name-${index}`">{{
                        t('economy.ui.name')
                    }}</FieldLabel>
                    <Input
                        :id="`participant-name-${index}`"
                        :model-value="participant.display_name ?? ''"
                        :placeholder="
                            t('economy.ui.externalParticipantPlaceholder')
                        "
                        :aria-invalid="Boolean(props.error)"
                        @update:model-value="
                            updateDisplayName(participant, $event)
                        "
                        @blur="props.validate?.('participants')"
                    />
                </Field>
                <Field v-if="splitType === 'fixed'" class="w-32 shrink-0">
                    <FieldLabel :for="`participant-amount-${index}`">{{
                        t('economy.ui.fixedAmount')
                    }}</FieldLabel>
                    <div class="relative">
                        <Input
                            :id="`participant-amount-${index}`"
                            :model-value="participant.amount"
                            class="pr-10 text-right"
                            type="number"
                            min="0"
                            step="0.01"
                            :placeholder="
                                t('economy.ui.fixedAmountPlaceholder')
                            "
                            :aria-invalid="Boolean(props.error)"
                            @update:model-value="
                                updateAmount(participant, $event)
                            "
                            @blur="props.validate?.('participants')"
                        />
                        <span
                            v-if="props.currency"
                            class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground"
                            aria-hidden="true"
                            >{{ props.currency }}</span
                        >
                    </div>
                </Field>
                <Field
                    v-else-if="splitType === 'percentage'"
                    class="w-28 shrink-0"
                >
                    <FieldLabel :for="`participant-percentage-${index}`">{{
                        t('economy.ui.percentage')
                    }}</FieldLabel>
                    <div class="relative">
                        <Input
                            :id="`participant-percentage-${index}`"
                            :model-value="participant.percentage ?? ''"
                            class="pr-6 text-right"
                            type="number"
                            min="0"
                            max="100"
                            placeholder="0"
                            :aria-invalid="Boolean(props.error)"
                            @update:model-value="
                                updateParticipantPercentage(participant, $event)
                            "
                            @blur="props.validate?.('participants')"
                        />
                        <span
                            class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground"
                            aria-hidden="true"
                            >%</span
                        >
                    </div>
                </Field>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    @click="removeParticipant(participant)"
                >
                    <Trash2 data-icon="inline-start" /><span class="sr-only">{{
                        t('economy.ui.removeParticipant')
                    }}</span>
                </Button>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addExternalParticipant"
            >
                <Plus data-icon="inline-start" />{{
                    t('economy.ui.addExternalParticipant')
                }}
            </Button>
            <Button
                v-if="anonymousParticipants.length === 0"
                type="button"
                variant="outline"
                size="sm"
                @click="addAnonymousShare"
            >
                <Plus data-icon="inline-start" />{{
                    t('economy.ui.addAnonymousShare')
                }}
            </Button>
        </div>
        <p
            v-if="splitType === 'percentage' && participants.length > 0"
            class="text-xs"
            :class="
                allocatedPercentage === 100
                    ? 'text-muted-foreground'
                    : 'text-destructive'
            "
        >
            {{
                t('economy.ui.splitPercentageTotal', {
                    current: allocatedPercentage,
                })
            }}
        </p>
        <p
            v-else-if="splitType === 'fixed' && participants.length > 0"
            class="text-xs"
            :class="
                amountBalanced ? 'text-muted-foreground' : 'text-destructive'
            "
        >
            {{
                t('economy.ui.splitFixedTotal', {
                    current: allocatedAmount.toFixed(2),
                    total: totalAmount.toFixed(2),
                    currency: props.currency,
                })
            }}
        </p>
        <FieldError v-if="props.error">{{ props.error }}</FieldError>
    </FieldSet>
</template>
