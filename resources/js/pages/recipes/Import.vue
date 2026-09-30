<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { Globe, FileText, ArrowLeft } from '@lucide/vue';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    previewCooklang,
    previewJsonLd,
    review,
} from '@/actions/App/Http/Controllers/Web/RecipeImportController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RecipeImportPreview from '@/components/recipes/RecipeImportPreview.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { vCan } from '@/directives/can';
import { index as recipesIndex } from '@/routes/recipes';

const { t } = useI18n();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Recetas', href: '/recipes' },
            { title: 'Importar', href: '#' },
        ],
    },
});

const importUrl = ref('');
const importUrlError = ref('');
const importUrlLoading = ref(false);

const importCooklang = ref('');
const importCooklangFile = ref<File | null>(null);
const importCooklangError = ref('');
const importCooklangLoading = ref(false);

const previewData = ref<any>(null);
const previewWarnings = ref<
    Array<{ code: string; message: string; level: string } | string>
>([]);
const previewMatches = ref<
    Array<
        | {
              ingredient: string;
              status: 'MATCHED' | 'SUGGESTED';
              product_id: string | null;
              product_name: string | null;
              score: number;
          }
        | string
    >
>([]);
type PreviewResponse = {
    data?: {
        recipe: any;
        warnings?: Array<
            { code: string; message: string; level: string } | string
        >;
        matches?: Array<
            | {
                  ingredient: string;
                  status: 'MATCHED' | 'SUGGESTED';
                  product_id: string | null;
                  product_name: string | null;
                  score: number;
              }
            | string
        >;
    };
    error?: string;
    errors?: Record<string, string[]>;
};
const jsonLdPreview = useHttp<{ url: string }, PreviewResponse>(
    previewJsonLd(),
    { url: '' },
);
const cooklangPreview = useHttp<{ cooklang: string }, PreviewResponse>(
    previewCooklang(),
    { cooklang: '' },
);

function applyPreview(result: PreviewResponse): boolean {
    if (!result.data?.recipe) {
        return false;
    }

    previewData.value = result.data.recipe;
    previewWarnings.value = result.data.warnings ?? [];
    previewMatches.value = result.data.matches ?? [];

    return true;
}

async function handleJsonLdImport() {
    if (!importUrl.value) {
        importUrlError.value = t('common.validation.invalidUrl');

        return;
    }

    importUrlLoading.value = true;
    importUrlError.value = '';

    try {
        jsonLdPreview.url = importUrl.value;
        const result = await jsonLdPreview.submit({
            onHttpException: (response) => {
                const data =
                    typeof response.data === 'string'
                        ? (JSON.parse(response.data) as PreviewResponse)
                        : (response.data as PreviewResponse);
                importUrlError.value =
                    data.errors?.url?.[0] ??
                    data.error ??
                    t('recipes.import.importFailed');
            },
        });

        if (!applyPreview(result)) {
            importUrlError.value =
                result.error ?? t('recipes.import.importFailed');
        }
    } catch {
        if (!importUrlError.value) {
            importUrlError.value = t('common.validation.connectionError');
        }
    } finally {
        importUrlLoading.value = false;
    }
}

function handleCooklangFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;

    if (file) {
        importCooklangFile.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            importCooklang.value = (e.target?.result as string) ?? '';
        };
        reader.readAsText(file);
    }
}

async function handleCooklangImport() {
    if (!importCooklang.value) {
        importCooklangError.value = t('recipes.import.cooklangFailed');

        return;
    }

    importCooklangLoading.value = true;
    importCooklangError.value = '';

    try {
        cooklangPreview.cooklang = importCooklang.value;
        const result = await cooklangPreview.submit({
            onHttpException: () => {
                importCooklangError.value = t('recipes.import.cooklangFailed');
            },
        });

        if (!applyPreview(result)) {
            importCooklangError.value = t('recipes.import.cooklangFailed');
        }
    } catch {
        if (!importCooklangError.value) {
            importCooklangError.value = t('common.validation.connectionError');
        }
    } finally {
        importCooklangLoading.value = false;
    }
}

function handleConfirmImport() {
    if (!previewData.value) {
        return;
    }

    // Apply matched product_id from candidates to ingredients
    const recipe = { ...previewData.value };

    if (previewMatches.value.length > 0) {
        const matchMap = new Map<string, string>();

        for (const m of previewMatches.value) {
            if (
                typeof m === 'object' &&
                'ingredient' in m &&
                'product_id' in m &&
                m.product_id
            ) {
                matchMap.set(m.ingredient, String(m.product_id));
            }
        }

        if (matchMap.size > 0) {
            recipe.ingredients = recipe.ingredients.map((ing: any) => ({
                ...ing,
                product_id: matchMap.get(ing.name) ?? ing.product_id,
            }));
        }
    }

    router.post(review.url(), {
        recipe: JSON.stringify(recipe),
    });
}

function handleCancelPreview() {
    previewData.value = null;
    previewWarnings.value = [];
    previewMatches.value = [];
}
</script>

<template>
    <Head :title="t('recipes.import.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex items-center gap-4">
            <Button variant="ghost" size="icon" as-child>
                <Link :href="recipesIndex.url()">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
            </Button>
            <Heading
                variant="small"
                :title="t('recipes.import.title')"
                :description="t('recipes.import.description')"
            />
        </div>

        <!-- Preview area -->
        <RecipeImportPreview
            v-if="previewData"
            :recipe="previewData"
            :warnings="previewWarnings"
            :matches="previewMatches"
            @confirm="handleConfirmImport"
            @cancel="handleCancelPreview"
        />

        <!-- Import forms -->
        <Tabs v-else default-value="url">
            <TabsList>
                <TabsTrigger value="url" class="gap-2">
                    <Globe class="h-4 w-4" />
                    {{ t('recipes.import.fromUrl') }}
                </TabsTrigger>
                <TabsTrigger value="cooklang" class="gap-2">
                    <FileText class="h-4 w-4" />
                    {{ t('recipes.import.cooklang') }}
                </TabsTrigger>
            </TabsList>

            <!-- JSON-LD / URL Import -->
            <TabsContent value="url">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            t('recipes.import.importFromUrl')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="mb-4 text-sm text-muted-foreground">
                            {{ t('recipes.import.importUrlDescription') }}
                        </p>
                        <FieldGroup>
                            <Field>
                                <FieldLabel for="import-url">{{
                                    t('recipes.import.urlLabel')
                                }}</FieldLabel>
                                <div class="flex gap-2">
                                    <Input
                                        id="import-url"
                                        v-model="importUrl"
                                        type="url"
                                        :placeholder="
                                            t('recipes.import.urlPlaceholder')
                                        "
                                        class="flex-1"
                                    />
                                    <Button
                                        v-can="'recipes.import'"
                                        :disabled="importUrlLoading"
                                        @click="handleJsonLdImport"
                                    >
                                        {{
                                            importUrlLoading
                                                ? t('recipes.import.analyzing')
                                                : t('recipes.import.analyze')
                                        }}
                                    </Button>
                                </div>
                                <InputError :message="importUrlError" />
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- CookLang Import -->
            <TabsContent value="cooklang">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">{{
                            t('recipes.import.importCooklang')
                        }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="mb-4 text-sm text-muted-foreground">
                            {{
                                t('recipes.import.cooklangDescription', {
                                    code: '.cook',
                                })
                            }}
                        </p>
                        <FieldGroup class="space-y-4">
                            <Field>
                                <FieldLabel for="cooklang-file">{{
                                    t('recipes.import.uploadFile')
                                }}</FieldLabel>
                                <Input
                                    id="cooklang-file"
                                    type="file"
                                    accept=".cook,.cooklang,.txt"
                                    @change="handleCooklangFileChange"
                                />
                            </Field>
                            <Field>
                                <FieldLabel for="cooklang-code">{{
                                    t('recipes.import.pasteCode')
                                }}</FieldLabel>
                                <Textarea
                                    id="cooklang-code"
                                    v-model="importCooklang"
                                    :placeholder="
                                        t('recipes.import.cooklangPlaceholder')
                                    "
                                    rows="10"
                                    class="font-mono text-sm"
                                />
                                <InputError :message="importCooklangError" />
                            </Field>
                            <div>
                                <Button
                                    v-can="'recipes.import'"
                                    :disabled="importCooklangLoading"
                                    @click="handleCooklangImport"
                                >
                                    {{
                                        importCooklangLoading
                                            ? 'Analizando...'
                                            : 'Analizar'
                                    }}
                                </Button>
                            </div>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>
</template>
