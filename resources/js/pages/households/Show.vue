<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import { BookUser, Bot, Copy, Mail, Settings, Trash2, X } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import InviteDialog from '@/components/households/InviteDialog.vue';
import MemberList from '@/components/households/MemberList.vue';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useAuthorization } from '@/composables/useAuthorization';

const page = usePage();
const { d, t } = useI18n();

type PendingInvitation = {
    id: string;
    email: string;
    status: string;
    status_label: string;
    expires_at: string;
    created_at: string;
};

type HouseholdData = {
    id: string;
    name: string;
    invite_code?: string;
    members: Array<{
        id: string;
        role: string;
        joined_at: string;
        user: { id: string; name: string; email: string };
        contact?: {
            display_name: string;
            avatar_url: string | null;
            email: string | null;
            phone: string | null;
            labels: Array<{ id: string; name: string }>;
        } | null;
    }>;
    pending_invitations: PendingInvitation[];
    created_by: string;
};

const props = defineProps<{
    household: HouseholdData;
}>();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'nav.households', href: '/households' },
            {
                title: props.household.name,
                href: `/households/${props.household.id}`,
            },
        ],
    });
});

function cancelInvitation(invitationId: string) {
    router.delete(`/households/invitations/${invitationId}`);
}

const { can } = useAuthorization();
const currentUserId = computed(() => (page.props.auth.user as any)?.id);
const isAdmin = computed(() =>
    props.household.members.some(
        (m) => m.user.id === currentUserId.value && m.role === 'admin',
    ),
);
const inviteCodeCopied = ref(false);

function copyInviteCode() {
    if (props.household.invite_code) {
        navigator.clipboard.writeText(props.household.invite_code);
        inviteCodeCopied.value = true;
        setTimeout(() => {
            inviteCodeCopied.value = false;
        }, 2000);
    }
}

function deleteHousehold() {
    router.delete(`/households/${props.household.id}`);
}
</script>

<template>
    <Head :title="household.name" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                variant="small"
                :title="household.name"
                :description="t('households.show.details')"
            />
            <TooltipProvider>
                <div class="flex items-center gap-2">
                    <Tooltip v-if="can('contacts.view')">
                        <TooltipTrigger as-child>
                            <Button variant="outline" size="icon-sm" as-child>
                                <Link
                                    :href="`/households/${household.id}/contacts`"
                                    :aria-label="t('households.show.contacts')"
                                >
                                    <BookUser aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('households.show.contacts')
                        }}</TooltipContent>
                    </Tooltip>
                    <Tooltip v-if="isAdmin">
                        <TooltipTrigger as-child>
                            <Button variant="outline" size="icon-sm" as-child>
                                <Link
                                    :href="`/households/${household.id}/settings/edit`"
                                    :aria-label="t('households.show.settings')"
                                >
                                    <Settings aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('households.show.settings')
                        }}</TooltipContent>
                    </Tooltip>
                    <Tooltip v-if="isAdmin">
                        <TooltipTrigger as-child>
                            <Button variant="outline" size="icon-sm" as-child>
                                <Link
                                    :href="`/households/${household.id}/settings/ai-providers`"
                                    :aria-label="
                                        t('households.show.aiSettings')
                                    "
                                >
                                    <Bot aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            t('households.show.aiSettings')
                        }}</TooltipContent>
                    </Tooltip>
                    <AlertDialog v-if="isAdmin">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <AlertDialogTrigger as-child>
                                    <Button
                                        variant="destructive"
                                        size="icon-sm"
                                        :aria-label="
                                            t('households.show.delete')
                                        "
                                    >
                                        <Trash2 aria-hidden="true" />
                                    </Button>
                                </AlertDialogTrigger>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                t('households.show.delete')
                            }}</TooltipContent>
                        </Tooltip>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>{{
                                    t('households.show.deleteTitle')
                                }}</AlertDialogTitle>
                                <AlertDialogDescription>
                                    {{ t('households.show.deleteDescription') }}
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>{{
                                    t('common.actions.cancel')
                                }}</AlertDialogCancel>
                                <AlertDialogAction @click="deleteHousehold">
                                    {{ t('households.show.delete') }}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </TooltipProvider>
        </div>

        <div
            v-if="isAdmin && household.invite_code"
            class="rounded-lg border bg-muted/50 p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium">
                        {{ t('households.show.inviteCode') }}
                    </p>
                    <p class="font-mono text-lg">{{ household.invite_code }}</p>
                </div>
                <TooltipProvider>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button
                                variant="outline"
                                size="icon-sm"
                                :aria-label="
                                    inviteCodeCopied
                                        ? t('households.show.copied')
                                        : t('households.show.copy')
                                "
                                @click="copyInviteCode"
                            >
                                <Copy aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{
                            inviteCodeCopied
                                ? t('households.show.copied')
                                : t('households.show.copy')
                        }}</TooltipContent>
                    </Tooltip>
                    <span role="status" class="text-xs text-muted-foreground">{{
                        inviteCodeCopied ? t('households.show.copied') : ''
                    }}</span>
                </TooltipProvider>
            </div>
        </div>

        <Separator />

        <div>
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-medium">
                    {{ t('households.show.members') }}
                </h3>
                <InviteDialog v-if="isAdmin" :household-id="household.id" />
            </div>
            <MemberList
                :members="household.members"
                :current-user-id="currentUserId"
                :household-id="household.id"
                :is-admin="isAdmin"
            />

            <div
                v-if="
                    household.pending_invitations &&
                    household.pending_invitations.length > 0
                "
                class="mt-6"
            >
                <h4 class="mb-3 text-sm font-medium text-muted-foreground">
                    {{ t('households.show.pendingInvitations') }}
                </h4>
                <div class="grid gap-2">
                    <Card
                        v-for="invitation in household.pending_invitations"
                        :key="invitation.id"
                        class="border-dashed"
                    >
                        <CardContent
                            class="flex items-center justify-between py-3"
                        >
                            <div class="flex items-center gap-3">
                                <Mail class="size-4 text-muted-foreground" />
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ invitation.email }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            t('households.show.expiresAt', {
                                                date: d(
                                                    new Date(
                                                        invitation.expires_at,
                                                    ),
                                                    'short',
                                                ),
                                            })
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge variant="secondary">{{
                                    invitation.status_label
                                }}</Badge>
                                <TooltipProvider v-if="isAdmin">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="`${t('households.show.cancelInvitation')}: ${invitation.email}`"
                                                @click="
                                                    cancelInvitation(
                                                        invitation.id,
                                                    )
                                                "
                                            >
                                                <X aria-hidden="true" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>{{
                                            t(
                                                'households.show.cancelInvitation',
                                            )
                                        }}</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
