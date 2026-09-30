<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Sparkles, Loader2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { generate as generateRecipe } from '@/actions/App/Http/Controllers/Web/RecipeAiController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        hasProvider: boolean;
        extraPrompt?: string | null;
    }>(),
    {
        extraPrompt: null,
    },
);

const emit = defineEmits<{
    generated: [recipe: any];
}>();

const showModal = ref(false);
const prompt = ref('');
const loading = ref(false);
const error = ref<string | null>(null);
const generation = useHttp<{ prompt: string }, any>(generateRecipe(), {
    prompt: '',
});
const elapsedSeconds = ref(0);
let elapsedTimer: ReturnType<typeof setInterval> | null = null;

const generationStatus = computed(() => {
    if (elapsedSeconds.value < 3) {
        return t('recipes.aiGenerator.statusPreparing');
    }

    if (elapsedSeconds.value < 12) {
        return t('recipes.aiGenerator.statusIngredients');
    }

    if (elapsedSeconds.value < 25) {
        return t('recipes.aiGenerator.statusOrganizing');
    }

    return t('recipes.aiGenerator.statusValidating');
});

function startElapsedTimer() {
    elapsedSeconds.value = 0;
    elapsedTimer = setInterval(() => {
        elapsedSeconds.value += 1;
    }, 1000);
}

function stopElapsedTimer() {
    if (elapsedTimer !== null) {
        clearInterval(elapsedTimer);
        elapsedTimer = null;
    }
}

function formatElapsed(seconds: number): string {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;

    return `${minutes}:${String(remainingSeconds).padStart(2, '0')}`;
}

onBeforeUnmount(stopElapsedTimer);

async function generate() {
    loading.value = true;
    error.value = null;
    startElapsedTimer();

    try {
        generation.prompt = prompt.value;
        await generation.submit({
            onSuccess: (recipe) => {
                emit('generated', recipe);
                showModal.value = false;
                prompt.value = '';
            },
            onHttpException: (response) => {
                const data =
                    typeof response.data === 'string'
                        ? JSON.parse(response.data)
                        : response.data;
                error.value = String(
                    data.message ?? t('recipes.aiGenerator.generationError'),
                );
            },
        });
    } catch {
        if (!error.value) {
            error.value = t('common.validation.connection');
        }
    } finally {
        stopElapsedTimer();
        loading.value = false;
    }
}
</script>

<template>
    <div v-if="hasProvider">
        <Button type="button" variant="outline" @click="showModal = true">
            <Sparkles class="mr-2 h-4 w-4" />
            {{ t('recipes.aiGenerator.generate') }}
        </Button>

        <Dialog v-model:open="showModal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{
                        t('recipes.aiGenerator.title')
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ t('recipes.aiGenerator.description') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div
                        v-if="props.extraPrompt"
                        class="rounded-md bg-muted p-3"
                    >
                        <p
                            class="mb-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ t('recipes.aiGenerator.systemInstructions') }}
                        </p>
                        <p
                            class="text-sm whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ props.extraPrompt }}
                        </p>
                    </div>

                    <Textarea
                        v-model="prompt"
                        :placeholder="
                            t('recipes.aiGenerator.promptPlaceholder')
                        "
                        rows="3"
                        :disabled="loading"
                    />

                    <div
                        v-if="loading"
                        role="status"
                        aria-live="polite"
                        class="flex items-start gap-3 rounded-lg border bg-muted/50 p-4"
                    >
                        <Loader2
                            class="mt-0.5 size-5 shrink-0 animate-spin text-primary"
                        />
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-medium">
                                {{ generationStatus }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Tiempo transcurrido:
                                <span class="tabular-nums">{{
                                    formatElapsed(elapsedSeconds)
                                }}</span>
                            </p>
                        </div>
                    </div>

                    <p v-if="error" class="text-sm text-destructive">
                        {{ error }}
                    </p>

                    <div class="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            @click="showModal = false"
                            :disabled="loading"
                        >
                            Cancelar
                        </Button>
                        <Button
                            @click="generate"
                            :disabled="!prompt.trim() || loading"
                        >
                            <Loader2
                                v-if="loading"
                                class="mr-2 h-4 w-4 animate-spin"
                            />
                            {{ loading ? 'Generando…' : 'Generar' }}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
