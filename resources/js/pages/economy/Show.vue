<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import AttachmentGallery from '@/components/economy/AttachmentGallery.vue';
import type {
    EconomyAttachment,
    EconomyTransaction,
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { vCan } from '@/directives/can';
import {
    destroy as privateDestroy,
    edit as privateEdit,
    index as privateEconomyIndex,
} from '@/routes/economy/me';
import {
    destroy as privateAttachmentDestroy,
    file as privateAttachmentFile,
    store as privateAttachmentStore,
} from '@/routes/economy/me/attachments';
import { file as privateDocumentFile } from '@/routes/economy/me/documents';
import {
    destroy as householdDestroy,
    edit as householdEdit,
    index as householdEconomyIndex,
} from '@/routes/households/economy';
import {
    destroy as householdAttachmentDestroy,
    file as householdAttachmentFile,
    store as householdAttachmentStore,
} from '@/routes/households/economy/attachments';
import { file as householdDocumentFile } from '@/routes/households/economy/documents';

const { t } = useI18n();
const page = usePage();
const props = defineProps<{
    household: { id: string; name: string } | null;
    transaction: EconomyTransaction;
}>();
const confirmDelete = ref(false);
const canModify = computed(
    () => props.transaction.created_by.id === String(page.props.auth.user.id),
);
const modifyPermission = computed(() =>
    props.household ? 'households.economy.edit' : 'economy.me.edit',
);
const editUrl = computed(() =>
    props.household
        ? householdEdit.url({
              household: props.household.id,
              transaction: props.transaction.id,
          })
        : privateEdit.url(props.transaction.id),
);
const documentUrl = computed(() =>
    !props.transaction.source_document
        ? null
        : props.household
          ? householdDocumentFile.url({
                household: props.household.id,
                document: props.transaction.source_document.id,
            })
          : privateDocumentFile.url(props.transaction.source_document.id),
);
const attachmentStoreUrl = computed(() =>
    props.household
        ? householdAttachmentStore.url({
              household: props.household.id,
              transaction: props.transaction.id,
          })
        : privateAttachmentStore.url(props.transaction.id),
);
const attachmentFileUrl = (attachment: EconomyAttachment): string =>
    props.household
        ? householdAttachmentFile.url({
              household: props.household.id,
              transaction: props.transaction.id,
              attachment: attachment.id,
          })
        : privateAttachmentFile.url({
              transaction: props.transaction.id,
              attachment: attachment.id,
          });
const attachmentDestroyUrl = (attachment: EconomyAttachment): string =>
    props.household
        ? householdAttachmentDestroy.url({
              household: props.household.id,
              transaction: props.transaction.id,
              attachment: attachment.id,
          })
        : privateAttachmentDestroy.url({
              transaction: props.transaction.id,
              attachment: attachment.id,
          });
const recurrenceWeekdays = computed(() => {
    const keys = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ] as const;

    return props.transaction.recurrence_weekdays
        .map((weekday) => t(`economy.recurrence.weekdays.${keys[weekday - 1]}`))
        .join(', ');
});
function destroy(): void {
    router.delete(
        props.household
            ? householdDestroy.url({
                  household: props.household.id,
                  transaction: props.transaction.id,
              })
            : privateDestroy.url(props.transaction.id),
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
            { title: 'economy.show' },
        ],
    });
});
</script>

<template>
    <Head :title="transaction.title" />
    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                variant="small"
                :title="transaction.title"
                :description="transaction.place ?? undefined"
            />
            <div v-if="canModify" v-can="modifyPermission" class="flex gap-2">
                <Button variant="outline" as-child
                    ><Link :href="editUrl">{{
                        t('economy.ui.edit')
                    }}</Link></Button
                ><Button variant="destructive" @click="confirmDelete = true">{{
                    t('economy.ui.delete')
                }}</Button>
            </div>
        </div>
        <Card class="max-w-4xl"
            ><CardHeader
                ><CardTitle
                    class="flex flex-wrap items-center justify-between gap-3"
                    ><span>{{ transaction.title }}</span
                    ><span class="font-mono"
                        >{{ transaction.type === 'expense' ? '−' : '+'
                        }}{{ transaction.amount }}
                        {{ transaction.currency }}</span
                    ></CardTitle
                ></CardHeader
            ><CardContent class="flex flex-col gap-5">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        :variant="
                            transaction.type === 'expense'
                                ? 'destructive'
                                : 'default'
                        "
                        >{{
                            transaction.type === 'expense'
                                ? t('economy.transaction.typeExpense')
                                : t('economy.transaction.typeIncome')
                        }}</Badge
                    ><Badge variant="secondary">{{
                        transaction.scope === 'shared'
                            ? t('economy.transaction.shared')
                            : t('economy.transaction.personal')
                    }}</Badge
                    ><Badge
                        v-if="transaction.recurrence_frequency"
                        variant="outline"
                        >{{ t('economy.recurrence.sourceBadge') }} ·
                        {{
                            t(
                                `economy.recurrence.frequencies.${transaction.recurrence_frequency}`,
                            )
                        }}</Badge
                    ><Badge
                        v-else-if="transaction.recurrence_parent_id"
                        variant="outline"
                        >{{ t('economy.recurrence.occurrenceBadge') }}</Badge
                    ><span
                        v-if="transaction.occurred_at"
                        class="text-sm text-muted-foreground"
                        >{{
                            new Date(
                                transaction.occurred_at,
                            ).toLocaleDateString()
                        }}</span
                    >
                </div>
                <p
                    v-if="
                        transaction.recurrence_frequency === 'weekly' &&
                        recurrenceWeekdays
                    "
                    class="text-sm text-muted-foreground"
                >
                    {{
                        t('economy.recurrence.selectedWeekdays', {
                            days: recurrenceWeekdays,
                        })
                    }}
                </p>
                <p
                    v-if="transaction.recurrence_next_at"
                    class="text-sm text-muted-foreground"
                >
                    {{
                        t('economy.recurrence.nextOccurrence', {
                            date: new Date(
                                transaction.recurrence_next_at,
                            ).toLocaleDateString(),
                        })
                    }}
                </p>
                <p
                    v-else-if="transaction.recurrence_frequency"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('economy.recurrence.ended') }}
                </p>
                <Separator />
                <div
                    v-if="transaction.items?.length"
                    class="flex flex-col gap-2"
                >
                    <h2 class="font-medium">
                        {{ t('economy.transaction.items') }}
                    </h2>
                    <Table
                        ><TableHeader
                            ><TableRow
                                ><TableHead>{{
                                    t('economy.ui.name')
                                }}</TableHead
                                ><TableHead>{{
                                    t('economy.ui.quantity')
                                }}</TableHead
                                ><TableHead class="text-right">{{
                                    t('economy.ui.total')
                                }}</TableHead></TableRow
                            ></TableHeader
                        ><TableBody
                            ><TableRow
                                v-for="item in transaction.items"
                                :key="item.id"
                                ><TableCell>{{ item.name }}</TableCell
                                ><TableCell>{{ item.quantity }}</TableCell
                                ><TableCell class="text-right font-mono"
                                    >{{ item.total }}
                                    {{ transaction.currency }}</TableCell
                                ></TableRow
                            ></TableBody
                        ></Table
                    >
                </div>
                <div
                    v-if="transaction.taxes?.length"
                    class="flex flex-col gap-2"
                >
                    <h2 class="font-medium">
                        {{ t('economy.transaction.taxes') }}
                    </h2>
                    <Table
                        ><TableHeader
                            ><TableRow
                                ><TableHead>{{ t('economy.ui.tax') }}</TableHead
                                ><TableHead>{{
                                    t('economy.ui.type')
                                }}</TableHead
                                ><TableHead class="text-right">{{
                                    t('economy.ui.taxAmount')
                                }}</TableHead></TableRow
                            ></TableHeader
                        ><TableBody
                            ><TableRow
                                v-for="tax in transaction.taxes"
                                :key="tax.id"
                                ><TableCell>{{ tax.name }}</TableCell
                                ><TableCell>{{ tax.rate }}%</TableCell
                                ><TableCell class="text-right font-mono"
                                    >{{ tax.tax_amount }}
                                    {{ transaction.currency }}</TableCell
                                ></TableRow
                            ></TableBody
                        ></Table
                    >
                </div>
                <div
                    v-if="transaction.participants?.length"
                    class="flex flex-col gap-2"
                >
                    <h2 class="font-medium">
                        {{ t('economy.ui.participants') }}
                    </h2>
                    <Table
                        ><TableHeader
                            ><TableRow
                                ><TableHead>{{
                                    t('economy.ui.name')
                                }}</TableHead
                                ><TableHead>{{
                                    t('economy.ui.splitType')
                                }}</TableHead
                                ><TableHead class="text-right">{{
                                    t('economy.ui.total')
                                }}</TableHead></TableRow
                            ></TableHeader
                        ><TableBody
                            ><TableRow
                                v-for="participant in transaction.participants"
                                :key="participant.id"
                                ><TableCell
                                    ><div class="flex items-center gap-2">
                                        <Avatar class="size-7">
                                            <AvatarImage
                                                v-if="
                                                    participant.household_member
                                                        .contact?.avatar_url
                                                "
                                                :src="
                                                    participant.household_member
                                                        .contact.avatar_url
                                                "
                                                :alt="
                                                    participant.household_member
                                                        .user.name
                                                "
                                            />
                                            <AvatarFallback>{{
                                                participant.household_member.user.name
                                                    .charAt(0)
                                                    .toUpperCase()
                                            }}</AvatarFallback>
                                        </Avatar>
                                        <div class="flex min-w-0 flex-col">
                                            <span class="font-medium">{{
                                                participant.household_member
                                                    .user.name
                                            }}</span>
                                            <span
                                                v-if="
                                                    participant.household_member
                                                        .contact &&
                                                    (participant
                                                        .household_member
                                                        .contact.email ||
                                                        participant
                                                            .household_member
                                                            .contact.phone)
                                                "
                                                class="truncate text-xs text-muted-foreground"
                                                >{{
                                                    [
                                                        participant
                                                            .household_member
                                                            .contact.email,
                                                        participant
                                                            .household_member
                                                            .contact.phone,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')
                                                }}</span
                                            >
                                        </div>
                                    </div></TableCell
                                ><TableCell class="text-muted-foreground"
                                    >{{
                                        t(
                                            `economy.ui.split${participant.split_type.charAt(0).toUpperCase()}${participant.split_type.slice(1)}`,
                                        )
                                    }}{{
                                        participant.percentage !== null
                                            ? ` (${participant.percentage}%)`
                                            : ''
                                    }}</TableCell
                                ><TableCell class="text-right font-mono"
                                    >{{ participant.amount }}
                                    {{ transaction.currency }}</TableCell
                                ></TableRow
                            ></TableBody
                        ></Table
                    >
                </div>
                <div
                    v-if="transaction.contact"
                    class="flex items-center gap-2 text-sm"
                >
                    <img
                        v-if="transaction.contact.avatar_url"
                        :src="transaction.contact.avatar_url"
                        alt=""
                        class="size-7 rounded-full object-cover"
                    />
                    <span class="text-muted-foreground">{{
                        t('economy.ui.linkedContact')
                    }}</span>
                    <span class="font-medium">{{
                        transaction.contact.display_name
                    }}</span>
                </div>
                <p
                    v-if="transaction.notes"
                    class="text-sm text-muted-foreground"
                >
                    {{ transaction.notes }}
                </p>
                <Button
                    v-if="transaction.source_document && documentUrl"
                    variant="outline"
                    as-child
                    class="self-start"
                    ><a :href="documentUrl"
                        >{{ t('economy.ui.download') }}
                        {{ transaction.source_document.original_filename }}</a
                    ></Button
                >
                <AttachmentGallery
                    v-if="transaction.attachments?.length"
                    :attachments="transaction.attachments"
                    :store-url="attachmentStoreUrl"
                    :file-url-for="attachmentFileUrl"
                    :destroy-url-for="attachmentDestroyUrl"
                    readonly
                /> </CardContent
        ></Card>
    </div>
    <AlertDialog v-model:open="confirmDelete"
        ><AlertDialogContent
            ><AlertDialogHeader
                ><AlertDialogTitle>{{
                    t('economy.ui.deleteTransactionTitle')
                }}</AlertDialogTitle
                ><AlertDialogDescription>{{
                    t('economy.ui.deleteTransactionDetail')
                }}</AlertDialogDescription></AlertDialogHeader
            ><AlertDialogFooter
                ><AlertDialogCancel>{{
                    t('economy.ui.cancel')
                }}</AlertDialogCancel
                ><AlertDialogAction @click="destroy">{{
                    t('economy.ui.delete')
                }}</AlertDialogAction></AlertDialogFooter
            ></AlertDialogContent
        ></AlertDialog
    >
</template>
