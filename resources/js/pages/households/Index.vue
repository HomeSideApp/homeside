<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getFallbackColor } from '@/lib/avatarFallbackColors';

const { t } = useI18n();

defineProps<{
    households: Array<{
        id: string;
        name: string;
        color: string | null;
        image_url: string | null;
        members_count: number;
        invite_code?: string;
        created_at: string;
    }>;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Hogares', href: '/households' }] },
});

function enterHousehold(householdId: string) {
    router.post(`/households/switch/${householdId}`);
}

function getInitial(name: string): string {
    return name.charAt(0).toUpperCase();
}
</script>

<template>
    <Head :title="t('households.index.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center justify-between">
            <Heading variant="small" :title="t('households.index.title')" :description="t('households.index.description')" />
            <Button v-can="'households.create'" as-child>
                <Link href="/households/create">
                    <Plus class="mr-2 size-4" />
                    {{ t('households.index.newHousehold') }}
                </Link>
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="h in households" :key="h.id" class="overflow-hidden">
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
                        <Badge variant="secondary" class="ml-2 shrink-0">
                            {{ t('households.index.membersCount', { count: h.members_count }) }}
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" class="flex-1" @click="enterHousehold(h.id)">
                            {{ t('households.index.enter') }}
                        </Button>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="`/households/${h.id}`">{{ t('households.index.details') }}</Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
