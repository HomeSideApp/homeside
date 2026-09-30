<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    test as testUserAiProvider,
    testConfig as testUserAiProviderConfig,
} from '@/actions/App/Http/Controllers/Settings/UserAiProviderController';
import Heading from '@/components/Heading.vue';
import AiProvidersForm from '@/components/households/AiProvidersForm.vue';
import { index as userAiProvidersIndex } from '@/routes/settings/ai-providers';
import type {
    AiProvider,
    PaginatedData,
    CatalogProvider,
    CatalogModel,
    CatalogPrefillData,
} from '@/types';

const { t } = useI18n();

const props = defineProps<{
    providers: AiProvider[];
    catalogProviders?: PaginatedData<CatalogProvider> | null;
    catalogModels?: PaginatedData<CatalogModel> | null;
    catalogPrefill?: CatalogPrefillData | null;
}>();

const catalogSearchUrl = '/settings/ai-providers/catalog/search';

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t('settings.aiProviders.title'),
                href: userAiProvidersIndex(),
            },
        ],
        wide: true,
    });
});

function refreshProviders(): void {
    router.reload({ only: ['providers'] });
}

function providerTestUrl(providerId: string): string {
    return testUserAiProvider.url(providerId);
}
</script>

<template>
    <Head :title="t('settings.aiProviders.title')" />

    <div class="flex flex-col gap-6">
        <Heading
            variant="small"
            :title="t('settings.aiProviders.title')"
            :description="t('settings.aiProviders.description')"
        />

        <AiProvidersForm
            :base-url="userAiProvidersIndex.url()"
            :test-config-url="testUserAiProviderConfig.url()"
            :test-provider-url="providerTestUrl"
            :providers="providers"
            :title="t('settings.aiProviders.title')"
            :empty-text="t('settings.aiProviders.empty')"
            :catalog-providers="catalogProviders"
            :catalog-models="catalogModels"
            :catalog-prefill="catalogPrefill"
            :catalog-search-url="catalogSearchUrl"
            @refresh="refreshProviders"
        />
    </div>
</template>
