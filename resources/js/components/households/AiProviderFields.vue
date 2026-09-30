<script setup lang="ts">
import { Brain, FileJson, Info, Lock, Paperclip, Wrench } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

type ProviderFieldsModel = {
    name: string;
    driver: string;
    base_url: string;
    model: string;
    api_key: string;
    modules: string[];
    configuration: {
        temperature: number | null;
        model_capabilities: {
            reasoning: boolean;
            reasoning_effort: string;
            context_tokens: number | null;
        };
    };
    privacy_level: string;
    fallback_policy: string;
    family: string | null;
    description: string | null;
    attachment: boolean;
    reasoning: boolean;
    tool_call: boolean;
    structured_output: boolean;
    open_weights: boolean;
    context_window: number | null;
    max_output_tokens: number | null;
};

const props = defineProps<{
    provider: ProviderFieldsModel;
    editing?: boolean;
}>();

const { t } = useI18n();
const availableModules = [
    'general',
    'economy',
    'recipes',
    'assistant',
    'image_generation',
    'translations',
];

function setReasoning(enabled: boolean) {
    props.provider.configuration.model_capabilities.reasoning = enabled;
    props.provider.reasoning = enabled;
}

function setContext(value: string | number) {
    const context = value === '' ? null : Number(value);
    props.provider.context_window = context;
    props.provider.configuration.model_capabilities.context_tokens = context;
}

function setTemperature(value: string | number) {
    props.provider.configuration.temperature =
        value === '' ? null : Number(value);
}

function setOutputLimit(value: string | number) {
    props.provider.max_output_tokens = value === '' ? null : Number(value);
}
</script>

<template>
    <div class="space-y-6">
        <section class="space-y-3">
            <h5 class="text-sm font-semibold">
                {{ t('households.aiProviders.connectionSection') }}
            </h5>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Field :label="t('households.aiProviders.name')">
                    <Input
                        v-model="provider.name"
                        :placeholder="
                            t('households.aiProviders.namePlaceholder')
                        "
                    />
                </Field>
                <Field :label="t('households.aiProviders.driver')">
                    <Select v-model="provider.driver">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="openai">OpenAI</SelectItem>
                            <SelectItem value="anthropic">Anthropic</SelectItem>
                            <SelectItem value="gemini"
                                >Google Gemini</SelectItem
                            >
                            <SelectItem value="ollama">
                                Ollama ({{
                                    t(
                                        'households.aiProviders.local',
                                    ).toLocaleLowerCase()
                                }})
                            </SelectItem>
                            <SelectItem value="openai-compatible"
                                >OpenAI Compatible</SelectItem
                            >
                            <SelectItem value="openrouter"
                                >OpenRouter</SelectItem
                            >
                            <SelectItem value="xai">xAI (Grok)</SelectItem>
                            <SelectItem value="groq">Groq</SelectItem>
                            <SelectItem value="deepseek">DeepSeek</SelectItem>
                            <SelectItem value="mistral">Mistral</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                <Field :label="t('households.aiProviders.baseUrl')">
                    <Input
                        v-model="provider.base_url"
                        placeholder="https://api.example.com/v1"
                    />
                </Field>
                <div class="space-y-2">
                    <div class="flex items-center gap-1">
                        <span class="text-sm font-medium">
                            {{ t('households.aiProviders.model') }}
                        </span>
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-6"
                                    :disabled="
                                        !provider.family &&
                                        !provider.description
                                    "
                                    :aria-label="
                                        t('households.aiProviders.modelDetails')
                                    "
                                >
                                    <Info class="size-4" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                align="start"
                                class="w-80 max-w-[calc(100vw-2rem)] space-y-2 text-sm"
                            >
                                <p v-if="provider.family" class="font-medium">
                                    {{ provider.family }}
                                </p>
                                <p
                                    v-if="provider.description"
                                    class="text-muted-foreground"
                                >
                                    {{ provider.description }}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <Badge
                                        v-if="provider.attachment"
                                        variant="secondary"
                                        ><Paperclip class="mr-1 size-3" />{{
                                            t('catalog.vision')
                                        }}</Badge
                                    >
                                    <Badge
                                        v-if="provider.reasoning"
                                        variant="secondary"
                                        ><Brain class="mr-1 size-3" />{{
                                            t('catalog.reasoning')
                                        }}</Badge
                                    >
                                    <Badge
                                        v-if="provider.tool_call"
                                        variant="secondary"
                                        ><Wrench class="mr-1 size-3" />{{
                                            t('catalog.tools')
                                        }}</Badge
                                    >
                                    <Badge
                                        v-if="provider.structured_output"
                                        variant="secondary"
                                        ><FileJson class="mr-1 size-3" />{{
                                            t('catalog.json')
                                        }}</Badge
                                    >
                                    <Badge
                                        v-if="provider.open_weights"
                                        variant="secondary"
                                        ><Lock class="mr-1 size-3" />{{
                                            t('catalog.openWeights')
                                        }}</Badge
                                    >
                                </div>
                            </PopoverContent>
                        </Popover>
                    </div>
                    <Input v-model="provider.model" placeholder="gpt-4o" />
                </div>
                <Field
                    class="md:col-span-2"
                    :label="
                        editing
                            ? t('households.aiProviders.apiKey')
                            : t('households.aiProviders.apiKeyShort')
                    "
                >
                    <Input
                        v-model="provider.api_key"
                        type="password"
                        placeholder="sk-..."
                    />
                </Field>
            </div>
        </section>

        <section class="space-y-3 border-t pt-5">
            <h5 class="text-sm font-semibold">
                {{ t('households.aiProviders.modelConfigurationSection') }}
            </h5>
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Field
                    class="rounded-md border p-3"
                    :label="t('households.aiProviders.reasoningMode')"
                >
                    <div class="flex items-center">
                        <Switch
                            :checked="
                                provider.configuration.model_capabilities
                                    .reasoning
                            "
                            :aria-label="
                                t('households.aiProviders.reasoningMode')
                            "
                            @update:checked="setReasoning"
                        />
                    </div>
                </Field>
                <Field
                    class="rounded-md border p-3"
                    :label="t('catalog.vision')"
                >
                    <div class="flex items-center">
                        <Switch
                            :checked="provider.attachment"
                            :aria-label="t('catalog.vision')"
                            @update:checked="
                                (enabled: boolean) =>
                                    (provider.attachment = enabled)
                            "
                        />
                    </div>
                </Field>
                <Field
                    class="rounded-md border p-3"
                    :label="t('catalog.tools')"
                >
                    <div class="flex items-center">
                        <Switch
                            :checked="provider.tool_call"
                            :aria-label="t('catalog.tools')"
                            @update:checked="
                                (enabled: boolean) =>
                                    (provider.tool_call = enabled)
                            "
                        />
                    </div>
                </Field>
                <Field class="rounded-md border p-3" :label="t('catalog.json')">
                    <div class="flex items-center">
                        <Switch
                            :checked="provider.structured_output"
                            :aria-label="t('catalog.json')"
                            @update:checked="
                                (enabled: boolean) =>
                                    (provider.structured_output = enabled)
                            "
                        />
                    </div>
                </Field>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ t('households.aiProviders.reasoningHint') }}
            </p>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Field :label="t('households.aiProviders.reasoningEffort')">
                    <Select
                        v-model="
                            provider.configuration.model_capabilities
                                .reasoning_effort
                        "
                        :disabled="
                            !provider.configuration.model_capabilities.reasoning
                        "
                    >
                        <SelectTrigger
                            ><SelectValue
                                :placeholder="
                                    t(
                                        'households.aiProviders.selectReasoningEffort',
                                    )
                                "
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="low">{{
                                t('households.aiProviders.reasoningEffortLow')
                            }}</SelectItem>
                            <SelectItem value="medium">{{
                                t(
                                    'households.aiProviders.reasoningEffortMedium',
                                )
                            }}</SelectItem>
                            <SelectItem value="high">{{
                                t('households.aiProviders.reasoningEffortHigh')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                <Field :label="t('catalog.temperature')">
                    <Input
                        :model-value="provider.configuration.temperature ?? ''"
                        type="number"
                        min="0"
                        max="2"
                        step="0.1"
                        placeholder="0.7"
                        @update:model-value="setTemperature"
                    />
                </Field>
                <Field :label="t('catalog.context')">
                    <Input
                        :model-value="provider.context_window ?? ''"
                        type="number"
                        min="1024"
                        :placeholder="t('catalog.contextWindowHint')"
                        @update:model-value="setContext"
                    />
                </Field>
                <Field :label="t('catalog.outputLimit')">
                    <Input
                        :model-value="provider.max_output_tokens ?? ''"
                        type="number"
                        min="1"
                        :placeholder="t('catalog.outputLimitHint')"
                        @update:model-value="setOutputLimit"
                    />
                </Field>
            </div>
        </section>

        <section class="space-y-3 border-t pt-5">
            <h5 class="text-sm font-semibold">
                {{ t('households.aiProviders.privacySection') }}
            </h5>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Field :label="t('households.aiProviders.privacyLevel')">
                    <Select v-model="provider.privacy_level">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="local">{{
                                t('households.aiProviders.local')
                            }}</SelectItem>
                            <SelectItem value="self_hosted">{{
                                t('households.aiProviders.selfHosted')
                            }}</SelectItem>
                            <SelectItem value="cloud">{{
                                t('households.aiProviders.cloud')
                            }}</SelectItem>
                            <SelectItem value="unknown">{{
                                t('households.aiProviders.unknown')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                <Field :label="t('households.aiProviders.fallbackPolicy')">
                    <Select v-model="provider.fallback_policy">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="local_only">{{
                                t('households.aiProviders.localOnly')
                            }}</SelectItem>
                            <SelectItem value="same_privacy_level">{{
                                t('households.aiProviders.samePrivacy')
                            }}</SelectItem>
                            <SelectItem value="allow_cloud">{{
                                t('households.aiProviders.allowCloud')
                            }}</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
            </div>
        </section>

        <section class="space-y-3 border-t pt-5">
            <h5 class="text-sm font-semibold">
                {{ t('households.aiProviders.modulesSection') }}
            </h5>
            <div class="flex flex-wrap gap-x-5 gap-y-3">
                <label
                    v-for="module in availableModules"
                    :key="module"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="provider.modules"
                        type="checkbox"
                        :value="module"
                    />
                    {{ t(`households.aiProviders.modules.${module}`) }}
                </label>
            </div>
        </section>
    </div>
</template>
