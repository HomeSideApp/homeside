<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as userAiProvidersIndex } from '@/routes/settings/ai-providers';
import type { NavItem } from '@/types';

const { wide = false } = defineProps<{
    /** When enabled, the settings content is not constrained to a reading width. */
    wide?: boolean;
}>();

const { t } = useI18n();
const sidebarNavItems = computed<NavItem[]>(() => [
    { title: t('settings.navigation.profile'), href: editProfile() },
    { title: t('settings.navigation.security'), href: editSecurity() },
    {
        title: t('settings.navigation.aiProviders'),
        href: userAiProvidersIndex(),
    },
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="t('settings.title')"
            :description="t('settings.description')"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    :aria-label="t('settings.title')"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div :class="wide ? 'min-w-0 flex-1' : 'flex-1 md:max-w-2xl'">
                <section :class="wide ? 'space-y-12' : 'max-w-xl space-y-12'">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
