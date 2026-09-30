<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ArrowLeft, BookOpen, Folder, FolderPlus, Pencil, Trash2 } from '@lucide/vue'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Heading from '@/components/Heading.vue'
import InputError from '@/components/InputError.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { index as recipesIndex, show as recipeShow } from '@/routes/recipes'
import { destroy, store, update } from '@/routes/recipes/collections'

interface RecipeCollection {
    id: string
    parent_id: string | null
    name: string
    path: string
    recipes_count: number
    recipes: Array<{ id: string; name: string }>
}

const { t } = useI18n();

defineProps<{ collections: RecipeCollection[] }>()

const createOpen = ref(false)
const editing = ref<RecipeCollection | null>(null)
const deleting = ref<RecipeCollection | null>(null)
const createForm = useForm({ name: '', parent_id: null as string | null })
const editForm = useForm({ name: '' })

defineOptions({
    layout: { breadcrumbs: [{ title: 'Recetas', href: '/recipes' }, { title: 'Colecciones', href: '#' }] },
})

function openCreate(parentId: string | null = null) {
    createForm.reset()
    createForm.parent_id = parentId
    createForm.clearErrors()
    createOpen.value = true
}

function createCollection() {
    createForm.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
 createOpen.value = false 
},
    })
}

function openEdit(collection: RecipeCollection) {
    editing.value = collection
    editForm.name = collection.name
    editForm.clearErrors()
}

function renameCollection() {
    if (!editing.value) {
return
}

    editForm.put(update(editing.value).url, {
        preserveScroll: true,
        onSuccess: () => {
 editing.value = null 
},
    })
}

function deleteCollection() {
    if (!deleting.value) {
return
}

    router.delete(destroy(deleting.value).url, {
        preserveScroll: true,
        onSuccess: () => {
 deleting.value = null 
},
    })
}

function depth(collection: RecipeCollection): number {
    return collection.path.split('/').length - 1
}
</script>

<template>
    <Head :title="t('recipes.collections.title')" />

    <div class="flex flex-col gap-6 px-8 py-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Heading variant="small" :title="t('recipes.collections.title')" :description="t('recipes.collections.description')" />
            <TooltipProvider>
                <div class="flex shrink-0 flex-nowrap items-center gap-2">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="outline" size="icon-sm" as-child>
                                <Link :href="recipesIndex().url" :aria-label="t('recipes.collections.backToRecipes')">
                                    <ArrowLeft aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('recipes.collections.backToRecipes') }}</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button size="icon-sm" :aria-label="t('recipes.collections.newCollection')" @click="openCreate()">
                                <FolderPlus aria-hidden="true" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>{{ t('recipes.collections.newCollection') }}</TooltipContent>
                    </Tooltip>
                </div>
            </TooltipProvider>
        </div>

        <Card>
            <CardHeader><CardTitle class="text-base">{{ t('recipes.collections.folders') }}</CardTitle></CardHeader>
            <CardContent>
                <div v-if="collections.length === 0" class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {{ t('recipes.collections.empty') }}
                </div>
                <div v-else class="divide-y rounded-md border">
                    <div v-for="collection in collections" :key="collection.id" class="flex flex-col gap-2 p-3">
                        <div class="flex items-center gap-3">
                            <div class="flex min-w-0 flex-1 items-center gap-2" :style="{ paddingLeft: `${depth(collection) * 24}px` }">
                                <Folder class="h-4 w-4 shrink-0 text-primary" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ collection.name }}</p>
                                    <p class="truncate text-xs text-muted-foreground">{{ collection.path }} · {{ t('recipes.collections.recipeCount', { count: collection.recipes_count }) }}</p>
                                </div>
                            </div>
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button variant="ghost" size="icon-sm" :aria-label="`${t('recipes.collections.subfolder')}: ${collection.name}`" @click="openCreate(collection.id)">
                                            <FolderPlus aria-hidden="true" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{ t('recipes.collections.subfolder') }}</TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button variant="ghost" size="icon-sm" :aria-label="`${t('common.actions.edit')}: ${collection.name}`" @click="openEdit(collection)">
                                            <Pencil aria-hidden="true" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{ t('common.actions.edit') }}</TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button variant="ghost" size="icon-sm" :aria-label="`${t('common.actions.delete')}: ${collection.name}`" @click="deleting = collection">
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>{{ t('common.actions.delete') }}</TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                        <div
                            v-if="collection.recipes.length > 0"
                            class="flex flex-wrap gap-2"
                            :style="{ paddingLeft: `${(depth(collection) + 1) * 24}px` }"
                        >
                            <Button
                                v-for="recipe in collection.recipes"
                                :key="recipe.id"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="recipeShow(recipe).url"><BookOpen data-icon="inline-start" />{{ recipe.name }}</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="createOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('recipes.collections.newDialogTitle') }}</DialogTitle>
                <DialogDescription>{{ t('recipes.collections.newDialogDescription') }}</DialogDescription>
            </DialogHeader>
            <form class="grid gap-4" @submit.prevent="createCollection">
                <Field>
                    <FieldLabel for="collection-name">{{ t('recipes.collections.name') }}</FieldLabel>
                    <Input id="collection-name" v-model="createForm.name" autofocus />
                    <InputError :message="createForm.errors.name" />
                </Field>
                <Field>
                    <FieldLabel for="collection-parent">{{ t('recipes.collections.parentFolder') }}</FieldLabel>
                    <select id="collection-parent" v-model="createForm.parent_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm">
                        <option :value="null">{{ t('recipes.collections.root') }}</option>
                        <option v-for="collection in collections" :key="collection.id" :value="collection.id">{{ collection.path }}</option>
                    </select>
                    <InputError :message="createForm.errors.parent_id" />
                </Field>
                <DialogFooter><Button type="submit" :disabled="createForm.processing">{{ t('recipes.collections.create') }}</Button></DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="editing !== null" @update:open="(open) => { if (!open) editing = null }">
        <DialogContent>
            <DialogHeader><DialogTitle>{{ t('recipes.collections.renameDialogTitle') }}</DialogTitle></DialogHeader>
            <form class="grid gap-4" @submit.prevent="renameCollection">
                <Field>
                    <FieldLabel for="edit-collection-name">{{ t('recipes.collections.name') }}</FieldLabel>
                    <Input id="edit-collection-name" v-model="editForm.name" />
                    <InputError :message="editForm.errors.name" />
                </Field>
                <DialogFooter><Button type="submit" :disabled="editForm.processing">{{ t('common.actions.save') }}</Button></DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="deleting !== null" @update:open="(open) => { if (!open) deleting = null }">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('recipes.collections.deleteDialogTitle') }}</DialogTitle>
                <DialogDescription>{{ t('recipes.collections.deleteDialogDescription') }}</DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleting = null">{{ t('common.actions.cancel') }}</Button>
                <Button variant="destructive" @click="deleteCollection">{{ t('common.actions.delete') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
