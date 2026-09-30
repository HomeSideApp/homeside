<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Sliders } from '@lucide/vue';
import { computed, onUnmounted, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import DocumentUpload from '@/components/economy/DocumentUpload.vue';
import ImageProcessingDialog from '@/components/economy/ImageProcessingDialog.vue';
import type { ImageProcessingPayload } from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
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
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useEconomyDestinations } from '@/composables/useEconomyDestinations';
import { index as privateEconomyIndex } from '@/routes/economy/me';
import { store as privateImportStore } from '@/routes/economy/me/imports';
import { index as householdEconomyIndex } from '@/routes/households/economy';
import { store as householdImportStore } from '@/routes/households/economy/imports';

const { t } = useI18n();
const props = defineProps<{ household: { id: string; name: string } | null }>();
const sections = [
    { key: 'place', label: t('economy.ui.place') },
    { key: 'date', label: t('economy.ui.date') },
    { key: 'items', label: t('economy.transaction.items') },
    { key: 'taxes', label: t('economy.transaction.taxes') },
] as const;

/**
 * In a context route the destination is fixed by the URL; otherwise it defaults to the user's
 * preferred destination, matching the dashboard selector.
 */
const { destinations, defaultDestinationId } = useEconomyDestinations();
const PRIVATE_VALUE = '__private__';
const destinationLocked = computed(() => props.household !== null);
const selectedDestination = ref<string>(
    props.household?.id ??
        (defaultDestinationId.value === null
            ? PRIVATE_VALUE
            : defaultDestinationId.value),
);

const submitUrl = computed(() => {
    const householdId = destinationLocked.value
        ? props.household!.id
        : selectedDestination.value === PRIVATE_VALUE
          ? null
          : selectedDestination.value;

    return householdId
        ? householdImportStore.url(householdId)
        : privateImportStore.url();
});

const form = useForm('post', submitUrl.value, {
    file: null as File | null,
    sections: { place: true, date: true, items: true, taxes: true },
    image_processing: null as ImageProcessingPayload | null,
});

const processingOpen = ref(false);
const previewUrl = ref<string | null>(null);

watch(
    () => form.file,
    (file) => {
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = null;
        }

        form.image_processing = null;

        if (file && file.type.startsWith('image/')) {
            previewUrl.value = URL.createObjectURL(file);
        }
    },
);

onUnmounted(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});

function applyProcessing(payload: ImageProcessingPayload): void {
    form.image_processing = payload;
}

function clearProcessing(): void {
    form.image_processing = null;
}

function submit(): void {
    if (!form.file) {
        form.setError('file', t('economy.ui.selectDocument'));

        return;
    }

    form.post(submitUrl.value);
}

function validateDocument(): void {
    form.validateFiles().validate('file');
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
            { title: 'economy.importTicket' },
        ],
    });
});
</script>

<template>
    <Head :title="t('economy.importTicket')" />
    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="
                household
                    ? t('economy.importTicket')
                    : t('economy.importPrivate')
            "
            :description="t('economy.importTicketDescription')"
        />
        <Alert v-if="form.hasErrors" variant="destructive">
            <AlertTitle>{{ t('economy.ui.validationErrors') }}</AlertTitle>
            <AlertDescription>
                <ul class="list-disc pl-4">
                    <li v-for="(message, field) in form.errors" :key="field">
                        {{ message }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>
        <Card class="max-w-2xl">
            <CardHeader
                ><CardTitle class="text-base">{{
                    t('economy.import.uploadTitle')
                }}</CardTitle></CardHeader
            >
            <CardContent class="flex flex-col gap-6">
                <Field
                    ><FieldLabel>{{
                        t('economy.destination.label')
                    }}</FieldLabel
                    ><Select
                        v-model="selectedDestination"
                        :disabled="destinationLocked"
                    >
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-if="household === null"
                                :value="PRIVATE_VALUE"
                            >
                                {{ t('economy.destination.private') }}
                            </SelectItem>
                            <SelectItem
                                v-for="destination in destinations.filter(
                                    (item) => item.id !== null,
                                )"
                                :key="destination.id as string"
                                :value="destination.id as string"
                            >
                                {{ destination.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </Field>

                <DocumentUpload
                    v-model="form.file"
                    :error="form.errors.file"
                    :disabled="form.processing"
                    :validate="validateDocument"
                />

                <div
                    v-if="previewUrl"
                    class="flex flex-col gap-3 rounded-lg border p-3"
                >
                    <img
                        :src="previewUrl"
                        :alt="t('economy.import.processing.preview')"
                        class="max-h-64 w-full rounded object-contain"
                    />
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="processingOpen = true"
                        >
                            <Sliders data-icon="inline-start" aria-hidden="true" />
                            {{ t('economy.import.processing.adjust') }}
                        </Button>
                        <Badge
                            v-if="form.image_processing"
                            variant="secondary"
                        >
                            {{ t('economy.import.processing.applied') }}
                        </Badge>
                        <Button
                            v-if="form.image_processing"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="clearProcessing"
                        >
                            {{ t('economy.import.processing.clear') }}
                        </Button>
                    </div>
                </div>
                <FieldSet
                    ><FieldLegend>{{
                        t('economy.ui.dataToExtract')
                    }}</FieldLegend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Field
                            v-for="section in sections"
                            :key="section.key"
                            orientation="horizontal"
                            ><Checkbox
                                v-model="form.sections[section.key]"
                            /><FieldContent
                                ><FieldLabel>{{
                                    section.label
                                }}</FieldLabel></FieldContent
                            ></Field
                        >
                    </div></FieldSet
                >
                <div class="flex flex-col items-start gap-2">
                    <Badge variant="outline">{{
                        t('economy.import.providerNote')
                    }}</Badge>
                    <p class="text-xs text-muted-foreground">
                        {{ t('economy.import.autoResolution') }}
                    </p>
                </div>
            </CardContent>
            <CardFooter class="justify-end"
                ><Button :disabled="form.processing" @click="submit"
                    ><Spinner
                        v-if="form.processing"
                        data-icon="inline-start"
                    />{{ t('economy.ui.analyze') }}</Button
                ></CardFooter
            >
        </Card>

        <ImageProcessingDialog
            v-if="previewUrl"
            v-model:open="processingOpen"
            :image-url="previewUrl"
            :initial="form.image_processing"
            @apply="applyProcessing"
        />
    </div>
</template>
