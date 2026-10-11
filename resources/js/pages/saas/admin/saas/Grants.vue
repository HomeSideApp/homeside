<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
import {
    index as grantsIndex,
    revoke as revokeGrant,
    store as storeGrant,
} from '@/routes/admin/saas/grants';

interface GrantRow {
    id: string;
    user_email: string | null;
    plan: string | null;
    version: number | null;
    starts_at: string | null;
    expires_at: string | null;
    revoked_at: string | null;
    reason: string;
    actor_email: string | null;
    created_at: string | null;
}

const props = defineProps<{
    grants: {
        data: GrantRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { email?: string };
    historyUser: { id: string; email: string } | null;
    planVersions: Array<{ id: string; label: string }>;
}>();

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({ title: t('saas.admin.grants.title') });
});

const form = ref({
    email: '',
    plan_version_id: '',
    expires_at: '',
    reason: '',
});

const revokeTarget = ref<GrantRow | null>(null);
const revokeReason = ref('');

function assign(): void {
    router.post(storeGrant().url, form.value, {
        preserveScroll: true,
        onSuccess: () => {
            form.value = { email: '', plan_version_id: '', expires_at: '', reason: '' };
        },
    });
}

function searchHistory(): void {
    router.get(grantsIndex().url, form.value.email ? { email: form.value.email } : {}, {
        preserveScroll: true,
    });
}

function confirmRevoke(): void {
    if (!revokeTarget.value) {
        return;
    }

    router.post(
        revokeGrant({ grant: revokeTarget.value.id }).url,
        { reason: revokeReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                revokeTarget.value = null;
                revokeReason.value = '';
            },
        },
    );
}

function date(value: string | null): string {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head :title="t('saas.admin.grants.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <h1 class="text-2xl font-bold">{{ t('saas.admin.grants.title') }}</h1>

        <Card>
            <CardHeader>
                <CardTitle>{{ t('saas.admin.grants.assignTitle') }}</CardTitle>
                <CardDescription>
                    {{ t('saas.admin.grants.assignDescription') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    class="grid gap-3 sm:grid-cols-5 sm:items-end"
                    @submit.prevent="assign"
                >
                    <div class="grid gap-1">
                        <Label for="grant-email">{{ t('saas.admin.grants.email') }}</Label>
                        <Input id="grant-email" v-model="form.email" type="email" required />
                    </div>
                    <div class="grid gap-1">
                        <Label>{{ t('saas.admin.grants.planVersion') }}</Label>
                        <Select v-model="form.plan_version_id">
                            <SelectTrigger>
                                <SelectValue
                                    :placeholder="t('saas.admin.grants.selectVersion')"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="version in planVersions"
                                    :key="version.id"
                                    :value="version.id"
                                >
                                    {{ version.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1">
                        <Label for="grant-expires">{{ t('saas.admin.grants.expiresAt') }}</Label>
                        <Input id="grant-expires" v-model="form.expires_at" type="date" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="grant-reason">{{ t('saas.admin.grants.reason') }}</Label>
                        <Input id="grant-reason" v-model="form.reason" required minlength="3" />
                    </div>
                    <Button type="submit">{{ t('saas.admin.grants.assign') }}</Button>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>
                    {{ historyUser
                        ? t('saas.admin.grants.historyFor', { email: historyUser.email })
                        : t('saas.admin.grants.historyTitle') }}
                </CardTitle>
                <CardDescription>
                    {{ t('saas.admin.grants.historyDescription') }}
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <form class="flex gap-2" @submit.prevent="searchHistory">
                    <Input
                        v-model="form.email"
                        :placeholder="t('saas.admin.grants.searchPlaceholder')"
                        class="max-w-xs"
                    />
                    <Button type="submit" variant="outline">
                        {{ t('saas.admin.grants.search') }}
                    </Button>
                </form>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('saas.admin.grants.email') }}</TableHead>
                            <TableHead>{{ t('saas.admin.grants.plan') }}</TableHead>
                            <TableHead>{{ t('saas.admin.grants.expiresAt') }}</TableHead>
                            <TableHead>{{ t('saas.admin.grants.status') }}</TableHead>
                            <TableHead>{{ t('saas.admin.grants.reason') }}</TableHead>
                            <TableHead>{{ t('saas.admin.grants.actor') }}</TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="grant in grants.data" :key="grant.id">
                            <TableCell class="lowercase">{{ grant.user_email }}</TableCell>
                            <TableCell>
                                {{ grant.plan }} v{{ grant.version }}
                            </TableCell>
                            <TableCell>{{ date(grant.expires_at) }}</TableCell>
                            <TableCell>
                                <Badge :variant="grant.revoked_at ? 'destructive' : 'default'">
                                    {{ grant.revoked_at
                                        ? t('saas.admin.grants.revoked')
                                        : t('saas.admin.grants.active') }}
                                </Badge>
                            </TableCell>
                            <TableCell class="max-w-48 truncate text-xs">
                                {{ grant.reason }}
                            </TableCell>
                            <TableCell class="lowercase text-xs">
                                {{ grant.actor_email ?? '—' }}
                            </TableCell>
                            <TableCell>
                                <Button
                                    v-if="!grant.revoked_at"
                                    size="sm"
                                    variant="destructive"
                                    @click="revokeTarget = grant"
                                >
                                    {{ t('saas.admin.grants.revoke') }}
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <div
            v-if="revokeTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="revokeTarget = null"
        >
            <Card class="w-full max-w-md">
                <CardHeader>
                    <CardTitle>{{ t('saas.admin.grants.revokeTitle') }}</CardTitle>
                    <CardDescription>
                        {{ t('saas.admin.grants.revokeDescription', { email: revokeTarget.user_email ?? '' }) }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-3">
                    <div class="grid gap-1">
                        <Label for="revoke-reason">{{ t('saas.admin.grants.reason') }}</Label>
                        <Input id="revoke-reason" v-model="revokeReason" required minlength="3" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button variant="outline" @click="revokeTarget = null">
                            {{ t('common.actions.cancel') }}
                        </Button>
                        <Button variant="destructive" :disabled="revokeReason.length < 3" @click="confirmRevoke">
                            {{ t('saas.admin.grants.revoke') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
