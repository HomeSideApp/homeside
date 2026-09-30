<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Lock, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type {
    PaymentMethod,
    PaymentMethodKind,
} from '@/components/economy/types';
import Heading from '@/components/Heading.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty, EmptyHeader, EmptyTitle } from '@/components/ui/empty';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { vCan } from '@/directives/can';
import { index as accountsIndex } from '@/routes/economy/me/accounts';
import {
    destroy as methodsDestroy,
    store as methodsStore,
} from '@/routes/economy/me/payment-methods';
const { t } = useI18n();

const props = defineProps<{
    paymentMethods: PaymentMethod[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'economy.accounts.title', href: accountsIndex.url() },
        { title: 'economy.paymentMethods.title' },
    ],
});

const kinds: PaymentMethodKind[] = [
    'cash',
    'card',
    'bank_transfer',
    'digital_wallet',
    'crypto',
    'cheque',
    'other',
];

const pendingDelete = ref<PaymentMethod | null>(null);

const form = useForm('post', methodsStore.url(), {
    name: '',
    kind: 'other' as PaymentMethodKind,
});

function submit(): void {
    form.submit({
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function confirmDelete(): void {
    if (!pendingDelete.value) {
        return;
    }

    router.delete(methodsDestroy.url(pendingDelete.value.id), {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    });
}
</script>

<template>
    <Head :title="t('economy.paymentMethods.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('economy.paymentMethods.title')"
            :description="t('economy.paymentMethods.description')"
        />

        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{
                    t('economy.paymentMethods.createTitle')
                }}</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    class="flex flex-col gap-4 md:flex-row md:items-end"
                    @submit.prevent="submit"
                >
                    <FieldGroup
                        class="grid flex-1 gap-4 md:grid-cols-2"
                    >
                        <Field :data-invalid="Boolean(form.errors.name)">
                            <FieldLabel for="method-name">{{
                                t('economy.paymentMethod.name')
                            }}</FieldLabel>
                            <Input
                                id="method-name"
                                v-model="form.name"
                                :aria-invalid="Boolean(form.errors.name)"
                                @blur="form.validate('name')"
                                @input="form.validate('name')"
                            />
                            <FieldError v-if="form.errors.name">{{
                                form.errors.name
                            }}</FieldError>
                        </Field>
                        <Field>
                            <FieldLabel for="method-kind">{{
                                t('economy.paymentMethod.kind')
                            }}</FieldLabel>
                            <Select
                                v-model="form.kind"
                                @update:model-value="form.validate('kind')"
                            >
                                <SelectTrigger id="method-kind">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem
                                            v-for="kind in kinds"
                                            :key="kind"
                                            :value="kind"
                                        >
                                            {{
                                                t(
                                                    `economy.paymentMethods.kinds.${kind}`,
                                                )
                                            }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <FieldError v-if="form.errors.kind">{{
                                form.errors.kind
                            }}</FieldError>
                        </Field>
                    </FieldGroup>
                    <Button type="submit" :disabled="form.processing">
                        <Plus data-icon="inline-start" aria-hidden="true" />
                        {{ t('economy.paymentMethods.create') }}
                    </Button>
                </form>
            </CardContent>
        </Card>

        <Empty v-if="paymentMethods.length === 0">
            <EmptyHeader>
                <EmptyTitle>{{
                    t('economy.paymentMethods.empty')
                }}</EmptyTitle>
            </EmptyHeader>
        </Empty>

        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="method in paymentMethods" :key="method.id">
                <CardContent
                    class="flex items-center justify-between gap-3 pt-6"
                >
                    <div class="flex flex-col gap-1">
                        <span class="font-medium">{{ method.name }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{
                                t(
                                    `economy.paymentMethods.kinds.${method.kind}`,
                                )
                            }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Badge variant="outline">
                                        <Lock aria-hidden="true" />
                                        {{
                                            t(
                                                'economy.paymentMethods.global',
                                            )
                                        }}
                                    </Badge>
                                </TooltipTrigger>
                                <TooltipContent>{{
                                    t('economy.paymentMethods.globalHelp')
                                }}</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <Button
                            v-if="!method.is_global"
                            v-can="'economy.me.payment-methods.destroy'"
                            variant="ghost"
                            size="icon-sm"
                            :aria-label="t('economy.ui.delete')"
                            @click="pendingDelete = method"
                        >
                            <Trash2 aria-hidden="true" />
                            <span class="sr-only">{{
                                t('economy.ui.delete')
                            }}</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <AlertDialog
            :open="pendingDelete !== null"
            @update:open="pendingDelete = null"
        >
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{
                        t('economy.paymentMethods.deleteTitle')
                    }}</AlertDialogTitle>
                    <AlertDialogDescription>{{
                        t('economy.paymentMethods.deleteDetail', {
                            name: pendingDelete?.name,
                        })
                    }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{
                        t('economy.ui.cancel')
                    }}</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDelete">{{
                        t('economy.ui.delete')
                    }}</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
