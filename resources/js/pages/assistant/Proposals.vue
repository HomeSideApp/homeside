<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle, XCircle, ClipboardList, Loader2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    acceptProposal as acceptProposalRoute,
    rejectProposal as rejectProposalRoute,
} from '@/routes/assistant';

type Proposal = {
    id: string;
    type: string;
    payload: Record<string, unknown>;
    reason: string | null;
    status: string;
    expires_at: string | null;
    created_at: string;
};

defineProps<{
    proposals: Proposal[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'assistant.title', href: '/assistant' },
            { title: 'assistant.proposals.title', href: '/assistant/proposals' },
        ],
    },
});

const processingId = ref<string | null>(null);
const { d, t } = useI18n();

function acceptProposal(proposalId: string) {
    processingId.value = proposalId;
    router.post(acceptProposalRoute(proposalId).url, {}, {
        onFinish: () => {
 processingId.value = null; 
},
    });
}

function rejectProposal(proposalId: string) {
    processingId.value = proposalId;
    router.post(rejectProposalRoute(proposalId).url, {}, {
        onFinish: () => {
 processingId.value = null; 
},
    });
}

function formatDate(iso: string): string {
    return d(new Date(iso), 'dateTime');
}

function statusColor(status: string): string {
    return {
        pending: 'bg-yellow-100 text-yellow-700',
        accepted: 'bg-green-100 text-green-700',
        rejected: 'bg-red-100 text-red-700',
        expired: 'bg-gray-100 text-gray-500',
    }[status] ?? 'bg-gray-100 text-gray-500';
}

function statusLabel(status: string): string {
    return t(`assistant.proposals.status.${status}`, status);
}

function typeLabel(type: string): string {
    return {
        add_shopping_items: t('assistant.proposals.type.addShoppingItems'),
        create_recipe: t('assistant.proposals.type.createRecipe'),
    }[type] ?? type;
}

function formatPayload(proposal: Proposal): string {
    if (proposal.type === 'add_shopping_items' && Array.isArray(proposal.payload.items)) {
        return proposal.payload.items.map((item: Record<string, unknown>) => `• ${item.name ?? item}`).join('\n');
    }

    if (proposal.type === 'create_recipe' && proposal.payload.name) {
        return t('assistant.proposals.recipe', { name: proposal.payload.name });
    }

    return JSON.stringify(proposal.payload, null, 2);
}
</script>

<template>
    <Head :title="t('assistant.proposals.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading variant="small" :title="t('assistant.proposals.title')" :description="t('assistant.proposals.description')" />

        <div v-if="proposals.length === 0" class="rounded-lg border border-dashed p-12 text-center">
            <ClipboardList class="mx-auto size-12 text-muted-foreground/50" />
            <p class="mt-4 text-sm text-muted-foreground">{{ t('assistant.proposals.empty') }}</p>
        </div>

        <div v-else class="grid gap-3">
            <Card v-for="proposal in proposals" :key="proposal.id">
                <CardContent class="p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium">{{ typeLabel(proposal.type) }}</span>
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusColor(proposal.status)]">
                                {{ statusLabel(proposal.status) }}
                            </span>
                        </div>
                        <span class="text-xs text-muted-foreground">{{ formatDate(proposal.created_at) }}</span>
                    </div>

                    <p v-if="proposal.reason" class="text-sm text-muted-foreground">{{ proposal.reason }}</p>

                    <pre class="rounded bg-muted p-3 text-xs whitespace-pre-wrap">{{ formatPayload(proposal) }}</pre>

                    <div v-if="proposal.status === 'pending'" class="flex items-center gap-2">
                        <Button size="sm" @click="acceptProposal(proposal.id)" :disabled="processingId === proposal.id">
                            <Loader2 v-if="processingId === proposal.id" class="mr-1 size-3 animate-spin" />
                            <CheckCircle v-else class="mr-1 size-3" />
                            {{ t('assistant.proposals.accept') }}
                        </Button>
                        <Button size="sm" variant="outline" @click="rejectProposal(proposal.id)" :disabled="processingId === proposal.id">
                            <XCircle class="mr-1 size-3" />
                            {{ t('assistant.proposals.reject') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
