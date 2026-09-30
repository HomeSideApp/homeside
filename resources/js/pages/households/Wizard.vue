<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    test as testHouseholdAiProvider,
    testConfig as testHouseholdAiProviderConfig,
} from '@/actions/App/Http/Controllers/Web/AiProviderController';
import Heading from '@/components/Heading.vue';
import AiProvidersForm from '@/components/households/AiProvidersForm.vue';
import { Button } from '@/components/ui/button';
import { index as householdAiProvidersIndex } from '@/routes/households/ai-providers';
import type { AiProvider, HouseholdSummary, PaginatedData, CatalogProvider, CatalogModel, CatalogPrefillData } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    household: HouseholdSummary;
    modules: any[];
    tags: any[];
    available_tags: any[];
    providers: AiProvider[];
    catalogProviders?: PaginatedData<CatalogProvider> | null;
    catalogModels?: PaginatedData<CatalogModel> | null;
    catalogPrefill?: CatalogPrefillData | null;
}>();

const catalogSearchUrl = `/households/${props.household.id}/wizard/catalog/search`;

const processing = ref(false);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            { title: 'nav.households', href: '/households' },
            {
                title: props.household.name,
                href: `/households/${props.household.id}`,
            },
            { title: 'households.wizard.title', href: '#' },
        ],
    });
});

function refreshProviders() {
    router.reload({ only: ['providers'] });
}

function providerTestUrl(providerId: string): string {
    return testHouseholdAiProvider.url({
        household: props.household.id,
        provider: providerId,
    });
}

function complete() {
    processing.value = true;
    router.visit(`/households/${props.household.id}/lists`);
}
</script>

<template>
    <Head :title="t('households.wizard.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('households.wizard.title')"
            :description="t('households.wizard.description')"
        />

        <!-- Step indicator -->
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground"
                >
                    <Check class="h-4 w-4" />
                </div>
                <span class="text-sm font-medium text-foreground">
                    {{ t('households.create.stepConfig') }}
                </span>
            </div>
            <div class="h-px flex-1 bg-primary" />
            <div class="flex items-center gap-2">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground"
                >
                    2
                </div>
                <span class="text-sm font-medium text-foreground">
                    {{ t('households.create.stepAI') }}
                </span>
            </div>
        </div>

        <!-- Step 2: AI Providers -->
        <AiProvidersForm
            :base-url="householdAiProvidersIndex.url(household.id)"
            :test-config-url="testHouseholdAiProviderConfig.url(household.id)"
            :test-provider-url="providerTestUrl"
            :providers="providers"
            :catalog-providers="catalogProviders"
            :catalog-models="catalogModels"
            :catalog-prefill="catalogPrefill"
            :catalog-search-url="catalogSearchUrl"
            @refresh="refreshProviders"
        />

        <div class="flex justify-end gap-3">
            <Button
                type="button"
                variant="outline"
                @click="complete"
                :disabled="processing"
            >
                {{ t('households.wizard.skip') }}
            </Button>
            <Button type="button" @click="complete" :disabled="processing">
                <Check class="mr-2 h-4 w-4" />
                {{ t('households.wizard.finish') }}
            </Button>
        </div>
    </div>
</template>
