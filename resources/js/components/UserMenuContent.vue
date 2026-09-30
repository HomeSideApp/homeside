<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { BookOpenText, House, LogOut, Settings } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { index as householdsIndex } from '@/routes/households';
import { edit } from '@/routes/profile';
import { ui as apiDocumentation } from '@/routes/scramble/docs';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

const { t } = useI18n();

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                {{ t('settings.title') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-if="user.households_enabled"
            v-can="'households.index'"
            :as-child="true"
        >
            <Link
                class="block w-full cursor-pointer"
                :href="householdsIndex()"
                prefetch
                data-test="user-menu-households"
            >
                <House class="mr-2 h-4 w-4" />
                {{ t('nav.households') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-can="'scramble.docs.ui'" :as-child="true">
            <a
                class="block w-full cursor-pointer"
                :href="apiDocumentation.url()"
                target="_blank"
                rel="noopener noreferrer"
            >
                <BookOpenText class="mr-2 h-4 w-4" />
                {{ t('common.actions.apiDocumentation') }}
            </a>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            {{ t('common.actions.logout') }}
        </Link>
    </DropdownMenuItem>
</template>
