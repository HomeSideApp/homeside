<script setup lang="ts">
import { Head, useForm, router, setLayoutProps } from '@inertiajs/vue3';
import { Save } from '@lucide/vue';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    test as testHouseholdAiProvider,
    testConfig as testHouseholdAiProviderConfig,
} from '@/actions/App/Http/Controllers/Web/AiProviderController';
import Heading from '@/components/Heading.vue';
import AiProvidersForm from '@/components/households/AiProvidersForm.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    index as householdAiProvidersIndex,
    moduleConfig as householdAiProviderModuleConfig,
} from '@/routes/households/ai-providers';
import type { AiProvider, HouseholdSummary, PaginatedData, CatalogProvider, CatalogModel, CatalogPrefillData } from '@/types';

type ModuleConfig = {
    id?: string;
    household_id: string;
    ai_provider_id: string | null;
    module: string;
    agent_name: string;
    label: string;
    additional_instructions: string | null;
    description: string | null;
    model: string | null;
    parameters: { temperature?: number; max_tokens?: number } | null;
    enabled: boolean;
};

type AgentDef = {
    name: string;
    label: string;
    description: string | null;
};

type ModuleDef = {
    value: string;
    label: string;
    agents: AgentDef[];
};

const { t } = useI18n();

const props = defineProps<{
    household: HouseholdSummary;
    providers: AiProvider[];
    module_configs: ModuleConfig[];
    available_modules: ModuleDef[];
    catalogProviders?: PaginatedData<CatalogProvider> | null;
    catalogModels?: PaginatedData<CatalogModel> | null;
    catalogPrefill?: CatalogPrefillData | null;
}>();

const catalogSearchUrl = `/households/${props.household.id}/settings/ai-providers/catalog/search`;

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'nav.households', href: '/households' },
            {
                title: props.household.name,
                href: `/households/${props.household.id}`,
            },
            { title: 'households.aiProviders.title', href: '#' },
        ],
    });
});

function refreshProviders() {
    router.reload({ only: ['providers'] });
}

function providerTestUrl(providerId: string): string {
    return testHouseholdAiProvider.url({
        household: props.household.id,
        provider: providerId,
    });
}

// Reka UI prohíbe <SelectItem value="">; se usa un centinela no vacío para "sin proveedor".
const NONE_PROVIDER = '__none__';

function getModuleConfig(
    module: string,
    agentName: string,
): ModuleConfig | undefined {
    return props.module_configs.find(
        (mc) => mc.module === module && mc.agent_name === agentName,
    );
}

function submitUpdateModuleConfig(
    module: string,
    agentName: string,
    config: ModuleConfig,
) {
    useForm({
        module,
        agent_name: agentName,
        label: config.label,
        additional_instructions: config.additional_instructions,
        description: config.description,
        ai_provider_id: config.ai_provider_id,
        model: config.model,
        parameters: config.parameters,
        enabled: config.enabled,
    }).put(householdAiProviderModuleConfig.url(props.household.id));
}
</script>

<template>
    <Head :title="t('households.aiConfig.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('households.aiConfig.title')"
            :description="
                t('households.aiConfig.description', { name: household.name })
            "
        />

        <!-- ===== Sección Proveedores ===== -->
        <AiProvidersForm
            :base-url="householdAiProvidersIndex.url(household.id)"
            :test-config-url="testHouseholdAiProviderConfig.url(household.id)"
            :test-provider-url="providerTestUrl"
            :providers="providers"
            :catalog-providers="catalogProviders"
            :catalog-models="catalogModels"
            :catalog-prefill="catalogPrefill"
            :catalog-search-url="catalogSearchUrl"
            @refresh="refreshProviders"
        />

        <!-- ===== Sección Configuración por Módulo ===== -->
        <Card v-for="mod in available_modules" :key="mod.value">
            <CardHeader>
                <CardTitle class="text-base">{{ mod.label }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <div
                    v-for="agent in mod.agents"
                    :key="agent.name"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="text-sm font-medium">
                                {{ agent.label }}
                            </h4>
                            <p
                                v-if="agent.description"
                                class="mt-0.5 text-xs text-muted-foreground"
                            >
                                {{ agent.description }}
                            </p>
                        </div>
                    </div>

                    <template v-if="getModuleConfig(mod.value, agent.name)">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <Field :label="t('households.aiConfig.provider')">
                                <Select
                                    :model-value="
                                        getModuleConfig(mod.value, agent.name)!
                                            .ai_provider_id ?? NONE_PROVIDER
                                    "
                                    @update:model-value="
                                        (v: any) => {
                                            const cfg = getModuleConfig(
                                                mod.value,
                                                agent.name,
                                            )!;
                                            cfg.ai_provider_id =
                                                v === NONE_PROVIDER ? null : v;
                                        }
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            :placeholder="
                                                t(
                                                    'households.aiConfig.defaultProvider',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="NONE_PROVIDER">{{
                                            t(
                                                'households.aiConfig.defaultProvider',
                                            )
                                        }}</SelectItem>
                                        <SelectItem
                                            v-for="p in providers"
                                            :key="p.id"
                                            :value="p.id"
                                        >
                                            {{ p.name }} ({{ p.model }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field
                                :label="t('households.aiConfig.modelOverride')"
                            >
                                <Input
                                    :model-value="
                                        getModuleConfig(mod.value, agent.name)!
                                            .model ?? ''
                                    "
                                    @update:model-value="
                                        (value: string | number) => {
                                            getModuleConfig(
                                                mod.value,
                                                agent.name,
                                            )!.model = value
                                                ? String(value)
                                                : null;
                                        }
                                    "
                                    :placeholder="
                                        t(
                                            'households.aiConfig.modelOverridePlaceholder',
                                        )
                                    "
                                />
                            </Field>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <Field
                                :label="t('households.aiConfig.temperature')"
                            >
                                <Input
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="2"
                                    :model-value="
                                        getModuleConfig(mod.value, agent.name)!
                                            .parameters?.temperature
                                    "
                                    @update:model-value="
                                        (v: string | number) => {
                                            const cfg = getModuleConfig(
                                                mod.value,
                                                agent.name,
                                            )!;
                                            if (!cfg.parameters)
                                                cfg.parameters = {};
                                            cfg.parameters.temperature =
                                                parseFloat(String(v)) || 0;
                                        }
                                    "
                                />
                            </Field>
                            <Field :label="t('households.aiConfig.maxTokens')">
                                <Input
                                    type="number"
                                    min="1"
                                    max="128000"
                                    :model-value="
                                        getModuleConfig(mod.value, agent.name)!
                                            .parameters?.max_tokens
                                    "
                                    @update:model-value="
                                        (v: string | number) => {
                                            const cfg = getModuleConfig(
                                                mod.value,
                                                agent.name,
                                            )!;
                                            if (!cfg.parameters)
                                                cfg.parameters = {};
                                            cfg.parameters.max_tokens =
                                                parseInt(String(v)) || 256;
                                        }
                                    "
                                />
                            </Field>
                        </div>

                        <Field :label="'Preferencias adicionales'">
                            <Textarea
                                :model-value="getModuleConfig(mod.value, agent.name)!.additional_instructions ?? ''"
                                @update:model-value="(value: string | number) => { getModuleConfig(mod.value, agent.name)!.additional_instructions = String(value); }"
                                rows="4"
                                class="font-mono text-xs"
                            />
                        </Field>

                        <div class="flex items-center gap-2">
                            <Switch
                                :checked="
                                    getModuleConfig(mod.value, agent.name)!
                                        .enabled
                                "
                                @update:checked="
                                    (v: boolean) =>
                                        (getModuleConfig(
                                            mod.value,
                                            agent.name,
                                        )!.enabled = v)
                                "
                            />
                            <Label class="text-sm">{{
                                t('households.aiConfig.active')
                            }}</Label>
                        </div>

                        <Separator />

                        <Button
                            size="sm"
                            @click="
                                submitUpdateModuleConfig(
                                    mod.value,
                                    agent.name,
                                    getModuleConfig(mod.value, agent.name)!,
                                )
                            "
                        >
                            <Save class="mr-2 size-4" />
                            {{ t('common.actions.save') }}
                        </Button>
                    </template>

                    <template v-else>
                        <div
                            class="rounded-lg border border-dashed p-4 text-center"
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ t('households.aiConfig.agentNotSync') }}
                            </p>
                        </div>
                    </template>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
