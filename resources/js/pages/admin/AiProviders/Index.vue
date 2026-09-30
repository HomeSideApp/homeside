<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    test as testAdminAiProvider,
    testConfig as testAdminAiProviderConfig,
} from '@/actions/App/Http/Controllers/Admin/AdminAiProviderController';
import GlobalPromptForm from '@/components/admin/GlobalPromptForm.vue';
import Heading from '@/components/Heading.vue';
import AiProvidersForm from '@/components/households/AiProvidersForm.vue';
import { aiProviders as adminAiProvidersIndex } from '@/routes/admin';
import type { AiProvider, PaginatedData, CatalogProvider, CatalogModel, CatalogPrefillData } from '@/types';

type GlobalSetting = {
    id?: string;
    module: string | null;
    extra_prompt: string;
};

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'admin.aiProviders.title', href: '/admin/ai-providers' },
        ],
    },
});

const props = defineProps<{
    providers: AiProvider[];
    global_settings: GlobalSetting[];
    available_modules: Array<{
        value: string;
        label: string;
        agents: Array<{
            name: string;
            label: string;
            description: string | null;
        }>;
    }>;
    catalogProviders?: PaginatedData<CatalogProvider> | null;
    catalogModels?: PaginatedData<CatalogModel> | null;
    catalogPrefill?: CatalogPrefillData | null;
}>();

const catalogSearchUrl = '/admin/ai-providers/catalog/search';

function refreshProviders() {
    router.reload({ only: ['providers'] });
}

function providerTestUrl(providerId: string): string {
    return testAdminAiProvider.url(providerId);
}
</script>

<template>
    <Head :title="t('admin.aiProviders.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('admin.aiProviders.title')"
            :description="t('admin.aiProviders.description')"
        />

        <!-- ===== Sección Proveedores Globales ===== -->
        <AiProvidersForm
            :base-url="adminAiProvidersIndex.url()"
            :test-config-url="testAdminAiProviderConfig.url()"
            :test-provider-url="providerTestUrl"
            :providers="providers"
            :title="t('admin.aiProviders.providersTitle')"
            :empty-text="t('admin.aiProviders.empty')"
            :show-global-badge="true"
            :catalog-providers="catalogProviders"
            :catalog-models="catalogModels"
            :catalog-prefill="catalogPrefill"
            :catalog-search-url="catalogSearchUrl"
            @refresh="refreshProviders"
        />

        <!-- ===== Sección Prompts Globales ===== -->
        <GlobalPromptForm :global-settings="global_settings" />
    </div>
</template>
