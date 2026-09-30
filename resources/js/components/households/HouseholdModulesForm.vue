<script setup lang="ts">
import { AlertCircle, ChefHat, Receipt, ShoppingCart } from '@lucide/vue';
import { computed, toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import type { HouseholdModule } from '@/types';

type WarningData = {
    module: string;
    module_label: string;
    missing_dependency: string;
    missing_dependency_label: string;
    message: string;
    severity: string;
    affected_features: string[];
};

const { t } = useI18n();

const props = defineProps<{
    form: {
        modules: Record<string, boolean>;
    };
    modules: HouseholdModule[];
}>();

const form = toRef(props, 'form');

const moduleIconMap: Record<string, any> = {
    ShoppingCart,
    ChefHat,
    Receipt,
};

function getModuleIcon(icon: string) {
    return moduleIconMap[icon] || ShoppingCart;
}

function toggleModule(module: string, value: boolean) {
    form.value.modules = { ...form.value.modules, [module]: value };
}

const currentWarnings = computed(() => {
    const modules = form.value.modules;

    const depMap: Record<string, Record<string, { warning: string; severity: string }>> = {
        recipes: {
            shopping_lists: {
                warning: 'households.modules.missingShoppingLists',
                severity: 'info',
            },
        },
        economy: {
            shopping_lists: {
                warning: 'households.modules.missingShoppingListsEconomy',
                severity: 'info',
            },
            recipes: {
                warning: 'households.modules.missingRecipes',
                severity: 'info',
            },
        },
    };

    const warnings: WarningData[] = [];

    for (const [moduleName, deps] of Object.entries(depMap)) {
        if (!modules[moduleName]) {
continue;
}

        for (const [depName, depInfo] of Object.entries(deps)) {
            if (!modules[depName]) {
                const moduleConfig = props.modules.find((m) => m.module === moduleName);
                const depConfig = props.modules.find((m) => m.module === depName);
                warnings.push({
                    module: moduleName,
                    module_label: moduleConfig?.label || moduleName,
                    missing_dependency: depName,
                    missing_dependency_label: depConfig?.label || depName,
                    message: depInfo.warning,
                    severity: depInfo.severity,
                    affected_features: [],
                });
            }
        }
    }

    return warnings;
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('households.modules.title') }}</CardTitle>
            <CardDescription>{{ t('households.modules.description') }}</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4">
            <div
                v-for="mod in modules"
                :key="mod.module"
                class="flex items-center justify-between rounded-lg border p-4"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-muted">
                        <component :is="getModuleIcon(mod.icon)" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="font-medium">{{ t(mod.label) }}</p>
                        <p class="text-sm text-muted-foreground">{{ t(mod.description) }}</p>
                    </div>
                </div>
                <Switch
                    :checked="form.modules[mod.module]"
                    @update:checked="(val: boolean) => toggleModule(mod.module, val)"
                />
            </div>

            <!-- Warnings when toggling modules -->
            <Alert v-if="currentWarnings.length > 0" class="border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    <div class="font-medium">{{ t('households.settings.dependencyWarnings') }}</div>
                    <ul class="mt-1 list-inside list-disc text-sm">
                        <li v-for="(warning, i) in currentWarnings" :key="i">
                            {{ t(warning.message) }}
                        </li>
                    </ul>
                </AlertDescription>
            </Alert>
        </CardContent>
    </Card>
</template>
