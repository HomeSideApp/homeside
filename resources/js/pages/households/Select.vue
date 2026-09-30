<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { House, Plus, X } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { getFallbackColor } from '@/lib/avatarFallbackColors';
import { create, switchMethod } from '@/routes/households';
import { accept, cancel } from '@/routes/households/invitations';

const page = usePage();
const household = computed(() => page.props.householdContext as any);
const households = computed(() => household.value?.households as any[] || []);
const pendingInvitations = computed(() => (page.props.pendingInvitations as any[]) || []);
const { t } = useI18n();

function getInitial(name: string): string {
    return name.charAt(0).toUpperCase();
}

function selectHousehold(householdId: string) {
    router.post(switchMethod(householdId).url);
}

function acceptInvitation(invitationId: string) {
    router.post(accept(invitationId).url);
}

function cancelInvitation(invitationId: string) {
    router.delete(cancel(invitationId).url);
}
</script>

<template>
    <Head :title="t('households.select.head')" />

    <div class="flex min-h-screen items-center justify-center bg-background p-4">
        <div class="w-full max-w-3xl space-y-8">
            <div class="text-center">
                <div class="mx-auto mb-4 flex items-center justify-center">
                    <AppLogoIcon class="size-32 object-contain" />
                </div>
                <h1 class="text-2xl font-bold">{{ t('households.select.welcome', { name: page.props.name }) }}</h1>
                <p class="mt-2 text-muted-foreground">
                    {{ t('households.select.description') }}
                </p>
            </div>

            <div v-if="households.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="h in households"
                    :key="h.id"
                    class="cursor-pointer overflow-hidden transition-colors hover:border-primary"
                    @click="selectHousehold(h.id)"
                >
                    <!-- Image or Color + Initial -->
                    <div v-if="h.image_url" class="aspect-video w-full overflow-hidden">
                        <img
                            :src="h.image_url"
                            :alt="h.name"
                            class="h-full w-full object-cover"
                        />
                    </div>
                    <div
                        v-else
                        class="flex aspect-video w-full items-center justify-center text-white text-5xl font-bold"
                        :style="{ backgroundColor: h.color || getFallbackColor(h.id) }"
                    >
                        {{ getInitial(h.name) }}
                    </div>
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center justify-between text-base">
                            <span>{{ h.name }}</span>
                            <Badge
                                :variant="h.members?.find((m: any) => m.user?.id === page.props.auth.user?.id)?.role === 'admin' ? 'default' : 'secondary'"
                                class="ml-2 shrink-0"
                            >
                                {{ h.members?.find((m: any) => m.user?.id === page.props.auth.user?.id)?.role === 'admin' ? t('households.select.administrator') : t('households.select.member') }}
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm text-muted-foreground">
                            {{ t('households.select.memberCount', h.members_count, { count: h.members_count }) }}
                        </p>
                    </CardContent>
                    <CardFooter>
                        <Button variant="outline" size="sm" class="w-full">
                            {{ t('households.select.select') }}
                        </Button>
                    </CardFooter>
                </Card>
            </div>

            <div v-if="pendingInvitations.length > 0" class="space-y-4">
                <h2 class="text-lg font-semibold text-muted-foreground">{{ t('households.select.pendingInvitations') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Card
                        v-for="invitation in pendingInvitations"
                        :key="invitation.id"
                        class="border-dashed"
                    >
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <House class="size-5" />
                                {{ invitation.household.name }}
                            </CardTitle>
                        </CardHeader>
                        <CardFooter class="flex gap-2">
                            <Button size="sm" @click="acceptInvitation(invitation.id)">
                                {{ t('households.select.acceptInvitation') }}
                            </Button>
                            <Button variant="outline" size="sm" @click="cancelInvitation(invitation.id)">
                                <X class="mr-1 size-4" />
                                {{ t('households.select.rejectInvitation') }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>

            <div class="text-center">
                <Button as-child variant="outline">
                    <Link :href="create().url">
                        <Plus class="mr-2 size-4" />
                        {{ t('households.select.create') }}
                    </Link>
                </Button>
            </div>
        </div>
    </div>
</template>
