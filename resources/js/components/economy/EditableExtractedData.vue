<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
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
import ItemEditor from './ItemEditor.vue';
import TaxEditor from './TaxEditor.vue';
import type { EconomyItem, EconomyTax } from './types';

interface ReviewData {
    type: 'expense' | 'income';
    scope: 'personal' | 'shared';
    title: string;
    amount: string;
    currency: string;
    place: string;
    occurred_at: string;
    items: EconomyItem[];
    taxes: EconomyTax[];
}

const data = defineModel<ReviewData>({ required: true });
const { t } = useI18n();
defineProps<{
    household: boolean;
    errors?: Record<string, string>;
    validate?: (field?: string) => void;
}>();
</script>

<template>
    <FieldGroup>
        <div class="grid gap-4 md:grid-cols-2">
            <Field
                ><FieldLabel>{{ t('economy.ui.type') }}</FieldLabel
                ><Select
                    v-model="data.type"
                    @update:model-value="validate?.('type')"
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
            <Field v-if="household"
                ><FieldLabel>{{ t('economy.ui.scope') }}</FieldLabel
                ><Select
                    v-model="data.scope"
                    @update:model-value="validate?.('scope')"
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
            <Field :data-invalid="Boolean(errors?.title)"
                ><FieldLabel for="review-title">{{
                    t('economy.transaction.title')
                }}</FieldLabel
                ><Input
                    id="review-title"
                    v-model="data.title"
                    :aria-invalid="Boolean(errors?.title)"
                    @blur="validate?.('title')"
                    @input="validate?.('title')"
                /><FieldError v-if="errors?.title">{{
                    errors.title
                }}</FieldError></Field
            >
            <Field :data-invalid="Boolean(errors?.amount)"
                ><FieldLabel for="review-amount">{{
                    t('economy.transaction.amount')
                }}</FieldLabel
                ><Input
                    id="review-amount"
                    v-model="data.amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    :aria-invalid="Boolean(errors?.amount)"
                    @blur="validate?.('amount')"
                    @input="validate?.('amount')"
                /><FieldError v-if="errors?.amount">{{
                    errors.amount
                }}</FieldError></Field
            >
            <Field
                ><FieldLabel for="review-currency">{{
                    t('economy.transaction.currency')
                }}</FieldLabel
                ><Input
                    id="review-currency"
                    v-model="data.currency"
                    maxlength="3"
                    @blur="validate?.('currency')"
                    @input="validate?.('currency')"
            /></Field>
            <Field
                ><FieldLabel for="review-place">{{
                    t('economy.transaction.place')
                }}</FieldLabel
                ><Input
                    id="review-place"
                    v-model="data.place"
                    @blur="validate?.('place')"
                    @input="validate?.('place')"
            /></Field>
            <Field
                ><FieldLabel for="review-date">{{
                    t('economy.transaction.date')
                }}</FieldLabel
                ><Input
                    id="review-date"
                    v-model="data.occurred_at"
                    type="date"
                    @blur="validate?.('occurred_at')"
                    @input="validate?.('occurred_at')"
            /></Field>
        </div>
        <Accordion type="multiple"
            ><AccordionItem value="items"
                ><AccordionTrigger
                    >{{ t('economy.transaction.items') }} ({{
                        data.items.length
                    }})</AccordionTrigger
                ><AccordionContent
                    ><ItemEditor
                        v-model="data.items"
                        :errors="errors"
                        :validate="
                            validate
                        " /></AccordionContent></AccordionItem
            ><AccordionItem value="taxes"
                ><AccordionTrigger
                    >{{ t('economy.transaction.taxes') }} ({{
                        data.taxes.length
                    }})</AccordionTrigger
                ><AccordionContent
                    ><TaxEditor
                        v-model="data.taxes"
                        :errors="errors"
                        :validate="
                            validate
                        " /></AccordionContent></AccordionItem
        ></Accordion>
    </FieldGroup>
</template>
