<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Loader2, MessageSquare, MessageSquarePlus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
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
import { Card, CardContent } from '@/components/ui/card';
import {
    destroy as assistantDestroy,
    show as assistantShow,
    store as assistantStore,
} from '@/routes/assistant';

type Conversation = {
    id: string;
    agent: string;
    title: string;
    runs_count: number;
    created_at: string;
    updated_at: string;
};

defineProps<{
    conversations: Conversation[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'nav.assistant', href: '/assistant' }] },
});

const creating = ref(false);
const deletingConversationId = ref<string | null>(null);
const { d, t } = useI18n();

function createConversation() {
    creating.value = true;
    useForm({}).post(assistantStore().url, {
        onFinish: () => {
            creating.value = false;
        },
    });
}

function deleteConversation(conversationId: string) {
    router.delete(assistantDestroy(conversationId).url, {
        preserveScroll: true,
        onStart: () => {
            deletingConversationId.value = conversationId;
        },
        onFinish: () => {
            deletingConversationId.value = null;
        },
    });
}

function formatDate(iso: string): string {
    return d(new Date(iso), 'dateTime');
}
</script>

<template>
    <Head :title="t('assistant.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between">
            <Heading
                variant="small"
                :title="t('assistant.title')"
                :description="t('assistant.description')"
            />
            <Button @click="createConversation" :disabled="creating">
                <Loader2 v-if="creating" class="mr-2 size-4 animate-spin" />
                <MessageSquarePlus v-else class="mr-2 size-4" />
                {{ t('assistant.newConversation') }}
            </Button>
        </div>

        <div
            v-if="conversations.length === 0"
            class="rounded-lg border border-dashed p-12 text-center"
        >
            <MessageSquare class="mx-auto size-12 text-muted-foreground/50" />
            <p class="mt-4 text-sm text-muted-foreground">
                {{ t('assistant.empty') }}
            </p>
        </div>

        <div v-else class="grid gap-3">
            <Card
                v-for="conversation in conversations"
                :key="conversation.id"
                class="transition-colors hover:bg-accent/50"
            >
                <CardContent class="flex items-center p-0">
                    <Link
                        :href="assistantShow(conversation.id).url"
                        class="flex min-w-0 flex-1 items-center justify-between gap-4 p-4"
                    >
                        <div class="flex items-center gap-3">
                            <MessageSquare
                                class="size-5 text-muted-foreground"
                            />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ conversation.title }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ conversation.agent }} ·
                                    {{ t('assistant.runs', { count: conversation.runs_count }) }}
                                </p>
                            </div>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">{{
                            formatDate(conversation.updated_at)
                        }}</span>
                    </Link>

                    <AlertDialog>
                        <AlertDialogTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="mr-3 shrink-0 text-muted-foreground hover:text-destructive"
                                :disabled="deletingConversationId !== null"
                                :aria-label="t('assistant.deleteTitle')"
                            >
                                <Loader2
                                    v-if="
                                        deletingConversationId ===
                                        conversation.id
                                    "
                                    class="size-4 animate-spin"
                                />
                                <Trash2 v-else class="size-4" />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle
                                    >{{ t('assistant.deleteTitle') }}</AlertDialogTitle
                                >
                                <AlertDialogDescription>
                                    {{ t('assistant.deleteDescription') }}
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>{{ t('common.actions.cancel') }}</AlertDialogCancel>
                                <AlertDialogAction
                                    @click="deleteConversation(conversation.id)"
                                >
                                    {{ t('common.actions.delete') }}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
