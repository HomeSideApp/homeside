<script setup lang="ts">
import { computed } from 'vue';
import NavMenuItem from '@/components/NavMenuItem.vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
} from '@/components/ui/sidebar';
import { useAuthorization } from '@/composables/useAuthorization';
import { useHousehold } from '@/composables/useHousehold';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { canAny } = useAuthorization();
const { enabledModules } = useHousehold();

const filteredItems = computed(() =>
    props.items.filter((item) => {
        // Filter by module if specified
        if (item.module && !enabledModules.value.includes(item.module)) {
            return false;
        }

        if (!item.permission) {
            return true;
        }

        const permissions = Array.isArray(item.permission)
            ? item.permission
            : [item.permission];

        return canAny(...permissions);
    })
);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <NavMenuItem
                v-for="item in filteredItems"
                :key="item.title"
                :item="item"
            />
        </SidebarMenu>
    </SidebarGroup>
</template>
