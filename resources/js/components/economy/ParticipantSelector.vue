<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldError, FieldLegend, FieldSet } from '@/components/ui/field';
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
