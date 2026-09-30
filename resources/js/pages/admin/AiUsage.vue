<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BarChart3,
    AlertTriangle,
    CheckCircle,
    Clock,
    Filter,
    Check,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import IconAction from '@/components/admin/IconAction.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { aiUsage } from '@/routes/admin';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'admin.aiUsage.title', href: aiUsage().url }],
    },
});

type Run = {
    id: string;
    agent: string;
    provider_name: string;
    model_name: string;
    input_tokens: number | null;
    output_tokens: number | null;
    duration_ms: number;
    status: string;
    error_code: string | null;
    created_at: string;
};

type Summary = {
    total_runs: number;
    success_runs: number;
    error_runs: number;
    total_input_tokens: number;
    total_output_tokens: number;
    unknown_token_runs: number;
    avg_duration_ms: number;
    max_duration_ms: number;
};

type DailyUsage = {
    date: string;
    runs: number;
    input_tokens: number;
    output_tokens: number;
    unknown_token_runs: number;
    avg_duration_ms: number;
};

const props = defineProps<{
    summary: Summary;
    dailyUsage: DailyUsage[];
    recentRuns: Run[];
    filters: Record<string, string>;
    filterOptions: {
        agents: string[];
        providers: string[];
        models: string[];
    };
}>();

const activeFilters = ref({
    agent: props.filters.agent ?? '',
    provider_name: props.filters.provider_name ?? '',
    model_name: props.filters.model_name ?? '',
    status: props.filters.status ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});
const { d, n, t } = useI18n();

function applyFilters() {
    const params: Record<string, string> = {};
    Object.entries(activeFilters.value).forEach(([key, value]) => {
        if (value) {
            params[key] = value;
        }
    });
    router.get(aiUsage(params).url, {}, { preserveState: true });
}

function clearFilters() {
    activeFilters.value = {
        agent: '',
        provider_name: '',
        model_name: '',
        status: '',
        date_from: '',
        date_to: '',
    };
    router.get(aiUsage().url);
}

function formatDuration(ms: number | null): string {
    if (!ms) {
        return '—';
    }

    if (ms < 1000) {
        return `${ms}ms`;
    }

    return `${(ms / 1000).toFixed(1)}s`;
}

function formatNumber(value: number | null): string {
    if (!value) {
        return '0';
    }

    return n(value, 'decimal');
}

function formatDate(iso: string): string {
    return d(new Date(iso), 'dateTime');
}
</script>

<template>
    <Head :title="t('admin.aiUsage.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('admin.aiUsage.title')"
            :description="t('admin.aiUsage.description')"
        />

        <!-- Resumen -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="p-4">
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <BarChart3 class="size-4" />
                        Total runs
                    </div>
                    <p class="mt-1 text-2xl font-bold">
                        {{ formatNumber(summary.total_runs) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <CheckCircle class="size-4 text-green-500" />
                        Exitosos
                    </div>
                    <p class="mt-1 text-2xl font-bold text-green-600">
                        {{ formatNumber(summary.success_runs) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <AlertTriangle class="size-4 text-red-500" />
                        Errores
                    </div>
                    <p class="mt-1 text-2xl font-bold text-red-600">
                        {{ formatNumber(summary.error_runs) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Clock class="size-4" />
                        Latencia media
                    </div>
                    <p class="mt-1 text-2xl font-bold">
                        {{ formatDuration(summary.avg_duration_ms) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Tokens -->
        <div class="grid grid-cols-2 gap-4">
            <Card>
                <CardContent class="p-4">
                    <p class="text-sm text-muted-foreground">
                        Tokens de entrada
                    </p>
                    <p class="text-xl font-bold">
                        {{ formatNumber(summary.total_input_tokens) }}
                    </p>
                    <p
                        v-if="summary.unknown_token_runs"
                        class="text-xs text-muted-foreground"
                    >
                        {{ summary.unknown_token_runs }} ejecuciones sin datos
                        de tokens
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-4">
                    <p class="text-sm text-muted-foreground">
                        Tokens de salida
                    </p>
                    <p class="text-xl font-bold">
                        {{ formatNumber(summary.total_output_tokens) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filtros -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Filter class="size-4" />
                    Filtros
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <Field label="Agente">
                        <Select v-model="activeFilters.agent">
                            <SelectTrigger
                                ><SelectValue placeholder="Todos"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Todos</SelectItem>
                                <SelectItem
                                    v-for="agent in filterOptions.agents"
                                    :key="agent"
                                    :value="agent"
                                    >{{ agent }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Proveedor">
                        <Select v-model="activeFilters.provider_name">
                            <SelectTrigger
                                ><SelectValue placeholder="Todos"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Todos</SelectItem>
                                <SelectItem
                                    v-for="p in filterOptions.providers"
                                    :key="p"
                                    :value="p"
                                    >{{ p }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Modelo">
                        <Select v-model="activeFilters.model_name">
                            <SelectTrigger
                                ><SelectValue placeholder="Todos"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Todos</SelectItem>
                                <SelectItem
                                    v-for="m in filterOptions.models"
                                    :key="m"
                                    :value="m"
                                    >{{ m }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field label="Desde">
                        <Input v-model="activeFilters.date_from" type="date" />
                    </Field>
                    <Field label="Hasta">
                        <Input v-model="activeFilters.date_to" type="date" />
                    </Field>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <IconAction
                        :label="t('admin.aiUsage.applyFilters')"
                        variant="default"
                        @click="applyFilters"
                    >
                        <Check aria-hidden="true" />
                    </IconAction>
                    <IconAction
                        :label="t('admin.aiUsage.clearFilters')"
                        @click="clearFilters"
                    >
                        <X aria-hidden="true" />
                    </IconAction>
                </div>
            </CardContent>
        </Card>

        <!-- Runs recientes -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{
                    t('admin.aiUsage.recentRuns')
                }}</CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="recentRuns.length === 0"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    {{ t('admin.aiUsage.noRuns') }}
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-muted-foreground"
                            >
                                <th class="pb-2 font-medium">Fecha</th>
                                <th class="pb-2 font-medium">Agente</th>
                                <th class="pb-2 font-medium">Proveedor</th>
                                <th class="pb-2 font-medium">Modelo</th>
                                <th class="pb-2 text-right font-medium">
                                    Tokens in
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    Tokens out
                                </th>
                                <th class="pb-2 text-right font-medium">
                                    Latencia
                                </th>
                                <th class="pb-2 font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="run in recentRuns"
                                :key="run.id"
                                class="border-b last:border-0"
                            >
                                <td class="py-2">
                                    {{ formatDate(run.created_at) }}
                                </td>
                                <td class="py-2">{{ run.agent }}</td>
                                <td class="py-2">{{ run.provider_name }}</td>
                                <td class="py-2">{{ run.model_name }}</td>
                                <td class="py-2 text-right">
                                    {{
                                        run.input_tokens === null
                                            ? '—'
                                            : formatNumber(run.input_tokens)
                                    }}
                                </td>
                                <td class="py-2 text-right">
                                    {{
                                        run.output_tokens === null
                                            ? '—'
                                            : formatNumber(run.output_tokens)
                                    }}
                                </td>
                                <td class="py-2 text-right">
                                    {{ formatDuration(run.duration_ms) }}
                                </td>
                                <td class="py-2">
                                    <span
                                        :class="
                                            run.status === 'ok'
                                                ? 'text-green-600'
                                                : 'text-red-600'
                                        "
                                    >
                                        {{ run.status === 'ok' ? '✅' : '❌' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
