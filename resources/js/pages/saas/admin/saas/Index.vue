<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface Entitlement {
    plan: string;
    version: number;
    origin: string;
    usage: Record<string, { used: number; cap: number | null }>;
    expires_at: string | null;
}

interface OverviewUser {
    id: string;
    email: string;
    entitlement: Entitlement;
}

const props = defineProps<{
    users: {
        data: OverviewUser[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { search?: string };
}>();

const { t } = useI18n();

watchEffect(() => {
    setLayoutProps({ title: t('saas.admin.overview.title') });
});

const ORIGIN_VARIANTS: Record<string, 'default' | 'secondary' | 'outline'> = {
    granted: 'default',
    subscribed: 'default',
    trial: 'secondary',
    free: 'secondary',
};

const search = computed(() => props.filters.search ?? '');

function originVariant(origin: string): 'default' | 'secondary' | 'outline' {
    return ORIGIN_VARIANTS[origin] ?? 'outline';
}

function expiry(expiresAt: string | null): string {
    if (!expiresAt) {
        return t('saas.admin.overview.noExpiry');
    }
    return new Date(expiresAt).toLocaleDateString();
}

function usageSummary(entitlement: Entitlement): string {
    const entries = Object.entries(entitlement.usage ?? {});
    if (entries.length === 0) {
        return t('saas.admin.overview.noUsage');
    }
    return entries
        .map(([key, value]) => {
            const cap = value.cap === null ? '∞' : value.cap;
            return `${key}: ${value.used}/${cap}`;
        })
        .join(' · ');
}

function pageHref(page: number): string {
    const params = new URLSearchParams();
    if (search.value) {
        params.set('search', search.value);
    }
    params.set('page', String(page));
    return `?${params.toString()}`;
}
</script>

<template>
    <Head :title="t('saas.admin.overview.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">
                {{ t('saas.admin.overview.title') }}
            </h1>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>{{ t('saas.admin.overview.listTitle') }}</CardTitle>
                <CardDescription>
                    {{ t('saas.admin.overview.description') }}
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-col gap-4">
                <form class="flex gap-2" method="get" action="/admin/saas">
                    <Input
                        name="search"
                        :model-value="search"
                        :placeholder="t('saas.admin.overview.searchPlaceholder')"
                        class="max-w-xs"
                    />
                    <Button type="submit" variant="outline">
                        {{ t('saas.admin.overview.search') }}
                    </Button>
                </form>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{
                                t('saas.admin.overview.email')
                            }}</TableHead>
                            <TableHead>{{
                                t('saas.admin.overview.plan')
                            }}</TableHead>
                            <TableHead>{{
                                t('saas.admin.overview.origin')
                            }}</TableHead>
                            <TableHead>{{
                                t('saas.admin.overview.expiry')
                            }}</TableHead>
                            <TableHead>{{
                                t('saas.admin.overview.usage')
                            }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="user in users.data"
                            :key="user.id"
                        >
                            <TableCell class="font-medium lowercase">
                                {{ user.email }}
                            </TableCell>
                            <TableCell>
                                {{ user.entitlement.plan }}
                                v{{ user.entitlement.version }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="originVariant(user.entitlement.origin)">
                                    {{ user.entitlement.origin }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{ expiry(user.entitlement.expires_at) }}
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                {{ usageSummary(user.entitlement) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div
                    v-if="users.last_page > 1"
                    class="flex items-center justify-end gap-2 text-sm"
                >
                    <Button
                        v-for="page in users.last_page"
                        :key="page"
                        :variant="page === users.current_page ? 'default' : 'outline'"
                        size="sm"
                        as-child
                    >
                        <a :href="pageHref(page)">{{ page }}</a>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
