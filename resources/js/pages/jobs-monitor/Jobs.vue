<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowUpDown } from '@lucide/vue';
import { createColumnHelper } from '@tanstack/vue-table';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DataTable from '@/components/DataTable.vue';
import { jobs as jobsMonitorJobs, show as showJob } from '@/routes/jobs-monitor';
import type { Paginated } from '@/types';

interface JobRow {
    id: string;
    uuid: string;
    job_class: string;
    full_job_class: string;
    queue: string;
    status: string;
    duration: string;
    duration_ms: number | null;
    user: { id: string; name: string } | null;
    tags: Array<{ tag?: string }> | null;
    created_at: string;
    created_at_human: string;
}

const props = defineProps<{
    jobs: Paginated<JobRow>;
    queues: string[];
    tags: string[];
    filters: {
        status?: string | null;
        queue?: string | null;
        job_class?: string | null;
        tags?: string[];
        tag_mode?: string;
        date_from?: string | null;
        date_to?: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Job Monitoring', href: '/jobs-monitor' },
            { title: 'Jobs', href: '#' },
        ],
    },
});

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'processed') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'processing') return 'secondary';

    return 'outline';
}

const columnHelper = createColumnHelper<any, JobRow>();

const columns: ColumnDef<any, JobRow, any>[] = [
    columnHelper.accessor('status', {
        header: 'Estado',
        cell: ({ row }) =>
            h(
                Badge,
                { variant: statusVariant(row.getValue('status')), class: 'text-xs' },
                () => row.getValue('status'),
            ),
    }),
    columnHelper.accessor('job_class', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Job', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            ),
        cell: ({ row }) => {
            const job = row.original as JobRow;

            return h(
                'a',
                {
                    href: showJob.url(job.id),
                    class: 'font-medium hover:underline',
                    title: job.full_job_class,
                },
                row.getValue('job_class'),
            );
        },
    }),
    columnHelper.accessor('queue', {
        header: 'Cola',
        cell: ({ row }) =>
            h('div', { class: 'text-muted-foreground' }, row.getValue('queue')),
    }),
    columnHelper.accessor('user', {
        header: 'Usuario',
        cell: ({ row }) => {
            const user = row.getValue('user') as { id: string; name: string } | null;

            return h(
                'div',
                { class: 'text-muted-foreground' },
                user ? user.name : '—',
            );
        },
    }),
    columnHelper.accessor('duration', {
        header: 'Duración',
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                row.getValue('duration'),
            ),
    }),
    columnHelper.accessor('created_at_human', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => ['Creado', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
            ),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                row.getValue('created_at_human'),
            ),
    }),
];

function applyFilters(patch: Record<string, string | null | undefined>): void {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries({
        status: props.filters.status ?? '',
        queue: props.filters.queue ?? '',
        job_class: props.filters.job_class ?? '',
        ...patch,
    })) {
        if (value) {
            query[key] = value;
        }
    }

    router.reload({
        url: jobsMonitorJobs.url(query),
        only: ['jobs', 'filters'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Jobs" />

    <div class="flex flex-col gap-6">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Ejecuciones de jobs</h1>
            <p class="text-sm text-muted-foreground">
                Listado de trabajos ejecutados por los workers de cola.
            </p>
        </div>

        <!-- Filtros (recargan solo los props afectados) -->
        <div class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <p class="mb-1 text-sm font-medium">Estado</p>
                <Select
                    :model-value="filters.status ?? 'all'"
                    @update:model-value="
                        (value) =>
                            applyFilters({
                                status: value === 'all' ? null : String(value),
                            })
                    "
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Todos" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="processing">En curso</SelectItem>
                        <SelectItem value="processed">Procesados</SelectItem>
                        <SelectItem value="failed">Fallidos</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="w-40">
                <p class="mb-1 text-sm font-medium">Cola</p>
                <Select
                    :model-value="filters.queue ?? 'all'"
                    @update:model-value="
                        (value) =>
                            applyFilters({
                                queue: value === 'all' ? null : String(value),
                            })
                    "
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Todas" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas</SelectItem>
                        <SelectItem
                            v-for="queue in queues"
                            :key="queue"
                            :value="queue"
                        >
                            {{ queue }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="w-56">
                <p class="mb-1 text-sm font-medium">Clase del job</p>
                <Input
                    :model-value="filters.job_class ?? ''"
                    placeholder="Ej. SendInvoice"
                    @update:model-value="
                        (value) => applyFilters({ job_class: String(value) })
                    "
                />
            </div>

            <Button
                v-if="filters.status || filters.queue || filters.job_class"
                variant="ghost"
                size="sm"
                @click="applyFilters({ status: null, queue: null, job_class: null })"
            >
                Limpiar filtros
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :data="jobs.data"
            :pagination="{
                current_page: jobs.current_page,
                last_page: jobs.last_page,
                per_page: jobs.per_page,
                from: jobs.from,
                to: jobs.to,
                total: jobs.total,
            }"
            :filters="{}"
            :route-url="jobsMonitorJobs.url()"
        />
    </div>
</template>
