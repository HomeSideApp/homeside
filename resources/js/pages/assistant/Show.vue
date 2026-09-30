<script setup lang="ts">
import { Head, Link, setLayoutProps, useHttp } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Send, X } from '@lucide/vue';
import { ref, nextTick, onMounted, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import AssistantMessageContent from '@/components/AssistantMessageContent.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    createRecipe as assistantCreateRecipe,
    index as assistantIndex,
    sendMessage as assistantSendMessage,
} from '@/routes/assistant';

type Message = {
    id: string;
    user_message: string | null;
    reply: string | null;
    status: string;
    created_at: string;
    recipe_key?: string;
};

type MessageResponse = {
    id: string;
    reply: string | null;
    status: string;
    recipe_key?: string;
    message?: string;
};

type RecipeResponse = {
    confirmation: Message;
    message?: string;
};

const { t } = useI18n();

const props = defineProps<{
    conversation: {
        id: string;
        agent: string;
        title: string;
        created_at: string;
    };
    messages: Message[];
}>();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'assistant.title', href: assistantIndex() },
            { title: props.conversation.title, href: '#' },
        ],
    });
});

const inputMessage = ref('');
const messages = ref<Message[]>([...props.messages]);
const sending = ref(false);
const creatingRecipeKey = ref<string | null>(null);
const elapsedSeconds = ref(0);
const messagesContainer = ref<HTMLElement | null>(null);
const messageRequest = useHttp<{ message: string }, MessageResponse>({
    message: '',
});
const recipeRequest = useHttp<Record<string, never>, RecipeResponse>({});
const sendCancelled = ref(false);
let elapsedTimer: ReturnType<typeof setInterval> | null = null;

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
        }
    });
}

function startElapsedTimer() {
    elapsedSeconds.value = 0;
    elapsedTimer = setInterval(() => {
        elapsedSeconds.value += 1;
    }, 1000);
}

function stopElapsedTimer() {
    if (elapsedTimer) {
        clearInterval(elapsedTimer);
        elapsedTimer = null;
    }
}

function cancelSend() {
    sendCancelled.value = true;
    messageRequest.cancel();
}

function formatElapsed(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}

onMounted(() => {
    scrollToBottom();
});

async function sendMessage() {
    const original = inputMessage.value.trim();

    if (!original || sending.value) {
        return;
    }

    // Si estamos en modo modificación, prefijar para que el backend regenere la receta
    const isModification = modifying.value;
    const message = isModification
        ? `Modifica la receta: ${original}`
        : original;
    modifying.value = false;

    if (isModification) {
        messages.value.forEach((message) => {
            message.recipe_key = undefined;
        });
    }

    inputMessage.value = '';
    sending.value = true;

    // Optimistic: add user message to list
    const tempId = `temp-${Date.now()}`;
    messages.value.push({
        id: tempId,
        user_message: message,
        reply: null,
        status: 'pending',
        created_at: new Date().toISOString(),
    });
    scrollToBottom();

    sendCancelled.value = false;
    startElapsedTimer();

    try {
        messageRequest.message = message;
        await messageRequest.post(
            assistantSendMessage(props.conversation.id).url,
            {
                onSuccess: (data) => {
                    if (data.recipe_key) {
                        messages.value.forEach((message) => {
                            message.recipe_key = undefined;
                        });
                    }

                    // Replace temp message with real response
                    const idx = messages.value.findIndex(
                        (m) => m.id === tempId,
                    );

                    if (idx !== -1) {
                        messages.value[idx] = {
                            id: data.id,
                            user_message: message,
                            reply: data.reply,
                            status: data.status,
                            created_at: new Date().toISOString(),
                            recipe_key: data.recipe_key,
                        };
                    }
                },
                onHttpException: (response) => {
                    const data =
                        typeof response.data === 'string'
                            ? (JSON.parse(response.data) as MessageResponse)
                            : (response.data as MessageResponse);
                    const idx = messages.value.findIndex(
                        (item) => item.id === tempId,
                    );

                    if (idx !== -1) {
                        messages.value[idx].status = 'error';
                        messages.value[idx].reply =
                            data.message ?? 'Error al enviar el mensaje.';
                    }
                },
            },
        );
    } catch {
        const idx = messages.value.findIndex((m) => m.id === tempId);

        if (idx !== -1 && messages.value[idx].status === 'pending') {
            messages.value[idx].status = 'error';
            messages.value[idx].reply = sendCancelled.value
                ? t('assistant.chat.generationCancelled')
                : t('assistant.chat.connectionError');
        }
    } finally {
        sending.value = false;
        stopElapsedTimer();
        scrollToBottom();
    }
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

async function createRecipe(message: Message) {
    if (!message.recipe_key || creatingRecipeKey.value) {
        return;
    }

    creatingRecipeKey.value = message.recipe_key;
    let failureMessage = t('assistant.chat.recipeCreationFailed');

    try {
        await recipeRequest.post(
            assistantCreateRecipe({
                conversation: props.conversation.id,
                recipeRun: message.recipe_key,
            }).url,
            {
                onSuccess: (data) => {
                    message.recipe_key = undefined;
                    messages.value.push({
                        ...data.confirmation,
                        user_message: null,
                    });
                    toast.success(t('assistant.chat.recipeCreated'));
                    scrollToBottom();
                },
                onHttpException: (response) => {
                    const data =
                        typeof response.data === 'string'
                            ? (JSON.parse(response.data) as RecipeResponse)
                            : (response.data as RecipeResponse);
                    failureMessage = data.message ?? failureMessage;
                },
            },
        );
    } catch {
        toast.error(failureMessage);
    } finally {
        creatingRecipeKey.value = null;
    }
}

const modifying = ref(false);
const inputEl = ref<HTMLInputElement | null>(null);

function startModifying() {
    modifying.value = true;
    inputMessage.value = '';
    nextTick(() => inputEl.value?.focus());
}
</script>

<template>
    <Head :title="`${t('assistant.title')} — ${conversation.title}`" />

    <div class="flex h-[calc(100vh-4rem)] flex-col">
        <!-- Header -->
        <div class="flex items-center gap-3 border-b px-8 py-3">
            <Link
                :href="assistantIndex().url"
                class="text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
            </Link>
            <Heading
                variant="small"
                :title="conversation.title"
                :description="conversation.agent"
            />
        </div>

        <!-- Messages -->
        <div
            ref="messagesContainer"
            class="flex-1 space-y-4 overflow-y-auto px-8 py-4"
        >
            <div
                v-if="messages.length === 0"
                class="flex h-full items-center justify-center"
            >
                <p class="text-sm text-muted-foreground">
                    {{ t('assistant.chat.prompt') }}
                </p>
            </div>

            <div v-for="msg in messages" :key="msg.id" class="space-y-2">
                <!-- User message -->
                <div v-if="msg.user_message" class="flex justify-end">
                    <div
                        class="max-w-[70%] rounded-lg bg-primary px-4 py-2 text-sm whitespace-pre-wrap text-primary-foreground"
                    >
                        {{ msg.user_message }}
                    </div>
                </div>

                <!-- Assistant reply (exclude errors) -->
                <div
                    v-if="msg.reply && msg.status !== 'error'"
                    class="flex flex-col justify-start gap-2"
                >
                    <div
                        class="max-w-[70%] rounded-lg bg-muted px-4 py-2 text-sm"
                    >
                        <AssistantMessageContent :text="msg.reply" />
                    </div>
                    <div v-if="msg.recipe_key" class="flex gap-2">
                        <Button
                            size="sm"
                            :disabled="creatingRecipeKey !== null"
                            @click="createRecipe(msg)"
                        >
                            <Loader2
                                v-if="creatingRecipeKey === msg.recipe_key"
                                class="size-4 animate-spin"
                            />
                            {{
                                creatingRecipeKey === msg.recipe_key
                                    ? t('assistant.chat.creating')
                                    : t('assistant.chat.createRecipe')
                            }}
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="startModifying()"
                        >
                            {{ t('assistant.chat.modifySomething') }}
                        </Button>
                    </div>
                </div>

                <!-- Pending indicator -->
                <div
                    v-else-if="msg.status === 'pending'"
                    class="flex justify-start"
                >
                    <div
                        class="flex items-center gap-3 rounded-lg bg-muted px-4 py-2 text-sm text-muted-foreground"
                    >
                        <Loader2 class="size-4 shrink-0 animate-spin" />
                        <span
                            >{{ t('assistant.chat.generating') }}
                            <span class="tabular-nums">{{
                                formatElapsed(elapsedSeconds)
                            }}</span></span
                        >
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-6 px-2 text-xs"
                            @click="cancelSend()"
                        >
                            Cancelar
                        </Button>
                    </div>
                </div>

                <!-- Error (mutually exclusive with reply) -->
                <div
                    v-else-if="msg.status === 'error' && msg.reply"
                    class="flex justify-start"
                >
                    <div
                        class="rounded-lg bg-destructive/10 px-4 py-2 text-sm text-destructive"
                    >
                        ❌ {{ msg.reply }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="border-t px-8 py-4">
            <form @submit.prevent="sendMessage" class="flex items-center gap-2">
                <Input
                    ref="inputEl"
                    v-model="inputMessage"
                    :placeholder="
                        modifying
                            ? t('assistant.chat.modifyPlaceholder')
                            : t('assistant.chat.inputPlaceholder')
                    "
                    :disabled="sending"
                    @keydown="handleKeydown"
                    class="flex-1"
                />
                <Button
                    type="button"
                    v-if="sending"
                    variant="outline"
                    @click="cancelSend()"
                >
                    <X class="size-4" />
                </Button>
                <Button type="submit" v-else :disabled="!inputMessage.trim()">
                    <Send class="size-4" />
                </Button>
            </form>
        </div>
    </div>
</template>
