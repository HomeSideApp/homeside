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
import type { EconomyItem } from './types';

const items = defineModel<EconomyItem[]>({ required: true });
const { t } = useI18n();

defineProps<{
    errors?: Record<string, string>;
    validate?: (field?: string) => void;
}>();

function addItem(): void {
    items.value.push({
        name: '',
        quantity: 1,
        unit_amount: '',
        subtotal: '',
        tax_amount: null,
        total: '',
    });
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <FieldGroup
            v-for="(item, index) in items"
            :key="item.id ?? index"
            class="rounded-lg border p-4"
        >
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Field :data-invalid="Boolean(errors?.[`items.${index}.name`])">
                    <FieldLabel :for="`item-name-${index}`">{{
                        t('economy.ui.name')
                    }}</FieldLabel>
                    <Input
                        :id="`item-name-${index}`"
                        v-model="item.name"
                        :name="`items[${index}][name]`"
                        :aria-invalid="Boolean(errors?.[`items.${index}.name`])"
                        @blur="validate?.(`items.${index}.name`)"
                        @input="validate?.(`items.${index}.name`)"
                    />
                    <FieldError v-if="errors?.[`items.${index}.name`]">{{
                        errors[`items.${index}.name`]
                    }}</FieldError>
                </Field>
                <Field>
                    <FieldLabel :for="`item-quantity-${index}`">{{
                        t('economy.ui.quantity')
                    }}</FieldLabel>
                    <Input
                        :id="`item-quantity-${index}`"
                        v-model.number="item.quantity"
                        type="number"
                        min="1"
                        :name="`items[${index}][quantity]`"
                        @blur="validate?.(`items.${index}.quantity`)"
                        @input="validate?.(`items.${index}.quantity`)"
                    />
                </Field>
                <Field>
                    <FieldLabel :for="`item-unit-${index}`">{{
                        t('economy.ui.unitAmount')
                    }}</FieldLabel>
                    <Input
                        :id="`item-unit-${index}`"
                        v-model="item.unit_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        :name="`items[${index}][unit_amount]`"
                        @blur="validate?.(`items.${index}.unit_amount`)"
                        @input="validate?.(`items.${index}.unit_amount`)"
                    />
                </Field>
                <Field>
                    <FieldLabel :for="`item-subtotal-${index}`">{{
                        t('economy.ui.subtotal')
                    }}</FieldLabel>
                    <Input
                        :id="`item-subtotal-${index}`"
                        v-model="item.subtotal"
                        type="number"
                        min="0"
                        step="0.01"
                        :name="`items[${index}][subtotal]`"
                        @blur="validate?.(`items.${index}.subtotal`)"
                        @input="validate?.(`items.${index}.subtotal`)"
                    />
                </Field>
                <Field>
                    <FieldLabel :for="`item-tax-${index}`">{{
                        t('economy.ui.tax')
                    }}</FieldLabel>
                    <Input
                        :id="`item-tax-${index}`"
                        :model-value="item.tax_amount ?? ''"
                        type="number"
                        min="0"
                        step="0.01"
                        :name="`items[${index}][tax_amount]`"
                        @update:model-value="
                            item.tax_amount =
                                $event === '' ? null : String($event)
                        "
                        @blur="validate?.(`items.${index}.tax_amount`)"
                        @input="validate?.(`items.${index}.tax_amount`)"
                    />
                </Field>
                <Field>
                    <FieldLabel :for="`item-total-${index}`">{{
                        t('economy.ui.total')
                    }}</FieldLabel>
                    <div class="flex gap-2">
                        <Input
                            :id="`item-total-${index}`"
                            v-model="item.total"
                            type="number"
                            min="0"
                            step="0.01"
                            :name="`items[${index}][total]`"
                            @blur="validate?.(`items.${index}.total`)"
                            @input="validate?.(`items.${index}.total`)"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            @click="items.splice(index, 1)"
                        >
                            <Trash2 data-icon="inline-start" /><span
                                class="sr-only"
                                >{{ t('economy.ui.removeItem') }}</span
                            >
                        </Button>
                    </div>
                </Field>
            </div>
        </FieldGroup>
        <Button
            type="button"
            variant="outline"
            class="self-start"
            @click="addItem"
        >
            <Plus data-icon="inline-start" />{{ t('economy.ui.addItem') }}
        </Button>
    </div>
</template>
