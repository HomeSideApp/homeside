<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useAuthorization } from '@/composables/useAuthorization';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useHousehold } from '@/composables/useHousehold';
import type { NavItem } from '@/types';

const props = defineProps<{
    item: NavItem;
    depth?: number;
}>();

const { isCurrentUrl } = useCurrentUrl();
const { canAny } = useAuthorization();
const { enabledModules } = useHousehold();
const { state, isMobile } = useSidebar();

const depth = props.depth ?? 0;

/**
 * En modo icono el submenú (`SidebarMenuSub`) queda oculto por CSS dentro del
 * `overflow-hidden` del sidebar, así que se usa un DropdownMenu teletransportado
 * (igual que los menús de usuario y hogar) para poder navegar.
 */
const isCollapsedFlyout = computed(
    () => !isMobile.value && state.value === 'collapsed',
);

function hasPermission(item: NavItem): boolean {
    if (!item.permission) {
        return true;
    }

    const permissions = Array.isArray(item.permission)
        ? item.permission
        : [item.permission];

    return canAny(...permissions);
}

function hasModule(item: NavItem): boolean {
    if (!item.module) {
        return true;
    }

    return enabledModules.value.includes(item.module);
}

function filteredChildren(children: NavItem[]): NavItem[] {
    return children.filter((child) => hasPermission(child) && hasModule(child));
}

function hasVisibleChildren(item: NavItem): boolean {
    return !!item.children && filteredChildren(item.children).length > 0;
}

function isChildActive(children: NavItem[]): boolean {
    return children.some((child) => {
        if (isCurrentUrl(child.href)) {
            return true;
        }

        if (child.children) {
            return isChildActive(child.children);
        }

        return false;
    });
}

function isActive(item: NavItem): boolean {
    if (isCurrentUrl(item.href)) {
        return true;
    }

    if (item.children) {
        return isChildActive(item.children);
    }

    return false;
}

const hasChildren = computed(
    () => !!props.item.children && props.item.children.length > 0,
);
</script>

<template>
    <!-- Item con hijos en modo icono: flyout teletransportado -->
    <SidebarMenuItem v-if="hasChildren && isCollapsedFlyout">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <SidebarMenuButton
                    :is-active="isActive(item)"
                    class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                >
                    <component :is="item.icon" v-if="item.icon" />
                    <span>{{ item.title }}</span>
                </SidebarMenuButton>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                class="min-w-48 rounded-lg"
                side="right"
                align="start"
                :side-offset="8"
            >
                <DropdownMenuLabel>{{ item.title }}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <template
                        v-for="child in filteredChildren(item.children!)"
                        :key="child.title"
                    >
                        <!-- Sub-item con nietos -->
                        <DropdownMenuSub v-if="hasVisibleChildren(child)">
                            <DropdownMenuSubTrigger>
                                <component :is="child.icon" v-if="child.icon" />
                                <span>{{ child.title }}</span>
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent class="min-w-44 rounded-lg">
                                <DropdownMenuGroup>
                                    <DropdownMenuItem
                                        v-for="grandchild in filteredChildren(
                                            child.children!,
                                        )"
                                        :key="grandchild.title"
                                        as-child
                                    >
                                        <Link
                                            :href="grandchild.href"
                                            class="flex w-full items-center gap-2"
                                            :class="{
                                                'font-medium': isCurrentUrl(
                                                    grandchild.href,
                                                ),
                                            }"
                                        >
                                            <component
                                                :is="grandchild.icon"
                                                v-if="grandchild.icon"
                                            />
                                            <span>{{ grandchild.title }}</span>
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                        <!-- Sub-item hoja -->
                        <DropdownMenuItem v-else as-child>
                            <Link
                                :href="child.href"
                                class="flex w-full items-center gap-2"
                                :class="{
                                    'font-medium': isCurrentUrl(child.href),
                                }"
                            >
                                <component :is="child.icon" v-if="child.icon" />
                                <span>{{ child.title }}</span>
                            </Link>
                        </DropdownMenuItem>
                    </template>
                </DropdownMenuGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    </SidebarMenuItem>

    <!-- Item con hijos (submenu colapsable) -->
    <Collapsible
        v-else-if="hasChildren"
        :default-open="isActive(item)"
        class="group/collapsible"
    >
        <SidebarMenuItem>
            <CollapsibleTrigger as-child>
                <SidebarMenuButton
                    :is-active="isActive(item)"
                    :tooltip="item.title"
                >
                    <component :is="item.icon" v-if="item.icon" />
                    <span>{{ item.title }}</span>
                    <ChevronDown
                        v-if="depth < 1"
                        class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-180"
                    />
                </SidebarMenuButton>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <SidebarMenuSub>
                    <template
                        v-for="child in filteredChildren(item.children!)"
                        :key="child.title"
                    >
                        <!-- Sub-item con nietos -->
                        <NavMenuItem
                            v-if="child.children && child.children.length > 0"
                            :item="child"
                            :depth="depth + 1"
                        />
                        <!-- Sub-item hoja -->
                        <SidebarMenuSubItem v-else>
                            <SidebarMenuSubButton
                                as-child
                                :is-active="isCurrentUrl(child.href)"
                            >
                                <Link :href="child.href">
                                    <component
                                        :is="child.icon"
                                        v-if="child.icon"
                                    />
                                    <span>{{ child.title }}</span>
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    </template>
                </SidebarMenuSub>
            </CollapsibleContent>
        </SidebarMenuItem>
    </Collapsible>

    <!-- Item sin hijos -->
    <SidebarMenuItem v-else>
        <SidebarMenuButton
            as-child
            :is-active="isCurrentUrl(item.href)"
            :tooltip="item.title"
        >
            <Link :href="item.href">
                <component :is="item.icon" v-if="item.icon" />
                <span>{{ item.title }}</span>
            </Link>
        </SidebarMenuButton>
    </SidebarMenuItem>
</template>
