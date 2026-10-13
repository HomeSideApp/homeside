<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Check, ShieldX, Undo2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { release as releaseRegistrationBlock } from '@/routes/admin/registration-blocks';
import {
    approve as approveRegistration,
    index as registrationsIndex,
    reject as rejectRegistration,
    releaseRejection as releaseRegistrationRejection,
} from '@/routes/admin/registrations';

interface PendingRegistration {
    id: string;
    name: string;
    email: string;
    channel: string | null;
    created_at: string | null;
    email_verified: boolean;
}

interface RejectedRegistration {
    id: string;
    name: string;
    email: string;
    channel: string | null;
    rejected_at: string | null;
    reason: string | null;
    rejected_by: string | null;
}

interface RegistrationBlockRow {
    id: string;
    reason_code: string;
    reason: string | null;
    source: string;
    source_label: string;
    created_at: string | null;
    created_by: string | null;
}

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    pending: PendingRegistration[];
    rejected: RejectedRegistration[];
    blocks: RegistrationBlockRow[];
    filters: { status: string; channel: string | null };
    options: { channels: Option[]; statuses: Option[] };
}>();

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '#' },
            { title: 'Registration', href: '/admin/registrations' },
        ],
    },
});

const activeTab = ref(props.filters.status);
const channel = ref(props.filters.channel ?? 'all');

// Concurrency conflicts (a lost race, a last-administrator refusal) arrive as
// validation errors on the `user` key; show them instead of a stack trace.
const page = usePage();
const conflict = computed(
    () =>
        (page.props.errors as Record<string, string | undefined> | undefined)
            ?.user ?? null,
);

function applyFilters(): void {
    router.get(
        registrationsIndex.url(),
        {
            status: activeTab.value,
            channel: channel.value === 'all' ? undefined : channel.value,
        },
        { preserveState: true, replace: true },
    );
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

function channelLabel(value: string | null): string {
    if (!value) {
        return '—';
    }

    return (
        props.options.channels.find((option) => option.value === value)?.label ??
        value
    );
}

const rejectTarget = ref<PendingRegistration | null>(null);

function approve(target: PendingRegistration): void {
    router.post(approveRegistration(target.id).url, {}, { preserveScroll: true });
}

function confirmReject(): void {
    rejectTarget.value = null;
}

function releaseRejection(target: RejectedRegistration): void {
    router.post(
        releaseRegistrationRejection(target.id).url,
        {},
        { preserveScroll: true },
    );
}

function releaseBlock(block: RegistrationBlockRow): void {
    router.post(
        releaseRegistrationBlock(block.id).url,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('admin.registrations.panel.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">
                    {{ t('admin.registrations.panel.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.registrations.panel.description') }}
                </p>
            </div>

            <div class="flex items-end gap-2">
                <div class="grid gap-1">
                    <Label for="status-filter">
                        {{ t('admin.registrations.panel.filters.status') }}
                    </Label>
                    <Select v-model="activeTab" @update:model-value="applyFilters">
                        <SelectTrigger id="status-filter" class="w-44">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.statuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{
                                    t(
                                        `admin.registrations.panel.tabs.${option.value}`,
                                    )
                                }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1">
                    <Label for="channel-filter">
                        {{ t('admin.registrations.panel.filters.channel') }}
                    </Label>
                    <Select v-model="channel" @update:model-value="applyFilters">
                        <SelectTrigger id="channel-filter" class="w-52">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                {{ t('admin.registrations.panel.filters.allChannels') }}
                            </SelectItem>
                            <SelectItem
                                v-for="option in options.channels"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </div>

        <Alert v-if="conflict" variant="destructive">
            <ShieldX />
            <AlertTitle>
                {{ t('admin.registrations.panel.conflict.title') }}
            </AlertTitle>
            <AlertDescription>{{ conflict }}</AlertDescription>
        </Alert>

        <Tabs v-model="activeTab" @update:model-value="applyFilters">
            <TabsList>
                <TabsTrigger value="pending">
                    {{ t('admin.registrations.panel.tabs.pending') }}
                    <Badge variant="secondary" class="ms-2">{{ pending.length }}</Badge>
                </TabsTrigger>
                <TabsTrigger value="rejected">
                    {{ t('admin.registrations.panel.tabs.rejected') }}
                    <Badge variant="secondary" class="ms-2">{{ rejected.length }}</Badge>
                </TabsTrigger>
                <TabsTrigger value="blocked">
                    {{ t('admin.registrations.panel.tabs.blocked') }}
                    <Badge variant="secondary" class="ms-2">{{ blocks.length }}</Badge>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="pending">
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {{ t('admin.registrations.panel.pending.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('admin.registrations.panel.pending.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.name')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.email')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.channel')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.registered')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.emailVerified')
                                    }}</TableHead>
                                    <TableHead class="text-right">{{
                                        t('admin.registrations.panel.columns.actions')
                                    }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in pending"
                                    :key="row.id"
                                >
                                    <TableCell class="font-medium">{{
                                        row.name
                                    }}</TableCell>
                                    <TableCell class="lowercase">{{
                                        row.email
                                    }}</TableCell>
                                    <TableCell>{{
                                        channelLabel(row.channel)
                                    }}</TableCell>
                                    <TableCell>{{
                                        formatDate(row.created_at)
                                    }}</TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="
                                                row.email_verified
                                                    ? 'secondary'
                                                    : 'outline'
                                            "
                                        >
                                            {{
                                                row.email_verified
                                                    ? t(
                                                          'admin.registrations.panel.yes',
                                                      )
                                                    : t(
                                                          'admin.registrations.panel.no',
                                                      )
                                            }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell
                                        class="flex justify-end gap-2"
                                    >
                                        <Button
                                            size="sm"
                                            @click="approve(row)"
                                        >
                                            <Check class="size-4" />
                                            {{
                                                t(
                                                    'admin.registrations.panel.pending.approve',
                                                )
                                            }}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="border-destructive/50 text-destructive hover:bg-destructive/10"
                                            @click="rejectTarget = row"
                                        >
                                            {{
                                                t(
                                                    'admin.registrations.panel.pending.reject',
                                                )
                                            }}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="pending.length === 0">
                                    <TableCell
                                        :colspan="6"
                                        class="h-24 text-center text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                'admin.registrations.panel.pending.empty',
                                            )
                                        }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="rejected">
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {{ t('admin.registrations.panel.rejected.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('admin.registrations.panel.rejected.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.name')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.email')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.rejectedBy')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.rejectedAt')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.reason')
                                    }}</TableHead>
                                    <TableHead class="text-right">{{
                                        t('admin.registrations.panel.columns.actions')
                                    }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in rejected"
                                    :key="row.id"
                                >
                                    <TableCell class="font-medium">{{
                                        row.name
                                    }}</TableCell>
                                    <TableCell class="lowercase">{{
                                        row.email
                                    }}</TableCell>
                                    <TableCell>{{
                                        row.rejected_by ??
                                        t('admin.registrations.panel.unknown')
                                    }}</TableCell>
                                    <TableCell>{{
                                        formatDate(row.rejected_at)
                                    }}</TableCell>
                                    <TableCell class="max-w-xs truncate">{{
                                        row.reason ??
                                        t('admin.registrations.panel.unknown')
                                    }}</TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="releaseRejection(row)"
                                        >
                                            <Undo2 class="size-4" />
                                            {{
                                                t(
                                                    'admin.registrations.panel.rejected.release',
                                                )
                                            }}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="rejected.length === 0">
                                    <TableCell
                                        :colspan="6"
                                        class="h-24 text-center text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                'admin.registrations.panel.rejected.empty',
                                            )
                                        }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>

            <TabsContent value="blocked">
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {{ t('admin.registrations.panel.blocked.title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ t('admin.registrations.panel.blocked.description') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.reason')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.source')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.createdBy')
                                    }}</TableHead>
                                    <TableHead>{{
                                        t('admin.registrations.panel.columns.createdAt')
                                    }}</TableHead>
                                    <TableHead class="text-right">{{
                                        t('admin.registrations.panel.columns.actions')
                                    }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in blocks" :key="row.id">
                                    <TableCell class="max-w-xs truncate">{{
                                        row.reason ??
                                        t('admin.registrations.panel.unknown')
                                    }}</TableCell>
                                    <TableCell>{{ row.source_label }}</TableCell>
                                    <TableCell>{{
                                        row.created_by ??
                                        t('admin.registrations.panel.unknown')
                                    }}</TableCell>
                                    <TableCell>{{
                                        formatDate(row.created_at)
                                    }}</TableCell>
                                    <TableCell class="text-right">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            @click="releaseBlock(row)"
                                        >
                                            <Undo2 class="size-4" />
                                            {{
                                                t(
                                                    'admin.registrations.panel.blocked.release',
                                                )
                                            }}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="blocks.length === 0">
                                    <TableCell
                                        :colspan="5"
                                        class="h-24 text-center text-muted-foreground"
                                    >
                                        {{
                                            t(
                                                'admin.registrations.panel.blocked.empty',
                                            )
                                        }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>

    <Dialog
        :open="rejectTarget !== null"
        @update:open="
            (open) => {
                if (!open) rejectTarget = null;
            }
        "
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ t('admin.registrations.panel.rejectDialog.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        t('admin.registrations.panel.rejectDialog.description', {
                            name: rejectTarget?.name ?? '',
                        })
                    }}
                </DialogDescription>
            </DialogHeader>

            <Form
                v-if="rejectTarget"
                :action="rejectRegistration(rejectTarget.id).url"
                method="POST"
                v-slot="{ errors, processing, validate }"
                class="flex flex-col gap-4"
                @success="confirmReject"
            >
                <div class="grid gap-2">
                    <Label for="reject-reason">
                        {{ t('admin.registrations.panel.rejectDialog.reason') }}
                    </Label>
                    <Textarea
                        id="reject-reason"
                        name="reason"
                        required
                        @input="validate"
                    />
                    <InputError :message="errors.reason" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="rejectTarget = null"
                    >
                        {{ t('common.actions.cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="processing"
                    >
                        {{
                            t('admin.registrations.panel.rejectDialog.confirm')
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
