<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Sliders } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import ContactPicker from '@/components/contacts/ContactPicker.vue';
import EditableExtractedData from '@/components/economy/EditableExtractedData.vue';
import ImageProcessingDialog from '@/components/economy/ImageProcessingDialog.vue';
import ImportStatusPoller from '@/components/economy/ImportStatusPoller.vue';
import ParticipantSelector from '@/components/economy/ParticipantSelector.vue';
import ReceiptViewer from '@/components/economy/ReceiptViewer.vue';
import type { ImageProcessingPayload } from '@/components/economy/types';
import type {
    EconomicAccount,
    EconomyDocument,
    EconomyItem,
    EconomyMember,
    EconomyParticipant,
    EconomyTax,
} from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index as privateEconomyIndex } from '@/routes/economy/me';
import { file as privateDocumentFile } from '@/routes/economy/me/documents';
import {
    confirm as privateConfirm,
    discard as privateDiscard,
    index as privateImportsIndex,
    reprocess as privateReprocess,
    retry as privateRetry,
} from '@/routes/economy/me/imports';
import { index as householdAiProvidersIndex } from '@/routes/households/ai-providers';
import { index as householdEconomyIndex } from '@/routes/households/economy';
import { file as householdDocumentFile } from '@/routes/households/economy/documents';
import {
    confirm as householdConfirm,
    discard as householdDiscard,
    index as householdImportsIndex,
    reprocess as householdReprocess,
    retry as householdRetry,
} from '@/routes/households/economy/imports';
import { index as userAiProvidersIndex } from '@/routes/settings/ai-providers';

interface ExtractedData {
    title?: string | null;
    amount?: string | null;
    currency?: string | null;
    place?: string | null;
    occurred_at?: string | null;
    items?: Array<Partial<EconomyItem> & { name: string; total: string }>;
    taxes?: Array<
        Partial<EconomyTax> & {
            name: string;
            rate: string;
            taxable_base: string;
            tax_amount?: string;
        }
    >;
}
interface ImportData {
    id: string;
    status: string;
    document: EconomyDocument | null;
    extracted: ExtractedData | null;
    error: { code: string; message: string } | null;
    ai_run: {
        id: string;
        provider_name: string;
        model_name: string;
        duration_ms: number;
    } | null;
}

const { t } = useI18n();
/**
 * Reka UI forbids an empty `SelectItem` value, so "no account" travels as a sentinel.
 */
const NO_ACCOUNT = '__none__';
const props = defineProps<{
    household: { id: string; name: string } | null;
    importData: ImportData;
    members: EconomyMember[];
    accounts: EconomicAccount[];
}>();
const extracted = props.importData.extracted ?? {};
const confirmUrl = props.household
    ? householdConfirm.url({
          household: props.household.id,
          import: props.importData.id,
      })
    : privateConfirm.url(props.importData.id);
const form = useForm('post', confirmUrl, {
    type: 'expense' as 'expense' | 'income',
    scope: 'personal' as 'personal' | 'shared',
    account_id: '',
    title: extracted.title ?? '',
    amount: extracted.amount ?? '',
    currency: extracted.currency ?? 'EUR',
    place: extracted.place ?? '',
    occurred_at: extracted.occurred_at?.slice(0, 10) ?? '',
    notes: '',
    contact_id: null as string | null,
    items: (extracted.items ?? []).map((item) => ({
        ...item,
        quantity: item.quantity ?? 1,
        unit_amount: item.unit_amount ?? item.total,
        subtotal: item.subtotal ?? item.total,
        tax_amount: item.tax_amount ?? null,
    })),
    taxes: (extracted.taxes ?? []).map((tax) => ({
        ...tax,
        amount: tax.amount ?? tax.tax_amount ?? '',
    })),
    participants: [] as EconomyParticipant[],
});
/**
 * The form keeps no account as an empty string, so an untouched import never submits one.
 */
const accountSelectValue = computed<string>({
    get: () => form.account_id || NO_ACCOUNT,
    set: (value) => {
        form.account_id = value === NO_ACCOUNT ? '' : value;
    },
});
let hasHydratedExtractedData = Boolean(props.importData.extracted);

function validateField(field?: string): void {
    if (field) {
        form.validate(field as never);

        return;
    }

    form.validate();
}

watch(
    () => props.importData.extracted,
    (payload) => {
        if (!payload || hasHydratedExtractedData) {
            return;
        }

        Object.assign(form, {
            title: payload.title ?? '',
            amount: payload.amount ?? '',
            currency: payload.currency ?? 'EUR',
            place: payload.place ?? '',
            occurred_at: payload.occurred_at?.slice(0, 10) ?? '',
            items: (payload.items ?? []).map((item) => ({
                ...item,
                quantity: item.quantity ?? 1,
                unit_amount: item.unit_amount ?? item.total,
                subtotal: item.subtotal ?? item.total,
                tax_amount: item.tax_amount ?? null,
            })),
            taxes: (payload.taxes ?? []).map((tax) => ({
                ...tax,
                amount: tax.amount ?? tax.tax_amount ?? '',
            })),
        });
        form.defaults();
        hasHydratedExtractedData = true;
    },
);
const documentUrl = computed(() =>
    !props.importData.document
        ? ''
        : props.household
          ? householdDocumentFile.url({
                household: props.household.id,
                document: props.importData.document.id,
            })
          : privateDocumentFile.url(props.importData.document.id),
);
const importErrorMessage = computed(() => {
    if (!props.importData.error) {
        return '';
    }

    const key = `economy.import.errors.${props.importData.error.code}`;
    const translated = t(key);

    return translated === key ? props.importData.error.message : translated;
});
const providerSettingsUrl = computed(() =>
    props.household
        ? householdAiProvidersIndex.url(props.household.id)
        : userAiProvidersIndex.url(),
);
const documentImageUrl = computed(() =>
    props.importData.document &&
    props.importData.document.mime_type.startsWith('image/')
        ? documentUrl.value
        : null,
);
const canReprocess = computed(
    () =>
        Boolean(documentImageUrl.value) &&
        ['ready_for_review', 'failed'].includes(props.importData.status),
);
const reprocessPermission = computed(() =>
    props.household
        ? 'households.economy.imports.reprocess'
        : 'economy.me.imports.reprocess',
);

const processingOpen = ref(false);

function reprocess(payload: ImageProcessingPayload): void {
    router.post(
        props.household
            ? householdReprocess.url({
                  household: props.household.id,
                  import: props.importData.id,
              })
            : privateReprocess.url(props.importData.id),
        {
            image_processing: {
                crop: payload.crop,
                rotate: payload.rotate,
                brightness: payload.brightness,
                contrast: payload.contrast,
                greyscale: payload.greyscale,
                sharpen: payload.sharpen,
            },
        },
    );
}

function retry(): void {
    router.post(
        props.household
            ? householdRetry.url({
                  household: props.household.id,
                  import: props.importData.id,
              })
            : privateRetry.url(props.importData.id),
    );
}
function discard(): void {
    router.post(
        props.household
            ? householdDiscard.url({
                  household: props.household.id,
                  import: props.importData.id,
              })
            : privateDiscard.url(props.importData.id),
    );
}

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: props.household ? 'economy.title' : 'economy.myEconomy',
                href: props.household
                    ? householdEconomyIndex.url(props.household.id)
                    : privateEconomyIndex.url(),
            },
            {
                title: 'economy.import.index.title',
                href: props.household
                    ? householdImportsIndex.url(props.household.id)
                    : privateImportsIndex.url(),
            },
            { title: 'economy.import.reviewTitle' },
        ],
    });
});
</script>

<template>
    <Head :title="t('economy.import.reviewTitle')" />
    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('economy.import.reviewTitle')"
            :description="t('economy.import.reviewDescription')"
        />
        <Alert v-if="importData.error" variant="destructive"
            ><AlertTitle>{{ t('economy.ui.error') }}</AlertTitle
            ><AlertDescription class="flex flex-col items-start gap-2"
                ><span>{{ importErrorMessage }}</span
                ><Button
                    v-if="importData.error.code === 'no_provider'"
                    variant="outline"
                    size="sm"
                    as-child
                    ><Link :href="providerSettingsUrl">{{
                        t('economy.ui.configureProvider')
                    }}</Link></Button
                ></AlertDescription
            ></Alert
        >
        <div class="flex flex-wrap items-center gap-2">
            <ImportStatusPoller :status="importData.status" /><Badge
                v-if="importData.ai_run"
                variant="outline"
                >{{ importData.ai_run.provider_name }} ·
                {{ importData.ai_run.model_name }} ·
                {{
                    t('economy.ui.durationMilliseconds', {
                        duration: importData.ai_run.duration_ms,
                    })
                }}</Badge
            >
        </div>
        <div
            v-if="importData.status === 'ready_for_review'"
            class="grid gap-6 xl:grid-cols-2"
        >
            <Card
                ><CardHeader
                    ><CardTitle class="text-base">{{
                        t('economy.ui.document')
                    }}</CardTitle></CardHeader
                ><CardContent
                    ><ReceiptViewer
                        v-if="importData.document"
                        :url="documentUrl"
                        :mime-type="importData.document.mime_type"
                        :filename="
                            importData.document.original_filename
                        " /></CardContent
            ></Card>
            <Card
                ><CardHeader
                    ><CardTitle class="text-base">{{
                        t('economy.import.extractedData')
                    }}</CardTitle></CardHeader
                ><CardContent class="flex flex-col gap-6"
                    ><EditableExtractedData
                        :model-value="form"
                        :household="Boolean(household)"
                        :errors="form.errors"
                        :validate="validateField"
                        @update:model-value="
                            Object.assign(form, $event)
                        " /><Field
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
                        v-if="!household || form.scope === 'personal'"
                        class="max-w-80"
                    >
                        <ContactPicker
                            v-model="form.contact_id"
                            :label="t('economy.ui.linkedContact')"
                            :placeholder="
                                t('economy.ui.linkedContactPlaceholder')
                            "
                            :scope="household?.id ?? null"
                            :error="form.errors.contact_id"
                        />
                    </div>
                    <Field
                        v-if="accounts.length"
                        :data-invalid="Boolean(form.errors.account_id)"
                        ><FieldLabel for="account_id">{{
                            t('economy.account.label')
                        }}</FieldLabel
                        ><Select
                            v-model="accountSelectValue"
                            @update:model-value="validateField('account_id')"
                        >
                            <SelectTrigger id="account_id">
                                <SelectValue
                                    :placeholder="t('economy.account.none')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem :value="NO_ACCOUNT">{{
                                        t('economy.account.none')
                                    }}</SelectItem>
                                    <SelectItem
                                        v-for="account in accounts"
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
                    ><ParticipantSelector
                        v-if="household && form.scope === 'shared'"
                        v-model="form.participants"
                        :members="members"
                        :amount="form.amount"
                        :currency="form.currency"
                        :error="form.errors.participants"
                        :validate="validateField" /></CardContent
            ></Card>
        </div>
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="importData.status === 'failed'"
                variant="outline"
                @click="retry"
                >{{ t('economy.ui.retry') }}</Button
            >
            <Button
                v-if="canReprocess"
                v-can="reprocessPermission"
                variant="outline"
                @click="processingOpen = true"
            >
                <Sliders data-icon="inline-start" aria-hidden="true" />
                {{ t('economy.import.processing.reanalyze') }}
            </Button>
            <Button
                variant="destructive"
                :disabled="
                    ['confirmed', 'discarded'].includes(importData.status)
                "
                @click="discard"
                >{{ t('economy.import.discard') }}</Button
            >
            <Button
                v-if="importData.status === 'ready_for_review'"
                :disabled="form.processing"
                @click="form.submit()"
                ><Spinner v-if="form.processing" data-icon="inline-start" />{{
                    t('economy.import.saveMovement')
                }}</Button
            >
        </div>

        <ImageProcessingDialog
            v-if="documentImageUrl"
            v-model:open="processingOpen"
            :image-url="documentImageUrl"
            :initial="importData.document?.processing"
            @apply="reprocess"
        />
    </div>
</template>
