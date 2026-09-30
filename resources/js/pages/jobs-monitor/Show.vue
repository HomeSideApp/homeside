<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show as showJob, retry as retryJob } from '@/routes/jobs-monitor';

interface JobDetail {
    id: string;
    uuid: string;
    job_class: string;
    short_job_class: string;
    queue: string;
    connection: string;
    status: string;
    duration: string;
    duration_ms: number | null;
    memory_usage: string;
    memory_start: number | null;
    memory_end: number | null;
    memory_peak: number | null;
    cpu_user: number | null;
    cpu_system: number | null;
    user: { id: string; name: string } | null;
    tags: Array<{ tag?: string }> | null;
    payload: Record<string, unknown> | null;
    exception: string | null;
    stack_trace: string | null;
    attempts: number;
    started_at: string | null;
    finished_at: string | null;
    created_at: string;
    original_job_run: { id: string; status: string; created_at: string } | null;
    retries: Array<{ id: string; status: string; duration_ms: number | null; created_at: string }>;
}

const props = defineProps<{ job: JobDetail }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Job Monitoring', href: '/jobs-monitor' },
            { title: 'Jobs', href: '/jobs-monitor/jobs' },
            { title: 'Detalle', href: '#' },
        ],
    },
});

const page = usePage();

const canRetry = computed(
    () =>
        props.job.status === 'failed' &&
        (page.props.auth?.permissions ?? []).includes('jobs-monitor.retry'),
);

const form = useForm({});

function retry(): void {
    form.post(retryJob.url(props.job.id), {
        preserveScroll: true,
        onFinish: () => {
            // Recarga el detalle con el nuevo intento vinculado.
            router.reload({ only: ['job'] });
        },
    });
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'processed') return 'default';
    if (status === 'failed') return 'destructive';
    if (status === 'processing') return 'secondary';

    return 'outline';
}
</script>

<template>
    <Head :title="`Job ${job.short_job_class}`" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ job.short_job_class }}
                    </h1>
                    <Badge :variant="statusVariant(job.status)">{{ job.status }}</Badge>
                </div>
                <p class="text-sm text-muted-foreground">{{ job.job_class }}</p>
            </div>

            <Button
                v-if="canRetry"
                :disabled="form.processing"
                @click="retry"
            >
                {{ form.processing ? 'Reintentando…' : 'Reintentar job' }}
            </Button>
        </div>

        <!-- Resumen -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Cola</CardTitle>
                </CardHeader>
                <CardContent class="text-lg font-semibold">
                    {{ job.queue }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Duración</CardTitle>
                </CardHeader>
                <CardContent class="text-lg font-semibold">
                    {{ job.duration }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Intentos</CardTitle>
                </CardHeader>
                <CardContent class="text-lg font-semibold">
                    {{ job.attempts }}
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Memoria</CardTitle>
                </CardHeader>
                <CardContent class="text-lg font-semibold">
                    {{ job.memory_usage }}
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Metadatos -->
            <Card>
                <CardHeader>
                    <CardTitle>Metadatos</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">UUID</dt>
                            <dd class="font-mono">{{ job.uuid }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Conexión</dt>
                            <dd>{{ job.connection }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Usuario</dt>
                            <dd>{{ job.user ? job.user.name : '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Inicio</dt>
                            <dd>{{ job.started_at ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Fin</dt>
                            <dd>{{ job.finished_at ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">CPU (usuario / sistema)</dt>
                            <dd>
                                {{ job.cpu_user ?? '—' }} / {{ job.cpu_system ?? '—' }}
                            </dd>
                        </div>
                        <div v-if="job.tags && job.tags.length" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Tags</dt>
                            <dd class="flex flex-wrap justify-end gap-1">
                                <Badge
                                    v-for="(tag, index) in job.tags"
                                    :key="index"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    {{ typeof tag === 'string' ? tag : tag.tag }}
                                </Badge>
                            </dd>
                        </div>
                        <div v-if="job.original_job_run" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Job original</dt>
                            <dd>
                                <a
                                    class="hover:underline"
                                    :href="showJob.url(job.original_job_run.id)"
                                >
                                    {{ job.original_job_run.status }}
                                </a>
                            </dd>
                        </div>
                        <div v-if="job.retries.length" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Reintentos</dt>
                            <dd class="flex flex-wrap justify-end gap-1">
                                <a
                                    v-for="retry in job.retries"
                                    :key="retry.id"
                                    class="hover:underline"
                                    :href="showJob.url(retry.id)"
                                >
                                    <Badge variant="outline" class="text-xs">
                                        {{ retry.status }}
                                    </Badge>
                                </a>
                            </dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>

            <!-- Error -->
            <Card v-if="job.exception">
                <CardHeader>
                    <CardTitle class="text-destructive">Error</CardTitle>
                    <CardDescription>{{ job.exception }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <pre
                        class="max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs"
                    >{{ job.stack_trace }}</pre>
                </CardContent>
            </Card>

            <!-- Payload -->
            <Card v-else-if="job.payload">
                <CardHeader>
                    <CardTitle>Payload</CardTitle>
                    <CardDescription>
                        Claves sensibles aparecen redactadas.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <pre class="max-h-80 overflow-auto rounded-md bg-muted p-3 text-xs">{{
                        JSON.stringify(job.payload, null, 2)
                    }}</pre>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
