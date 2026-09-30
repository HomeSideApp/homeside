<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { EconomyTax } from './types';

const taxes = defineModel<EconomyTax[]>({ required: true });
const { t } = useI18n();
defineProps<{
    errors?: Record<string, string>;
    validate?: (field?: string) => void;
}>();

function addTax(): void {
    taxes.value.push({ name: '', rate: '', taxable_base: '', amount: '' });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <FieldGroup
            v-for="(tax, index) in taxes"
            :key="tax.id ?? index"
            class="rounded-lg border p-4"
        >
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Field :data-invalid="Boolean(errors?.[`taxes.${index}.name`])">
                    <FieldLabel :for="`tax-name-${index}`">{{
                        t('economy.ui.name')
                    }}</FieldLabel>
                    <Input
                        :id="`tax-name-${index}`"
                        v-model="tax.name"
                        :aria-invalid="Boolean(errors?.[`taxes.${index}.name`])"
                        @blur="validate?.(`taxes.${index}.name`)"
                        @input="validate?.(`taxes.${index}.name`)"
                    />
                    <FieldError v-if="errors?.[`taxes.${index}.name`]">{{
                        errors[`taxes.${index}.name`]
                    }}</FieldError>
                </Field>
                <Field
                    ><FieldLabel :for="`tax-rate-${index}`">{{
                        t('economy.ui.rate')
                    }}</FieldLabel
                    ><Input
                        :id="`tax-rate-${index}`"
                        v-model="tax.rate"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        @blur="validate?.(`taxes.${index}.rate`)"
                        @input="validate?.(`taxes.${index}.rate`)"
                /></Field>
                <Field
                    ><FieldLabel :for="`tax-base-${index}`">{{
                        t('economy.ui.taxableBase')
                    }}</FieldLabel
                    ><Input
                        :id="`tax-base-${index}`"
                        v-model="tax.taxable_base"
                        type="number"
                        min="0"
                        step="0.01"
                        @blur="validate?.(`taxes.${index}.taxable_base`)"
                        @input="validate?.(`taxes.${index}.taxable_base`)"
                /></Field>
                <Field>
                    <FieldLabel :for="`tax-amount-${index}`">{{
                        t('economy.ui.taxAmount')
                    }}</FieldLabel>
                    <div class="flex gap-2">
                        <Input
                            :id="`tax-amount-${index}`"
                            v-model="tax.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            @blur="validate?.(`taxes.${index}.amount`)"
                            @input="validate?.(`taxes.${index}.amount`)"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            @click="taxes.splice(index, 1)"
                            ><Trash2 data-icon="inline-start" /><span
                                class="sr-only"
                                >{{ t('economy.ui.removeTax') }}</span
                            ></Button
                        >
                    </div>
                </Field>
            </div>
        </FieldGroup>
        <Button
            type="button"
            variant="outline"
            class="self-start"
            @click="addTax"
            ><Plus data-icon="inline-start" />{{
                t('economy.ui.addTax')
            }}</Button
        >
    </div>
</template>
