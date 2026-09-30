<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import { ArrowRight, Loader2 } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Heading from '@/components/Heading.vue';
import HouseholdModulesForm from '@/components/households/HouseholdModulesForm.vue';
import HouseholdSettingsForm from '@/components/households/HouseholdSettingsForm.vue';
import HouseholdTagsForm from '@/components/households/HouseholdTagsForm.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { HouseholdModule, HouseholdTag } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Hogares', href: '/households' }, { title: 'Crear hogar', href: '/households/create' }] },
});

const { t } = useI18n();

const props = defineProps<{
    modules: HouseholdModule[];
    tags: HouseholdTag[];
    available_tags: HouseholdTag[];
}>();

const form = useForm({
    name: '',
    description: '',
    color: '',
    image: null as File | null,
    tags: [] as string[],
    modules: Object.fromEntries(props.modules.map((m) => [m.module, m.enabled])) as Record<string, boolean>,
});

const imagePreview = ref<string | null>(null);
const imageRemoved = ref(false);

function submit() {
    form.transform((data) => {
        const payload: Record<string, any> = {
            name: data.name,
            description: data.description,
            color: data.color || null,
            modules: data.modules,
            tags: data.tags,
        };

        if (data.image) {
            payload.image = data.image;
        } else if (imageRemoved.value) {
            payload.remove_image = true;
        }

        return payload;
    });

    form.post('/households', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('households.create.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <Heading
            variant="small"
            :title="t('households.create.title')"
            :description="t('households.create.description')"
        />

        <!-- Step indicator -->
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground">
                    1
                </div>
                <span class="text-sm font-medium text-foreground">
                    {{ t('households.create.stepConfig') }}
                </span>
            </div>
            <div class="h-px flex-1 bg-border" />
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-sm font-medium text-muted-foreground">
                    2
                </div>
                <span class="text-sm font-medium text-muted-foreground">
                    {{ t('households.create.stepAI') }}
                </span>
            </div>
        </div>

        <!-- Step 1: Create Household + Configuration -->
        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('households.create.newHousehold') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <Field :label="t('households.create.householdName')" :error="form.errors?.name">
                        <Input
                            v-model="form.name"
                            :placeholder="t('households.create.householdNamePlaceholder')"
                            :class="{ 'border-destructive': form.errors?.name }"
                        />
                    </Field>
                </CardContent>
            </Card>

            <HouseholdSettingsForm
                :form="form"
                :image-preview="imagePreview"
                :image-removed="imageRemoved"
                :show-name="false"
                @update:image-preview="(val) => imagePreview = val"
                @update:image-removed="(val) => imageRemoved = val"
            />

            <HouseholdModulesForm
                :form="form"
                :modules="modules"
            />

            <HouseholdTagsForm
                :form="form"
                :available-tags="available_tags"
            />

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Crear hogar y continuar
                    <ArrowRight v-if="!form.processing" class="ml-2 h-4 w-4" />
                </Button>
            </div>
        </form>
    </div>
</template>
