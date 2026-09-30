<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

interface RecipeSection {
    client_id: string;
    id?: string;
    name: string;
    order: number | null;
}

const { t } = useI18n();

const props = defineProps<{
    sections: RecipeSection[];
    errors: Record<string, string>;
    validate: (field?: string) => void;
}>();

const sections = toRef(props, 'sections');

function generateClientId(): string {
    return crypto.randomUUID();
}

function addSection() {
    sections.value.push({
        client_id: generateClientId(),
        name: '',
        order: sections.value.length + 1,
    });
}

function removeSection(index: number) {
    sections.value.splice(index, 1);
}

function getSectionError(index: number, field: string): string | undefined {
    return props.errors[`sections.${index}.${field}`];
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium">Secciones</h3>
            <Button type="button" variant="outline" size="sm" @click="addSection">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('recipes.sectionEditor.add') }}
            </Button>
        </div>

        <div v-if="sections.length === 0" class="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
            No hay secciones. Puedes organizar ingredientes y pasos en secciones.
        </div>

        <div v-for="(section, index) in sections" :key="section.client_id" class="flex items-end gap-2">
            <Field class="flex-1">
                <FieldLabel :for="`section-name-${index}`">{{ t('recipes.sectionEditor.name') }}</FieldLabel>
                <Input
                    :id="`section-name-${index}`"
                    v-model="section.name"
                    placeholder="Ej: Para el relleno, Para la salsa..."
                    @blur="validate(`sections.${index}.name`)"
                    @input="validate(`sections.${index}.name`)"
                />
                <InputError :message="getSectionError(index, 'name')" />
            </Field>
            <Button type="button" variant="ghost" size="icon" class="h-9 w-9 text-destructive" @click="removeSection(index)">
                <Trash2 class="h-4 w-4" />
            </Button>
        </div>
    </div>
</template>
