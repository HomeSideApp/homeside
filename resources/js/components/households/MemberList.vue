<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
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
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

/**
 * Contact summary of the member resolved from the viewer's address book.
 */
type MemberContact = {
    display_name: string;
    avatar_url: string | null;
    email: string | null;
    phone: string | null;
    labels: Array<{ id: string; name: string }>;
};

type Member = {
    id: string;
    role: string;
    joined_at: string;
    user: {
        id: string;
        name: string;
        email: string;
    };
    contact?: MemberContact | null;
};

defineProps<{
    members: Member[];
    currentUserId: string;
    householdId: string;
    isAdmin: boolean;
}>();

const { t } = useI18n();

function removeMember(householdId: string, memberId: string) {
    router.delete(`/households/${householdId}/members/${memberId}`);
}
</script>

<template>
    <div class="grid gap-3">
        <Card v-for="member in members" :key="member.id">
            <CardContent class="flex items-center justify-between py-4">
                <div class="flex items-center gap-3">
                    <Avatar class="size-10">
                        <AvatarImage
                            v-if="member.contact?.avatar_url"
                            :src="member.contact.avatar_url"
                            :alt="member.user.name"
                        />
                        <AvatarFallback>
                            {{ member.user.name.charAt(0).toUpperCase() }}
                        </AvatarFallback>
                    </Avatar>
                    <div>
                        <p class="text-sm font-medium">
                            {{ member.user.name }}
                            <span
                                v-if="
                                    member.contact &&
                                    member.contact.display_name &&
                                    member.contact.display_name !==
                                        member.user.name
                                "
                                class="text-xs font-normal text-muted-foreground"
                            >
                                · {{ member.contact.display_name }}
                            </span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ member.contact?.email ?? member.user.email }}
                        </p>
                        <p
                            v-if="member.contact?.phone"
                            class="text-xs text-muted-foreground"
                        >
                            {{ member.contact.phone }}
                        </p>
                        <div
                            v-if="member.contact?.labels.length"
                            class="mt-1 flex flex-wrap gap-1"
                        >
                            <Badge
                                v-for="label in member.contact.labels"
                                :key="label.id"
                                variant="outline"
                                class="px-1.5 py-0 text-[10px]"
                            >
                                {{ label.name }}
                            </Badge>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Badge
                        :variant="
                            member.role === 'admin' ? 'default' : 'secondary'
                        "
                    >
                        {{
                            member.role === 'admin'
                                ? t('households.members.admin')
                                : t('households.members.member')
                        }}
                    </Badge>
                    <AlertDialog
                        v-if="isAdmin || member.user.id === currentUserId"
                    >
                        <AlertDialogTrigger as-child>
                            <Button variant="ghost" size="icon" class="size-8">
                                <Trash2 class="size-4 text-muted-foreground" />
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>{{
                                    t('households.members.deleteTitle')
                                }}</AlertDialogTitle>
                                <AlertDialogDescription>
                                    {{
                                        t(
                                            'households.members.deleteDescription',
                                            { name: member.user.name },
                                        )
                                    }}
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>{{
                                    t('common.actions.cancel')
                                }}</AlertDialogCancel>
                                <AlertDialogAction
                                    @click="
                                        removeMember(householdId, member.id)
                                    "
                                >
                                    {{ t('common.actions.delete') }}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
