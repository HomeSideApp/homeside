<script setup lang="ts">
import { Head, router, usePoll } from '@inertiajs/vue3';
import { ArrowRight, Activity, CheckCircle2, XCircle, Loader2, Server } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as jobsMonitorIndex, show as showJob } from '@/routes/jobs-monitor';
import type { Paginated } from '@/types';

interface Statistics {
    total: number;
    processed: number;
    failed: number;
    processing: number;
    success_rate: number;
}

interface QueueDepth {
    queue: string;
    count: number;
    status: string;
}

interface JobRow {
    id: string;
    uuid: string;
    job_class: string;
    queue: string;
    status: string;
    duration: string;
    user: { id: string; name: string } | null;
    tags: Array<{ tag?: string }> | null;
    created_at: string;
    created_at_human: string;
}

interface TopFailingJob {
    job_class: string;
    full_class: string;
    failures: number;
}

defineProps<{
    statistics: Statistics;
    queueDepths: QueueDepth[];
    recentJobs: Paginated<JobRow>;
    topFailingJobs: TopFailingJob[];
    filters: { period: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Job Monitoring', href: '/jobs-monitor' }],
    },
});

const periods = ['1h', '6h', '24h', '7d', '30d'];

// Refresca las métricas sin salir de la página (solo props seleccionados).
usePoll(5000, { only: ['statistics', 'queueDepths', 'recentJobs', 'topFailingJobs'] });

function changePeriod(period: string): void {
    router.reload({
        url: jobsMonitorIndex.url({ period }),
        only: ['statistics', 'queueDepths', 'recentJobs', 'topFailingJobs', 'filters'],
        preserveState: true,
        preserveScroll: true,
    });
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'processed') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'processing') return 'secondary';

    return 'outline';
}

function depthVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'critical' || status === 'warning') return 'destructive';
    if (status === 'normal') return 'secondary';

    return 'outline';
}
</script>

<template>
    <Head title="Job Monitoring" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Job Monitoring</h1>
                <p class="text-sm text-muted-foreground">
                    Estado de los workers de cola y ejecuciones de jobs.
                </p>
            </div>

            <div class="flex items-center gap-1 rounded-lg border p-1">
                <Button
                    v-for="period in periods"
                    :key="period"
                    size="sm"
                    :variant="filters.period === period ? 'default' : 'ghost'"
                    @click="changePeriod(period)"
                >
                    {{ period }}
                </Button>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total</CardTitle>
                    <Activity class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ statistics.total }}</div>
                    <p class="text-xs text-muted-foreground">
                        {{ statistics.success_rate }}% de éxito
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Procesados</CardTitle>
                    <CheckCircle2 class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ statistics.processed }}</div>
                    <p class="text-xs text-muted-foreground">Jobs completados</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Fallidos</CardTitle>
                    <XCircle class="h-4 w-4 text-destructive" aria-hidden="true" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ statistics.failed }}</div>
                    <p class="text-xs text-muted-foreground">Requieren revisión</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">En curso</CardTitle>
                    <Loader2 class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ statistics.processing }}</div>
                    <p class="text-xs text-muted-foreground">Siendo ejecutados</p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Profundidad de colas -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Server class="h-4 w-4" aria-hidden="true" />
                        Colas
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="queueDepths.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No hay jobs pendientes en ninguna cola.
                    </p>
                    <ul
                        v-else
                        class="flex flex-col gap-2"
                    >
                        <li
                            v-for="depth in queueDepths"
                            :key="depth.queue"
                            class="flex items-center justify-between rounded-md border px-3 py-2"
                        >
                            <span class="text-sm font-medium">{{ depth.queue }}</span>
                            <span class="flex items-center gap-2">
                                <span class="text-sm text-muted-foreground">
                                    {{ depth.count }} pendientes
                                </span>
                                <Badge
                                    :variant="depthVariant(depth.status)"
                                    class="text-xs"
                                >
                                    {{ depth.status }}
                                </Badge>
                            </span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Jobs con más fallos -->
            <Card>
                <CardHeader>
                    <CardTitle>Jobs con más fallos</CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="topFailingJobs.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Sin fallos en el periodo seleccionado.
                    </p>
                    <ul
                        v-else
                        class="flex flex-col gap-2"
                    >
                        <li
                            v-for="job in topFailingJobs"
                            :key="job.full_class"
                            class="flex items-center justify-between rounded-md border px-3 py-2"
                        >
                            <span class="truncate text-sm font-medium">
                                {{ job.job_class }}
                            </span>
                            <Badge variant="destructive" class="text-xs">
                                {{ job.failures }}
                            </Badge>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <!-- Trabajos recientes -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>Trabajos recientes</CardTitle>
                <Button variant="outline" size="sm" as-child>
                    <a :href="jobsMonitorIndex.url()">
                        Ver todos
                        <ArrowRight class="ml-2 h-4 w-4" aria-hidden="true" />
                    </a>
                </Button>
            </CardHeader>
            <CardContent>
                <p
                    v-if="recentJobs.data.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No se ha ejecutado ningún job en el periodo seleccionado.
                </p>
                <ul
                    v-else
                    class="flex flex-col gap-2"
                >
                    <li
                        v-for="job in recentJobs.data"
                        :key="job.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2"
                    >
                        <div class="flex items-center gap-2">
                            <Badge :variant="statusVariant(job.status)" class="text-xs">
                                {{ job.status }}
                            </Badge>
                            <a
                                class="text-sm font-medium hover:underline"
                                :href="showJob.url(job.id)"
                            >
                                {{ job.job_class }}
                            </a>
                            <span class="text-xs text-muted-foreground">
                                {{ job.queue }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-muted-foreground">
                            <span>{{ job.duration }}</span>
                            <span>{{ job.created_at_human }}</span>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
