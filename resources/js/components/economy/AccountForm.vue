<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
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
import { store as accountsStore } from '@/routes/economy/me/accounts';
import { update as accountsUpdate } from '@/routes/economy/me/accounts';
import type {
    CryptoAsset,
    EconomicAccount,
    PaymentMethod,
} from './types';

const { t } = useI18n();
const props = defineProps<{
    paymentMethods: PaymentMethod[];
    account?: EconomicAccount | null;
    cryptoAssets?: CryptoAsset[];
}>();

const isEditing = Boolean(props.account?.id);
const submitUrl = isEditing
    ? accountsUpdate.url(props.account!.id)
    : accountsStore.url();

const form = useForm(isEditing ? 'put' : 'post', submitUrl, {
    name: props.account?.name ?? '',
    payment_method_ids: (props.account?.payment_methods ?? []).map(
        (method) => method.id,
    ),
    crypto_asset_id: props.account?.crypto_asset_id ?? '',
    currency: props.account?.currency ?? 'EUR',
    decimal_places: String(props.account?.decimal_places ?? 2),
    initial_balance_minor: String(props.account?.initial_balance_minor ?? 0),
    initial_balance_at: props.account?.initial_balance_at ?? '',
    color: props.account?.color ?? '#6366F1',
    last_four_digits: props.account?.last_four_digits ?? '',
    include_in_totals: props.account?.include_in_totals ?? true,
});

const isCrypto = computed(() =>
    props.paymentMethods.some(
        (method) =>
            form.payment_method_ids.includes(method.id) &&
            method.kind === 'crypto',
    ),
);

function togglePaymentMethod(methodId: string, selected: boolean): void {
    form.payment_method_ids = selected
        ? [...new Set([...form.payment_method_ids, methodId])]
        : form.payment_method_ids.filter((id) => id !== methodId);

    form.validate('payment_method_ids');
}
const availableCryptoAssets = computed<CryptoAsset[]>(
    () => props.cryptoAssets ?? [],
);

function selectCryptoAsset(assetId: string): void {
    form.crypto_asset_id = assetId;

    const asset = availableCryptoAssets.value.find(
        (candidate) => candidate.id === assetId,
    );

    if (asset) {
        form.currency = asset.symbol;
        form.decimal_places = String(asset.decimal_places);
    }

    form.validate('crypto_asset_id');
    form.validate('currency');
}

function validateField(field: string): void {
    form.validate(field as never);
}

function submit(): void {
    form.submit();
}
</script>

<template>
    <form class="max-w-3xl" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{
                    isEditing
                        ? t('economy.accounts.editTitle')
                        : t('economy.accounts.createTitle')
                }}</CardTitle>
            </CardHeader>
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

                <FieldGroup class="grid gap-4 md:grid-cols-2">
                    <Field :data-invalid="Boolean(form.errors.name)">
                        <FieldLabel for="name">{{
                            t('economy.account.name')
                        }}</FieldLabel>
                        <Input
                            id="name"
                            v-model="form.name"
                            :aria-invalid="Boolean(form.errors.name)"
                            @blur="validateField('name')"
                            @input="validateField('name')"
                        />
                        <FieldError v-if="form.errors.name">{{
                            form.errors.name
                        }}</FieldError>
                    </Field>

                    <Field
                        class="md:col-span-2"
                        :data-invalid="
                            Boolean(form.errors.payment_method_ids)
                        "
                    >
                        <FieldLabel>{{
                            t('economy.account.paymentMethods')
                        }}</FieldLabel>
                        <FieldDescription>{{
                            t('economy.account.paymentMethodsHelp')
                        }}</FieldDescription>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="method in paymentMethods"
                                :key="method.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`method-${method.id}`"
                                    :model-value="
                                        form.payment_method_ids.includes(
                                            method.id,
                                        )
                                    "
                                    @update:model-value="
                                        (value) =>
                                            togglePaymentMethod(
                                                method.id,
                                                Boolean(value),
                                            )
                                    "
                                />
                                <FieldLabel :for="`method-${method.id}`">{{
                                    method.name
                                }}</FieldLabel>
                            </div>
                        </div>
                        <FieldError v-if="form.errors.payment_method_ids">{{
                            form.errors.payment_method_ids
                        }}</FieldError>
                    </Field>

                    <Field
                        v-if="isCrypto"
                        :data-invalid="Boolean(form.errors.crypto_asset_id)"
                    >
                        <FieldLabel for="crypto_asset_id">{{
                            t('economy.account.cryptoAsset')
                        }}</FieldLabel>
                        <Select
                            :model-value="form.crypto_asset_id"
                            @update:model-value="
                                (value) => selectCryptoAsset(String(value))
                            "
                        >
                            <SelectTrigger id="crypto_asset_id">
                                <SelectValue
                                    :placeholder="
                                        t('economy.account.selectAsset')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem
                                        v-for="asset in availableCryptoAssets"
                                        :key="asset.id"
                                        :value="asset.id"
                                    >
                                        {{ asset.symbol }} — {{ asset.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <FieldError v-if="form.errors.crypto_asset_id">{{
                            form.errors.crypto_asset_id
                        }}</FieldError>
                    </Field>

                    <Field :data-invalid="Boolean(form.errors.currency)">
                        <FieldLabel for="currency">{{
                            t('economy.account.currency')
                        }}</FieldLabel>
                        <Input
                            id="currency"
                            v-model="form.currency"
                            maxlength="10"
                            :aria-invalid="Boolean(form.errors.currency)"
                            @blur="validateField('currency')"
                            @input="validateField('currency')"
                        />
                        <FieldDescription v-if="isCrypto">{{
                            t('economy.accounts.cryptoNotice')
                        }}</FieldDescription>
                        <FieldError v-if="form.errors.currency">{{
                            form.errors.currency
                        }}</FieldError>
                    </Field>

                    <Field :data-invalid="Boolean(form.errors.decimal_places)">
                        <FieldLabel for="decimal_places">{{
                            t('economy.account.decimalPlaces')
                        }}</FieldLabel>
                        <Input
                            id="decimal_places"
                            v-model="form.decimal_places"
                            type="number"
                            min="0"
                            max="18"
                            :aria-invalid="Boolean(form.errors.decimal_places)"
                            @blur="validateField('decimal_places')"
                            @input="validateField('decimal_places')"
                        />
                        <FieldError v-if="form.errors.decimal_places">{{
                            form.errors.decimal_places
                        }}</FieldError>
                    </Field>

                    <Field
                        :data-invalid="
                            Boolean(form.errors.initial_balance_minor)
                        "
                    >
                        <FieldLabel for="initial_balance_minor">{{
                            t('economy.account.initialBalance')
                        }}</FieldLabel>
                        <Input
                            id="initial_balance_minor"
                            v-model="form.initial_balance_minor"
                            type="number"
                            :aria-invalid="
                                Boolean(form.errors.initial_balance_minor)
                            "
                            @blur="validateField('initial_balance_minor')"
                            @input="validateField('initial_balance_minor')"
                        />
                        <FieldDescription>{{
                            t('economy.account.initialBalanceHelp')
                        }}</FieldDescription>
                        <FieldError
                            v-if="form.errors.initial_balance_minor"
                            >{{
                                form.errors.initial_balance_minor
                            }}</FieldError
                        >
                    </Field>

                    <Field
                        :data-invalid="Boolean(form.errors.initial_balance_at)"
                    >
                        <FieldLabel for="initial_balance_at">{{
                            t('economy.account.initialBalanceAt')
                        }}</FieldLabel>
                        <Input
                            id="initial_balance_at"
                            v-model="form.initial_balance_at"
                            type="date"
                            :aria-invalid="
                                Boolean(form.errors.initial_balance_at)
                            "
                            @change="validateField('initial_balance_at')"
                        />
                        <FieldError v-if="form.errors.initial_balance_at">{{
                            form.errors.initial_balance_at
                        }}</FieldError>
                    </Field>

                    <Field :data-invalid="Boolean(form.errors.color)">
                        <FieldLabel for="color">{{
                            t('economy.account.color')
                        }}</FieldLabel>
                        <Input
                            id="color"
                            v-model="form.color"
                            type="color"
                            :aria-invalid="Boolean(form.errors.color)"
                            @change="validateField('color')"
                        />
                        <FieldError v-if="form.errors.color">{{
                            form.errors.color
                        }}</FieldError>
                    </Field>

                    <Field
                        :data-invalid="Boolean(form.errors.last_four_digits)"
                    >
                        <FieldLabel for="last_four_digits">{{
                            t('economy.account.lastFourDigits')
                        }}</FieldLabel>
                        <Input
                            id="last_four_digits"
                            v-model="form.last_four_digits"
                            maxlength="4"
                            :aria-invalid="
                                Boolean(form.errors.last_four_digits)
                            "
                            @blur="validateField('last_four_digits')"
                        />
                        <FieldError v-if="form.errors.last_four_digits">{{
                            form.errors.last_four_digits
                        }}</FieldError>
                    </Field>
                </FieldGroup>

                <div class="flex items-center gap-2">
                    <Checkbox
                        id="include_in_totals"
                        :model-value="form.include_in_totals"
                        @update:model-value="
                            (value) => {
                                form.include_in_totals = Boolean(value);
                                validateField('include_in_totals');
                            }
                        "
                    />
                    <FieldLabel for="include_in_totals">{{
                        t('economy.account.includeInTotals')
                    }}</FieldLabel>
                </div>
            </CardContent>
            <CardFooter class="flex justify-end gap-2">
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" data-icon="inline-start" />
                    {{
                        isEditing
                            ? t('economy.accounts.saveChanges')
                            : t('economy.accounts.create')
                    }}
                </Button>
            </CardFooter>
        </Card>
    </form>
</template>
