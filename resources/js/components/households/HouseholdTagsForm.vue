<script setup lang="ts">
import { Check, Tag, X } from '@lucide/vue';
import { ref, toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { HouseholdTag } from '@/types';

const { t } = useI18n();

const props = defineProps<{
    form: {
        tags: string[];
    };
    availableTags: HouseholdTag[];
}>();

const form = toRef(props, 'form');

const newTag = ref('');

function addTag(tagName: string) {
    const trimmed = tagName.trim();

    if (trimmed && !form.value.tags.includes(trimmed)) {
        form.value.tags.push(trimmed);
    }

    newTag.value = '';
}

function removeTag(index: number) {
    form.value.tags.splice(index, 1);
}

function handleTagKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        event.preventDefault();
        addTag(newTag.value);
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ t('households.tags.title') }}</CardTitle>
            <CardDescription>{{ t('households.tags.description') }}</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4">
            <!-- Assigned tags -->
            <div v-if="form.tags.length > 0" class="flex flex-wrap gap-2">
                <Badge
                    v-for="(tag, index) in form.tags"
                    :key="tag"
                    variant="secondary"
                    class="gap-1 pr-1"
                >
                    <Tag class="h-3 w-3" />
                    {{ tag }}
                    <button
                        type="button"
                        @click="removeTag(index)"
                        class="ml-1 rounded-full p-0.5 hover:bg-muted"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </Badge>
            </div>

            <div v-else class="text-sm text-muted-foreground">
                {{ t('households.tags.empty') }}
            </div>

            <Separator />

            <!-- Available predefined tags -->
            <div v-if="availableTags.length > 0">
                <Label class="mb-2 block">{{ t('households.tags.available') }}</Label>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="tag in availableTags"
                        :key="tag.id"
                        variant="outline"
                        class="cursor-pointer hover:bg-muted"
                        @click="addTag(tag.name)"
                    >
                        <Check class="mr-1 h-3 w-3" />
                        {{ tag.name }}
                    </Badge>
                </div>
            </div>

            <!-- Custom tag input -->
            <div>
                <Label for="new-tag" class="mb-2 block">{{ t('households.tags.create') }}</Label>
                <div class="flex gap-2">
                    <Input
                        id="new-tag"
                        v-model="newTag"
                        :placeholder="t('households.tags.placeholder')"
                        @keydown="handleTagKeydown"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        @click="addTag(newTag)"
                        :disabled="!newTag.trim()"
                    >
                        {{ t('households.tags.add') }}
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
