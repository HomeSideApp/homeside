<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import {
    Loader2,
    Pencil,
    Plus,
    Star,
    TestTube2,
    Trash2,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AiProviderFields from '@/components/households/AiProviderFields.vue';
import CatalogModelPicker from '@/components/households/CatalogModelPicker.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    AiProvider,
    CatalogPrefillData,
    PaginatedData,
    CatalogProvider,
    CatalogModel,
} from '@/types';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        /** URL of the provider collection used as the resource route base. */
        baseUrl: string;
        testConfigUrl: string;
        testProviderUrl: (providerId: string) => string;
        providers: AiProvider[];
        /** Optional section title. */
        title?: string;
        /** Optional empty-state text. */
        emptyText?: string;
        /** Whether to show the global badge on each provider. */
        showGlobalBadge?: boolean;
        /** Paginated catalog providers from Inertia partial reload. */
        catalogProviders?: PaginatedData<CatalogProvider> | null;
        /** Paginated catalog models from Inertia partial reload. */
        catalogModels?: PaginatedData<CatalogModel> | null;
        /** Prefill data from Inertia partial reload. */
        catalogPrefill?: CatalogPrefillData | null;
        /** URL to hit for catalog provider search via Inertia partial reload. */
        catalogSearchUrl?: string;
    }>(),
    {
        showGlobalBadge: false,
        catalogProviders: null,
        catalogModels: null,
        catalogPrefill: null,
        catalogSearchUrl: '',
    },
);

const emit = defineEmits<{
    refresh: [];
}>();

const testResults = ref<Record<string, any>>({});
const testingId = ref<string | null>(null);
const showNewProviderForm = ref(false);
const editingId = ref<string | null>(null);
const testingNewProvider = ref(false);
const testingEditProvider = ref(false);
const newProviderTestResult = ref<any>(null);
const editProviderTestResult = ref<any>(null);
const newProviderTest = useHttp({
    type: '',
    base_url: '',
    model: '',
    api_key: '',
    module: '',
});
const editedProviderTest = useHttp({
    type: '',
    base_url: '',
    model: '',
    api_key: '',
    module: '',
});
const savedProviderTest = useHttp<Record<string, never>, any>({});

function defaultCapabilities() {
    return {
        reasoning: false,
        reasoning_effort: '',
        tool_call_format: 'native',
        context_tokens: null as number | null,
    };
}

const newProvider = useForm({
    name: '',
    driver: 'openai-compatible',
    base_url: '',
    model: '',
    api_key: '',
    modules: ['general'] as string[],
    enabled: true,
    configuration: {
        temperature: null as number | null,
        model_capabilities: defaultCapabilities(),
    },
    privacy_level: 'unknown',
    fallback_policy: 'same_privacy_level',
    // Catalog-aligned fields.
    family: null as string | null,
    description: null as string | null,
    attachment: false,
    reasoning: false,
    reasoning_options: null as Record<string, any> | null,
    tool_call: false,
    structured_output: false,
    temperature: false,
    open_weights: false,
    modalities_input: null as string[] | null,
    modalities_output: null as string[] | null,
    context_window: null as number | null,
    max_input_tokens: null as number | null,
    max_output_tokens: null as number | null,
    cost_input: null as number | null,
    cost_output: null as number | null,
    cost_cache_read: null as number | null,
    cost_cache_write: null as number | null,
});

const showCatalogPicker = ref(false);

function applyCatalogPrefill(prefill: CatalogPrefillData) {
    if (prefill.name) {
newProvider.name = prefill.name;
}

    if (prefill.type) {
newProvider.driver = prefill.type;
}

    if (prefill.model) {
newProvider.model = prefill.model;
}

    if (prefill.base_url) {
newProvider.base_url = prefill.base_url;
}

    if (prefill.privacy_level) {
newProvider.privacy_level = prefill.privacy_level;
}

    newProvider.family = prefill.family;
    newProvider.description = prefill.description;
    newProvider.attachment = prefill.attachment;
    newProvider.reasoning = prefill.reasoning;
    newCapabilities.value.reasoning = prefill.reasoning;
    newProvider.reasoning_options = prefill.reasoning_options;
    newProvider.tool_call = prefill.tool_call;
    newProvider.structured_output = prefill.structured_output;
    newProvider.temperature = prefill.temperature ?? false;
    newProvider.open_weights = prefill.open_weights;
    newProvider.modalities_input = prefill.modalities_input;
    newProvider.modalities_output = prefill.modalities_output;
    newProvider.context_window = prefill.context_window;
    newCapabilities.value.context_tokens = prefill.context_window;
    newProvider.max_input_tokens = prefill.max_input_tokens;
    newProvider.max_output_tokens = prefill.max_output_tokens;
    newProvider.cost_input = prefill.cost_input;
    newProvider.cost_output = prefill.cost_output;
    newProvider.cost_cache_read = prefill.cost_cache_read;
    newProvider.cost_cache_write = prefill.cost_cache_write;
    showCatalogPicker.value = false;

    // Reset browser URL to base without triggering Inertia navigation.
    // The catalog search left stale query params (model_id, provider_slug, q)
    // that would break the picker if reopened. history.replaceState preserves
    // all Vue / Inertia component state while cleaning the URL.
    const baseUrl = props.baseUrl || window.location.pathname;
    history.replaceState(history.state, '', baseUrl);
}

const newCapabilities = computed(
    () =>
        (newProvider.configuration.model_capabilities ??=
            defaultCapabilities()),
);

function submitCreate() {
    newProvider.post(props.baseUrl, {
        onSuccess: () => {
            showNewProviderForm.value = false;
            newProvider.reset();
            emit('refresh');
        },
    });
}

async function testNewProvider() {
    testingNewProvider.value = true;
    newProviderTestResult.value = null;

    try {
        newProviderTest.type = newProvider.driver;
        newProviderTest.base_url = newProvider.base_url;
        newProviderTest.model = newProvider.model;
        newProviderTest.api_key = newProvider.api_key;
        newProviderTest.module = newProvider.modules[0] ?? 'general';

        await newProviderTest.post(props.testConfigUrl, {
            onSuccess: (data) => {
                newProviderTestResult.value = data;
            },
        });
    } catch {
        newProviderTestResult.value = {
            status: 'error',
            message: t('households.aiProviders.connectionError'),
        };
    } finally {
        testingNewProvider.value = false;
    }
}

const editProvider = useForm({
    name: '',
    driver: 'openai-compatible',
    base_url: '',
    model: '',
    api_key: '',
    modules: ['general'] as string[],
    enabled: true,
    configuration: {
        temperature: null as number | null,
        model_capabilities: defaultCapabilities(),
    },
    privacy_level: 'unknown',
    fallback_policy: 'same_privacy_level',
    family: null as string | null,
    description: null as string | null,
    attachment: false,
    reasoning: false,
    reasoning_options: null as Record<string, any> | null,
    tool_call: false,
    structured_output: false,
    temperature: false,
    open_weights: false,
    modalities_input: null as string[] | null,
    modalities_output: null as string[] | null,
    context_window: null as number | null,
    max_input_tokens: null as number | null,
    max_output_tokens: null as number | null,
    cost_input: null as number | null,
    cost_output: null as number | null,
    cost_cache_read: null as number | null,
    cost_cache_write: null as number | null,
});

const editCapabilities = computed(
    () =>
        (editProvider.configuration.model_capabilities ??=
            defaultCapabilities()),
);

function startEdit(provider: AiProvider) {
    editingId.value = provider.id;
    editProvider.name = provider.name;
    editProvider.driver = provider.driver ?? provider.type;
    editProvider.base_url = provider.base_url;
    editProvider.model = provider.model;
    editProvider.api_key = '';
    editProvider.modules = [...(provider.modules ?? [provider.module])];
    editProvider.enabled = provider.enabled;
    editProvider.configuration = {
        ...provider.configuration,
        temperature: provider.configuration?.temperature ?? null,
        model_capabilities: {
            ...defaultCapabilities(),
            ...(provider.configuration?.model_capabilities ?? {}),
        },
    };
    editProvider.privacy_level = provider.privacy_level ?? 'unknown';
    editProvider.fallback_policy =
        provider.fallback_policy ?? 'same_privacy_level';
    editProvider.family = provider.family;
    editProvider.description = provider.description;
    editProvider.attachment = provider.attachment;
    editProvider.reasoning = provider.reasoning;
    editCapabilities.value.reasoning = provider.reasoning;
    editProvider.reasoning_options = provider.reasoning_options;
    editProvider.tool_call = provider.tool_call;
    editProvider.structured_output = provider.structured_output;
    editProvider.temperature = provider.temperature ?? false;
    editProvider.open_weights = provider.open_weights;
    editProvider.modalities_input = provider.modalities_input;
    editProvider.modalities_output = provider.modalities_output;
    editProvider.context_window =
        provider.context_window ??
        provider.configuration?.model_capabilities?.context_tokens ??
        null;
    editCapabilities.value.context_tokens ??= provider.context_window;
    editProvider.max_input_tokens = provider.max_input_tokens;
    editProvider.max_output_tokens = provider.max_output_tokens;
    editProvider.cost_input = provider.cost_input;
    editProvider.cost_output = provider.cost_output;
    editProvider.cost_cache_read = provider.cost_cache_read;
    editProvider.cost_cache_write = provider.cost_cache_write;
    editProviderTestResult.value = null;
}

function cancelEdit() {
    editingId.value = null;
    editProvider.reset();
    editProviderTestResult.value = null;
}

function submitUpdate(providerId: string) {
    editProvider.put(`${props.baseUrl}/${providerId}`, {
        onSuccess: () => {
            editingId.value = null;
            editProvider.reset();
            emit('refresh');
        },
    });
}

async function testEditProvider() {
    testingEditProvider.value = true;
    editProviderTestResult.value = null;

    try {
        editedProviderTest.type = editProvider.driver;
        editedProviderTest.base_url = editProvider.base_url;
        editedProviderTest.model = editProvider.model;
        editedProviderTest.api_key = editProvider.api_key;
        editedProviderTest.module = editProvider.modules[0] ?? 'general';

        await editedProviderTest.post(props.testConfigUrl, {
            onSuccess: (data) => {
                editProviderTestResult.value = data;
            },
        });
    } catch {
        editProviderTestResult.value = {
            status: 'error',
            message: t('households.aiProviders.connectionError'),
        };
    } finally {
        testingEditProvider.value = false;
    }
}

function deleteProvider(providerId: string) {
    useForm({}).delete(`${props.baseUrl}/${providerId}`, {
        onSuccess: () => emit('refresh'),
    });
}

function markDefault(providerId: string, module: string) {
    useForm({ module }).post(`${props.baseUrl}/${providerId}/default`, {
        onSuccess: () => emit('refresh'),
    });
}

async function testProvider(providerId: string) {
    testingId.value = providerId;

    try {
        await savedProviderTest.post(props.testProviderUrl(providerId), {
            onSuccess: (data) => {
                testResults.value[providerId] = data;
            },
        });
    } catch {
        testResults.value[providerId] = {
            status: 'error',
            message: t('households.aiProviders.connectionError'),
        };
    } finally {
        testingId.value = null;
    }
}
</script>

<template>
    <TooltipProvider>
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="text-base">{{
                        title ?? t('households.aiProviders.title')
                    }}</CardTitle>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                :aria-label="t('households.aiProviders.add')"
                                :aria-expanded="showNewProviderForm"
                                @click="
                                    showNewProviderForm = !showNewProviderForm
                                "
                            >
                                <Plus aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('households.aiProviders.add')
                        }}</TooltipContent>
                    </Tooltip>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <Dialog v-model:open="showCatalogPicker">
                    <DialogContent
                        class="flex max-h-[85vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl"
                    >
                        <DialogHeader class="shrink-0 border-b px-6 py-5 pr-14">
                            <DialogTitle class="text-base">{{
                                t('catalog.title')
                            }}</DialogTitle>
                        </DialogHeader>
                        <div class="min-h-0 overflow-y-auto px-6 py-5">
                            <CatalogModelPicker
                                v-if="catalogSearchUrl"
                                :catalog-providers="catalogProviders"
                                :catalog-models="catalogModels"
                                :catalog-prefill="catalogPrefill"
                                :catalog-search-url="catalogSearchUrl"
                                @select="applyCatalogPrefill"
                            />
                        </div>
                    </DialogContent>
                </Dialog>

                <!-- New provider form. -->
                <div
                    v-if="showNewProviderForm"
                    class="space-y-3 rounded-lg border border-dashed p-4"
                >
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-medium">
                            {{ t('households.aiProviders.newProvider') }}
                        </h4>
                        <Tooltip v-if="catalogSearchUrl">
                            <TooltipTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    :aria-label="t('catalog.browse')"
                                    @click="
                                        showCatalogPicker = !showCatalogPicker
                                    "
                                >
                                    <Sparkles aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                t('catalog.browse')
                            }}</TooltipContent>
                        </Tooltip>
                    </div>
                    <AiProviderFields :provider="newProvider" />
                    <div
                        v-if="newProvider.hasErrors"
                        class="space-y-1 text-sm text-destructive"
                        role="alert"
                    >
                        <p
                            v-for="(message, field) in newProvider.errors"
                            :key="field"
                        >
                            {{ message }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch
                            :checked="newProvider.enabled"
                            @update:checked="
                                (v: boolean) => (newProvider.enabled = v)
                            "
                        />
                        <Label>{{ t('households.aiProviders.active') }}</Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="testNewProvider"
                            :disabled="
                                testingNewProvider ||
                                !newProvider.base_url ||
                                !newProvider.model ||
                                !newProvider.api_key
                            "
                        >
                            <Loader2
                                v-if="testingNewProvider"
                                class="mr-2 size-4 animate-spin"
                            />
                            <TestTube2 v-else class="mr-2 size-4" />
                            {{ t('households.aiProviders.testConnection') }}
                        </Button>
                        <Button
                            size="sm"
                            @click="submitCreate"
                            :disabled="newProvider.processing"
                        >
                            <Loader2
                                v-if="newProvider.processing"
                                class="mr-2 size-4 animate-spin"
                            />
                            {{ t('households.aiProviders.save') }}
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="showNewProviderForm = false"
                            >{{ t('households.aiProviders.cancel') }}</Button
                        >
                    </div>
                    <div
                        v-if="newProviderTestResult"
                        class="rounded-lg border p-3 text-sm"
                        :class="
                            newProviderTestResult.status === 'ok'
                                ? 'border-green-200 bg-green-50'
                                : 'border-red-200 bg-red-50'
                        "
                    >
                        <template v-if="newProviderTestResult.status === 'ok'">
                            ✅ {{ t('households.aiProviders.connected') }} ({{
                                newProviderTestResult.latency_ms
                            }}ms)
                            <p
                                class="mt-1 text-xs text-muted-foreground italic"
                            >
                                "{{ newProviderTestResult.reply }}"
                            </p>
                        </template>
                        <template v-else>
                            ❌ {{ newProviderTestResult.message }}
                        </template>
                    </div>
                </div>

                <!-- Provider list. -->
                <div
                    v-for="provider in providers"
                    :key="provider.id"
                    class="space-y-3 rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium">{{
                                        provider.name
                                    }}</span>
                                    <span
                                        class="rounded-full bg-secondary px-2 py-0.5 text-xs"
                                        >{{
                                            provider.driver ?? provider.type
                                        }}</span
                                    >
                                    <span
                                        v-for="module in provider.modules ?? [
                                            provider.module,
                                        ]"
                                        :key="module"
                                        class="rounded-full bg-secondary px-2 py-0.5 text-xs"
                                    >
                                        {{
                                            t(
                                                `households.aiProviders.modules.${module}`,
                                            )
                                        }}
                                        <button
                                            type="button"
                                            class="ml-1"
                                            :aria-label="`${t('households.aiProviders.markDefault')}: ${provider.name}, ${t(`households.aiProviders.modules.${module}`)}`"
                                            :aria-pressed="
                                                (
                                                    provider.default_modules ??
                                                    []
                                                ).includes(module)
                                            "
                                            :title="
                                                t(
                                                    'households.aiProviders.markDefault',
                                                )
                                            "
                                            @click="
                                                markDefault(provider.id, module)
                                            "
                                        >
                                            <Star
                                                class="inline size-3"
                                                aria-hidden="true"
                                                :class="
                                                    (
                                                        provider.default_modules ??
                                                        []
                                                    ).includes(module)
                                                        ? 'fill-primary text-primary'
                                                        : ''
                                                "
                                            />
                                        </button>
                                    </span>
                                    <span
                                        v-if="showGlobalBadge"
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700"
                                        >{{
                                            t('households.aiProviders.global')
                                        }}</span
                                    >
                                    <span
                                        v-if="provider.is_default"
                                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                        >{{
                                            t('households.aiProviders.default')
                                        }}</span
                                    >
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ provider.base_url }} ·
                                    {{ provider.model }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="`${t('households.aiProviders.editTitle')}: ${provider.name}`"
                                        @click="startEdit(provider)"
                                    >
                                        <Pencil aria-hidden="true" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{{
                                    t('households.aiProviders.editTitle')
                                }}</TooltipContent>
                            </Tooltip>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        :aria-label="`${t('households.aiProviders.testConnection')}: ${provider.name}`"
                                        :aria-disabled="
                                            testingId === provider.id
                                        "
                                        class="aria-disabled:cursor-not-allowed aria-disabled:opacity-50"
                                        @click="
                                            testingId !== provider.id &&
                                            testProvider(provider.id)
                                        "
                                    >
                                        <Loader2
                                            v-if="testingId === provider.id"
                                            class="animate-spin"
                                            aria-hidden="true"
                                        />
                                        <TestTube2 v-else aria-hidden="true" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{{
                                    t('households.aiProviders.testConnection')
                                }}</TooltipContent>
                            </Tooltip>
                            <AlertDialog>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <AlertDialogTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="`${t('households.aiProviders.delete')}: ${provider.name}`"
                                            >
                                                <Trash2
                                                    class="text-destructive"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        </AlertDialogTrigger>
                                    </TooltipTrigger>
                                    <TooltipContent>{{
                                        t('households.aiProviders.delete')
                                    }}</TooltipContent>
                                </Tooltip>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>{{
                                            t(
                                                'households.aiProviders.deleteTitle',
                                            )
                                        }}</AlertDialogTitle>
                                        <AlertDialogDescription>{{
                                            t(
                                                'households.aiProviders.deleteDescription',
                                            )
                                        }}</AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>{{
                                            t('households.aiProviders.cancel')
                                        }}</AlertDialogCancel>
                                        <AlertDialogAction
                                            @click="deleteProvider(provider.id)"
                                            >{{
                                                t(
                                                    'households.aiProviders.delete',
                                                )
                                            }}</AlertDialogAction
                                        >
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </div>

                    <!-- Inline edit form. -->
                    <div
                        v-if="editingId === provider.id"
                        class="space-y-3 rounded-lg border border-dashed p-4"
                    >
                        <h4 class="text-sm font-medium">
                            {{ t('households.aiProviders.editProvider') }}
                        </h4>
                        <AiProviderFields :provider="editProvider" editing />
                        <div
                            v-if="editProvider.hasErrors"
                            class="space-y-1 text-sm text-destructive"
                            role="alert"
                        >
                            <p
                                v-for="(message, field) in editProvider.errors"
                                :key="field"
                            >
                                {{ message }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Switch
                                :checked="editProvider.enabled"
                                @update:checked="
                                    (v: boolean) => (editProvider.enabled = v)
                                "
                            />
                            <Label>{{
                                t('households.aiProviders.active')
                            }}</Label>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="testEditProvider"
                                :disabled="
                                    testingEditProvider ||
                                    !editProvider.base_url ||
                                    !editProvider.model
                                "
                            >
                                <Loader2
                                    v-if="testingEditProvider"
                                    class="mr-2 size-4 animate-spin"
                                />
                                <TestTube2 v-else class="mr-2 size-4" />
                                {{ t('households.aiProviders.testConnection') }}
                            </Button>
                            <Button
                                size="sm"
                                @click="submitUpdate(provider.id)"
                                :disabled="editProvider.processing"
                            >
                                <Loader2
                                    v-if="editProvider.processing"
                                    class="mr-2 size-4 animate-spin"
                                />
                                {{ t('households.aiProviders.save') }}
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="cancelEdit"
                                >{{
                                    t('households.aiProviders.cancel')
                                }}</Button
                            >
                        </div>
                        <div
                            v-if="editProviderTestResult"
                            class="rounded-lg border p-3 text-sm"
                            :class="
                                editProviderTestResult.status === 'ok'
                                    ? 'border-green-200 bg-green-50'
                                    : 'border-red-200 bg-red-50'
                            "
                        >
                            <template
                                v-if="editProviderTestResult.status === 'ok'"
                            >
                                ✅
                                {{ t('households.aiProviders.connected') }} ({{
                                    editProviderTestResult.latency_ms
                                }}ms)
                                <p
                                    class="mt-1 text-xs text-muted-foreground italic"
                                >
                                    "{{ editProviderTestResult.reply }}"
                                </p>
                            </template>
                            <template v-else>
                                ❌ {{ editProviderTestResult.message }}
                            </template>
                        </div>
                    </div>

                    <!-- Connection test result. -->
                    <div
                        v-if="testResults[provider.id]"
                        class="rounded-lg border p-3 text-sm"
                        :class="
                            testResults[provider.id].status === 'ok'
                                ? 'border-green-200 bg-green-50'
                                : 'border-red-200 bg-red-50'
                        "
                    >
                        <template
                            v-if="testResults[provider.id].status === 'ok'"
                        >
                            ✅ {{ t('households.aiProviders.connected') }} ({{
                                testResults[provider.id].latency_ms
                            }}ms)
                            <p
                                class="mt-1 text-xs text-muted-foreground italic"
                            >
                                "{{ testResults[provider.id].reply }}"
                            </p>
                        </template>
                        <template v-else>
                            ❌ {{ testResults[provider.id].message }}
                        </template>
                    </div>
                </div>

                <div
                    v-if="providers.length === 0 && !showNewProviderForm"
                    class="rounded-lg border border-dashed p-6 text-center"
                >
                    <p class="text-sm text-muted-foreground">
                        {{ emptyText ?? t('households.aiProviders.empty') }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </TooltipProvider>
</template>
