<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, House, Mail, Plus, Settings } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { useHousehold } from '@/composables/useHousehold';

const { t } = useI18n();
const { activeHousehold, households, switchHousehold } = useHousehold();
const { isMobile, state } = useSidebar();
const page = usePage();
const pendingInvitations = computed(() => (page.props.pendingInvitations as any[]) || []);
</script>

<template>
    <SidebarMenu v-if="activeHousehold">
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="household-switcher-button"
                    >
                        <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <House class="size-4" />
                        </div>
                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-semibold">
                                {{ activeHousehold.name }}
                            </span>
                            <span class="truncate text-xs text-muted-foreground">
                                {{ t('households.switcher.membersCount', { count: activeHousehold.members_count }) }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'bottom'"
                    align="end"
                    :side-offset="4"
                >
                    <div class="px-2 py-1.5 text-sm font-medium text-muted-foreground">
                        {{ t('households.switcher.myHouseholds') }}
                    </div>
                    <DropdownMenuItem
                        v-for="household in households"
                        :key="household.id"
                        class="flex items-center gap-2"
                        @click="switchHousehold(household.id)"
                    >
                        <Check v-if="household.id === activeHousehold.id" class="size-4" />
                        <House v-else class="size-4" />
                        <div class="flex flex-1 items-center justify-between">
                            <span>{{ household.name }}</span>
                            <Badge variant="outline" class="ml-2 text-xs">
                                {{ t('households.switcher.membersCount', { count: household.members_count }) }}
                            </Badge>
                        </div>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator v-if="pendingInvitations.length > 0" />
                    <DropdownMenuItem
                        v-for="invitation in pendingInvitations"
                        :key="invitation.id"
                        class="flex items-center gap-2"
                    >
                        <Mail class="size-4 text-muted-foreground" />
                        <div class="flex flex-1 items-center justify-between">
                            <span class="truncate">{{ invitation.household.name }}</span>
                            <Badge variant="secondary" class="ml-2 text-xs">
                                {{ t('households.switcher.pending') }}
                            </Badge>
                        </div>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link href="/households" class="flex items-center gap-2">
                            <Settings class="size-4" />
                            <span>{{ t('households.switcher.manage') }}</span>
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-can="'households.create'" as-child>
                        <Link href="/households/create" class="flex items-center gap-2">
                            <Plus class="size-4" />
                            <span>{{ t('households.switcher.create') }}</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
