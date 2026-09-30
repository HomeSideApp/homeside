<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2, Save } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import {
    updateGlobalPrompt,
    updateModulePrompt,
} from '@/actions/App/Http/Controllers/Admin/AdminAiProviderController';
import IconAction from '@/components/admin/IconAction.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';

type GlobalSetting = {
    id?: string;
    module: string | null;
    extra_prompt: string;
};

const { t } = useI18n();

const props = defineProps<{
    globalSettings: GlobalSetting[];
}>();

function getPromptForModule(module: string | null): string {
    const setting = props.globalSettings.find((s) => s.module === module);

    return setting?.extra_prompt ?? '';
}

const globalPromptForm = useForm({
    extra_prompt: getPromptForModule(null),
});

function submitGlobalPrompt() {
    globalPromptForm.put(updateGlobalPrompt.url());
}

const economyPromptForm = useForm({
    module: 'economy',
    extra_prompt: getPromptForModule('economy'),
});

const recipesPromptForm = useForm({
    module: 'recipes',
    extra_prompt: getPromptForModule('recipes'),
});

const generalPromptForm = useForm({
    module: 'general',
    extra_prompt: getPromptForModule('general'),
});

function submitModulePrompt(form: ReturnType<typeof useForm>) {
    form.put(updateModulePrompt.url());
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">{{
                t('admin.globalPrompt.title')
            }}</CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Prompt global -->
            <div class="space-y-3">
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.globalPrompt.globalNote') }}
                </p>
                <Field :label="t('admin.globalPrompt.globalLabel')">
                    <Textarea
                        v-model="globalPromptForm.extra_prompt"
                        rows="4"
                        class="font-mono text-xs"
                        :placeholder="t('admin.globalPrompt.globalPlaceholder')"
                    />
                </Field>
                <IconAction
                    :label="t('admin.globalPrompt.saveGlobal')"
                    variant="default"
                    :disabled="globalPromptForm.processing"
                    @click="submitGlobalPrompt"
                >
                    <Loader2
                        v-if="globalPromptForm.processing"
                        class="animate-spin"
                        aria-hidden="true"
                    />
                    <Save v-else aria-hidden="true" />
                </IconAction>
            </div>

            <Separator />

            <!-- Prompts por módulo -->
            <div class="space-y-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.globalPrompt.moduleNote') }}
                </p>

                <!-- Economy -->
                <div class="space-y-3 rounded-lg border p-4">
                    <h4 class="text-sm font-medium">
                        {{ t('admin.globalPrompt.economyTitle') }}
                    </h4>
                    <Field :label="t('admin.globalPrompt.economyLabel')">
                        <Textarea
                            v-model="economyPromptForm.extra_prompt"
                            rows="3"
                            class="font-mono text-xs"
                            :placeholder="
                                t('admin.globalPrompt.economyPlaceholder')
                            "
                        />
                    </Field>
                    <IconAction
                        :label="`${t('admin.globalPrompt.save')}: ${t('admin.globalPrompt.economyTitle')}`"
                        variant="outline"
                        :disabled="economyPromptForm.processing"
                        @click="submitModulePrompt(economyPromptForm)"
                    >
                        <Loader2
                            v-if="economyPromptForm.processing"
                            class="animate-spin"
                            aria-hidden="true"
                        />
                        <Save v-else aria-hidden="true" />
                    </IconAction>
                </div>

                <!-- Recipes -->
                <div class="space-y-3 rounded-lg border p-4">
                    <h4 class="text-sm font-medium">
                        {{ t('admin.globalPrompt.recipesTitle') }}
                    </h4>
                    <Field :label="t('admin.globalPrompt.recipesLabel')">
                        <Textarea
                            v-model="recipesPromptForm.extra_prompt"
                            rows="3"
                            class="font-mono text-xs"
                            :placeholder="
                                t('admin.globalPrompt.recipesPlaceholder')
                            "
                        />
                    </Field>
                    <IconAction
                        :label="`${t('admin.globalPrompt.save')}: ${t('admin.globalPrompt.recipesTitle')}`"
                        variant="outline"
                        :disabled="recipesPromptForm.processing"
                        @click="submitModulePrompt(recipesPromptForm)"
                    >
                        <Loader2
                            v-if="recipesPromptForm.processing"
                            class="animate-spin"
                            aria-hidden="true"
                        />
                        <Save v-else aria-hidden="true" />
                    </IconAction>
                </div>

                <!-- General -->
                <div class="space-y-3 rounded-lg border p-4">
                    <h4 class="text-sm font-medium">
                        {{ t('admin.globalPrompt.generalTitle') }}
                    </h4>
                    <Field :label="t('admin.globalPrompt.generalLabel')">
                        <Textarea
                            v-model="generalPromptForm.extra_prompt"
                            rows="3"
                            class="font-mono text-xs"
                            :placeholder="
                                t('admin.globalPrompt.generalPlaceholder')
                            "
                        />
                    </Field>
                    <IconAction
                        :label="`${t('admin.globalPrompt.save')}: ${t('admin.globalPrompt.generalTitle')}`"
                        variant="outline"
                        :disabled="generalPromptForm.processing"
                        @click="submitModulePrompt(generalPromptForm)"
                    >
                        <Loader2
                            v-if="generalPromptForm.processing"
                            class="animate-spin"
                            aria-hidden="true"
                        />
                        <Save v-else aria-hidden="true" />
                    </IconAction>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
