<script setup lang="ts">
import { Tag, CookingPot, Clock, MessageSquare, Minus, Eye, Pencil, BookOpen } from '@lucide/vue'
import { refDebounced } from '@vueuse/core'
import { ref, computed, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { hasCooklangSyntax, parseCooklangSegments, parseIngredients, parseCookware, parseTimers } from '@/composables/useCooklangParser'

interface RecipeIngredient {
    id?: string
    name: string
    quantity?: number | null
    quantity_text?: string | null
    unit?: string | null
    preparation?: string | null
}

interface RecipeCookware {
    id?: string
    name: string
    type?: string
}

const { t } = useI18n();

const props = withDefaults(defineProps<{
    modelValue: string
    recipeIngredients?: RecipeIngredient[]
    recipeCookware?: RecipeCookware[]
    referenceRecipes?: Array<{ id: string; name: string; collection_path: string | null }>
    placeholder?: string
}>(), {
    recipeIngredients: () => [],
    recipeCookware: () => [],
    referenceRecipes: () => [],
    placeholder: 'Escribe el paso de la receta...',
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
    'update:detectedIngredients': [value: { refId: string; name: string; quantity: number | null; unit: string | null; preparation: string | null }[]]
    'update:detectedCookware': [value: { refId: string; name: string }[]]
    'update:detectedTimers': [value: { name: string | null; durationRaw: string; durationSeconds: number | null }[]]
    'addTimer': [value: { name: string; duration_seconds: number }]
    'addIngredient': [value: { name: string; quantity: number | null; unit: string | null; preparation: string | null }]
    'addCookware': [value: { name: string; type: string }]
}>()

const mode = ref<'rendered' | 'raw'>('raw')
const textareaRef = ref<HTMLTextAreaElement | null>(null)

// Search/filter state
const ingredientSearch = ref('')
const cookwareSearch = ref('')
const recipeSearch = ref('')
const debouncedRecipeSearch = refDebounced(recipeSearch, 200)

// Dialog states
const showIngredientDialog = ref(false)
const ingredientName = ref('')
const ingredientQuantity = ref<string>('')
const ingredientUnit = ref('')
const ingredientPreparation = ref('')

const showCookwareDialog = ref(false)
const cookwareName = ref('')
const cookwareType = ref('tool')

const showTimerDialog = ref(false)
const timerName = ref('')
const timerQuantity = ref<string>('')
const timerUnit = ref('minutes')

// Filtered lists
const filteredIngredients = computed(() => {
    if (!ingredientSearch.value) {
return props.recipeIngredients
}

    const q = ingredientSearch.value.toLowerCase()

    return props.recipeIngredients.filter(i => i.name.toLowerCase().includes(q))
})

const filteredCookware = computed(() => {
    if (!cookwareSearch.value) {
return props.recipeCookware
}

    const q = cookwareSearch.value.toLowerCase()

    return props.recipeCookware.filter(c => c.name.toLowerCase().includes(q))
})

const filteredReferenceRecipes = computed(() => {
    const query = debouncedRecipeSearch.value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase()

    if (!query) {
return props.referenceRecipes
}

    return props.referenceRecipes.filter((recipe) => {
        const label = [recipe.collection_path, recipe.name].filter(Boolean).join('/')

        return label.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase().includes(query)
    })
})

const isCooklang = computed(() => hasCooklangSyntax(props.modelValue))

// Watch for changes and emit detected references
watch(
    () => props.modelValue,
    (text) => {
        if (hasCooklangSyntax(text)) {
            emit('update:detectedIngredients', parseIngredients(text))
            emit('update:detectedCookware', parseCookware(text))
            emit('update:detectedTimers', parseTimers(text))
        }
    },
    { immediate: true },
)

function updateText(value: string) {
    emit('update:modelValue', value)
}

// Insert text at cursor position
function insertAtCursor(text: string) {
    const ref = textareaRef.value
    // Get the native textarea element (handles both component ref and native ref)
    const ta = (ref as any)?.$el as HTMLTextAreaElement || ref as HTMLTextAreaElement

    if (!ta || typeof ta.selectionStart !== 'number') {
        // Fallback: append to end
        updateText(props.modelValue + text)

        return
    }

    const start = ta.selectionStart
    const end = ta.selectionEnd
    const current = props.modelValue
    const newText = current.substring(0, start) + text + current.substring(end)
    updateText(newText)

    nextTick(() => {
        ta.focus()
        const pos = start + text.length
        ta.selectionStart = ta.selectionEnd = pos
    })
}

// Ingredient actions
function selectExistingIngredient(ing: RecipeIngredient) {
    const qty = ing.quantity != null ? ing.quantity : ''
    const unit = ing.unit || ''
    const notes = ing.preparation ? `(${ing.preparation})` : ''
    const qtyStr = qty !== '' ? `{${qty}${unit ? '%' + unit : ''}}` : ''
    insertAtCursor(`@${ing.name}${qtyStr}${notes} `)
    ingredientSearch.value = ''
}

function openNewIngredientDialog() {
    ingredientName.value = ''
    ingredientQuantity.value = ''
    ingredientUnit.value = ''
    ingredientPreparation.value = ''
    showIngredientDialog.value = true
}

function insertNewIngredient() {
    const name = ingredientName.value.trim()

    if (!name) {
return
}

    const qty = ingredientQuantity.value ? Number(ingredientQuantity.value) : null
    const unit = ingredientUnit.value.trim() || null
    const prep = ingredientPreparation.value.trim() || null

    let qtyStr = ''

    if (qty != null && !isNaN(qty)) {
        qtyStr = `{${qty}${unit ? '%' + unit : ''}}`
    }

    const notes = prep ? `(${prep})` : ''
    insertAtCursor(`@${name}${qtyStr}${notes} `)

    // Emit to add this ingredient to the recipe's ingredients list
    emit('addIngredient', { name, quantity: qty, unit, preparation: prep })

    showIngredientDialog.value = false
}

// Cookware actions
function selectExistingCookware(cw: RecipeCookware) {
    insertAtCursor(`#${cw.name}{} `)
    cookwareSearch.value = ''
}

function insertRecipeReference(recipe: { name: string; collection_path: string | null }) {
    const path = [recipe.collection_path, recipe.name].filter(Boolean).join('/')
    insertAtCursor(`@./${path}{} `)
    recipeSearch.value = ''
}

function openNewCookwareDialog() {
    cookwareName.value = ''
    cookwareType.value = 'tool'
    showCookwareDialog.value = true
}

function insertNewCookware() {
    const name = cookwareName.value.trim()

    if (!name) {
return
}

    insertAtCursor(`#${name}{} `)
    emit('addCookware', { name, type: cookwareType.value })
    showCookwareDialog.value = false
}

// Timer actions
function openTimerDialog() {
    timerName.value = ''
    timerQuantity.value = ''
    timerUnit.value = 'minutes'
    showTimerDialog.value = true
}

function insertTimer() {
    const qty = timerQuantity.value ? parseFloat(timerQuantity.value) : null

    if (qty == null || isNaN(qty)) {
return
}

    const unit = timerUnit.value
    const name = timerName.value.trim()
    const timerStr = name ? `~${name}{${qty}%${unit}}` : `~{${qty}%${unit}}`
    insertAtCursor(`${timerStr} `)

    // Emit timer to sync with step.timers
    const durationSeconds = qty * (unit === 'hours' ? 3600 : unit === 'seconds' ? 1 : 60)
    emit('addTimer', {
        name: name || `Timer ${timerQuantity.value} ${unit}`,
        duration_seconds: durationSeconds,
    })

    showTimerDialog.value = false
}

// Note/Comment inserts
function insertNote() {
 insertAtCursor('\n> ') 
}
function insertComment() {
 insertAtCursor('\n-- ') 
}

// Preview computed values for dialogs
const ingredientPreview = computed(() => {
    if (!ingredientName.value) {
return ''
}

    let text = '@' + ingredientName.value

    if (ingredientQuantity.value) {
        text += '{' + ingredientQuantity.value

        if (ingredientUnit.value) {
text += '%' + ingredientUnit.value
}

        text += '}'
    }

    if (ingredientPreparation.value) {
text += '(' + ingredientPreparation.value + ')'
}

    return text
})

const cookwarePreview = computed(() => {
    return cookwareName.value ? '#' + cookwareName.value + '{}' : ''
})

const timerPreview = computed(() => {
    if (!timerQuantity.value) {
return ''
}

    let text = '~'

    if (timerName.value) {
text += timerName.value
}

    text += '{' + timerQuantity.value + '%' + timerUnit.value + '}'

    return text
})

function formatQuantity(quantity: number | null, unit: string | null): string {
    if (quantity === null) {
return ''
}

    const qty = quantity % 1 === 0 ? quantity.toString() : quantity.toFixed(1)

    return unit ? `${qty} ${unit}` : qty
}

function formatDuration(seconds: number): string {
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = seconds % 60

    const parts: string[] = []

    if (h > 0) {
parts.push(`${h}h`)
}

    if (m > 0) {
parts.push(`${m}min`)
}

    if (parts.length === 0 && s > 0) {
parts.push(`${s}s`)
}

    return parts.join(' ') || '0s'
}

// Render line for preview mode
interface RenderPart {
    type: 'text' | 'ingredient' | 'cookware' | 'timer' | 'recipe'
    text: string
    quantity?: number | null
    unit?: string | null
    durationSeconds?: number | null
}

function renderLine(line: string): RenderPart[] {
    return parseCooklangSegments(line).map((segment) => ({
        type: segment.type,
        text: segment.type === 'timer' && !segment.content ? segment.durationRaw ?? '' : segment.content,
        quantity: segment.quantity,
        unit: segment.unit,
        durationSeconds: segment.durationSeconds,
    }))
}
</script>

<template>
    <div class="cooklang-wysiwyg border rounded-md">
        <!-- Toolbar -->
        <TooltipProvider>
        <div class="flex items-center gap-1 px-2 py-1.5 border-b bg-muted/30 flex-wrap">
            <!-- Ingredient dropdown -->
            <DropdownMenu>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" :aria-label="t('recipes.form.ingredients')">
                                <Tag class="h-3.5 w-3.5 mr-1" aria-hidden="true" /> @
                            </Button>
                        </DropdownMenuTrigger>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('recipes.form.ingredients') }}</TooltipContent>
                </Tooltip>
                <DropdownMenuContent class="w-64" align="start">
                    <div class="p-1">
                        <Input v-model="ingredientSearch" placeholder="Buscar ingrediente..." class="h-7 text-xs" />
                    </div>
                    <DropdownMenuItem v-if="filteredIngredients.length === 0" disabled class="text-xs text-muted-foreground">
                        No se encontraron ingredientes
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-for="ing in filteredIngredients"
                        :key="ing.id"
                        @select="selectExistingIngredient(ing)"
                        class="text-xs"
                    >
                        {{ ing.name }}
                        <span v-if="ing.quantity != null" class="ml-auto text-muted-foreground">
                            {{ ing.quantity }}{{ ing.unit ? ' ' + ing.unit : '' }}
                        </span>
                    </DropdownMenuItem>
                    <DropdownMenuItem class="text-xs border-t" @select="openNewIngredientDialog">
                        + Nuevo ingrediente
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Cookware dropdown -->
            <DropdownMenu>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="ghost" size="sm" class="h-7 px-2 text-xs" :aria-label="t('recipes.form.cookware')">
                                <CookingPot class="h-3.5 w-3.5 mr-1" aria-hidden="true" /> #
                            </Button>
                        </DropdownMenuTrigger>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('recipes.form.cookware') }}</TooltipContent>
                </Tooltip>
                <DropdownMenuContent class="w-64" align="start">
                    <div class="p-1">
                        <Input v-model="cookwareSearch" placeholder="Buscar utensilio..." class="h-7 text-xs" />
                    </div>
                    <DropdownMenuItem v-if="filteredCookware.length === 0" disabled class="text-xs text-muted-foreground">
                        No se encontraron utensilios
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-for="cw in filteredCookware"
                        :key="cw.id"
                        @select="selectExistingCookware(cw)"
                        class="text-xs"
                    >
                        {{ cw.name }}
                    </DropdownMenuItem>
                    <DropdownMenuItem class="text-xs border-t" @select="openNewCookwareDialog">
                        + Nuevo utensilio
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <!-- Timer button -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="icon-sm" class="size-7" :aria-label="t('recipes.cooklangEditor.timer')" @click="openTimerDialog">
                        <Clock aria-hidden="true" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{{ t('recipes.cooklangEditor.timer') }}</TooltipContent>
            </Tooltip>

            <!-- Referenced recipe dropdown -->
            <DropdownMenu>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <DropdownMenuTrigger as-child>
                            <Button type="button" variant="ghost" size="icon-sm" class="size-7" :aria-label="t('recipes.cooklangEditor.referenceRecipe')">
                                <BookOpen aria-hidden="true" />
                            </Button>
                        </DropdownMenuTrigger>
                    </TooltipTrigger>
                    <TooltipContent>{{ t('recipes.cooklangEditor.referenceRecipe') }}</TooltipContent>
                </Tooltip>
                <DropdownMenuContent class="w-72" align="start">
                    <div class="p-1">
                        <Input v-model="recipeSearch" placeholder="Buscar receta o carpeta..." class="h-7 text-xs" />
                    </div>
                    <DropdownMenuItem v-if="filteredReferenceRecipes.length === 0" disabled class="text-xs text-muted-foreground">
                        No se encontraron recetas
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-for="recipe in filteredReferenceRecipes"
                        :key="recipe.id"
                        class="text-xs"
                        @select="insertRecipeReference(recipe)"
                    >
                        <span>{{ recipe.name }}</span>
                        <span v-if="recipe.collection_path" class="ml-auto text-muted-foreground">{{ recipe.collection_path }}</span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <div class="w-px h-4 bg-border mx-1" />

            <!-- Note -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="icon-sm" class="size-7" :aria-label="t('recipes.cooklangEditor.insertNote')" @click="insertNote">
                        <MessageSquare aria-hidden="true" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{{ t('recipes.cooklangEditor.insertNote') }}</TooltipContent>
            </Tooltip>

            <!-- Comment -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="icon-sm" class="size-7" :aria-label="t('recipes.cooklangEditor.insertComment')" @click="insertComment">
                        <Minus aria-hidden="true" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{{ t('recipes.cooklangEditor.insertComment') }}</TooltipContent>
            </Tooltip>

            <div class="flex-1" />

            <!-- Toggle raw/rendered -->
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button type="button" variant="ghost" size="icon-sm" class="size-7" :aria-label="mode === 'raw' ? t('recipes.cooklangEditor.preview') : t('recipes.cooklangEditor.editSource')" @click="mode = mode === 'raw' ? 'rendered' : 'raw'">
                        <Eye v-if="mode === 'raw'" aria-hidden="true" />
                        <Pencil v-else aria-hidden="true" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{{ mode === 'raw' ? t('recipes.cooklangEditor.preview') : t('recipes.cooklangEditor.editSource') }}</TooltipContent>
            </Tooltip>
        </div>
        </TooltipProvider>

        <!-- Raw editor -->
        <div v-if="mode === 'raw'">
            <Textarea
                ref="textareaRef"
                :model-value="modelValue"
                :placeholder="placeholder"
                class="min-h-[120px] p-3 text-sm font-mono resize-y border-0 rounded-none focus-visible:ring-0"
                @update:model-value="(v: any) => updateText(String(v))"
            />
        </div>

        <!-- Rendered preview -->
        <div v-else class="p-3 min-h-[120px]">
            <div v-if="isCooklang" class="cooklang-rendered text-sm space-y-1">
                <template v-for="(line, idx) in modelValue.split('\n')" :key="idx">
                    <div v-if="line.trim().startsWith('= ')" class="font-semibold text-base mt-3">
                        {{ line.replace(/^=\s*/, '') }}
                    </div>
                    <blockquote v-else-if="line.trim().startsWith('> ')" class="border-l-4 border-muted-foreground/30 pl-4 italic text-muted-foreground my-1">
                        {{ line.replace(/^>\s*/, '') }}
                    </blockquote>
                    <div v-else-if="line.trim().startsWith('--')" class="text-xs text-muted-foreground italic">
                        {{ line }}
                    </div>
                    <div v-else class="leading-relaxed">
                        <template v-for="(part, pidx) in renderLine(line)" :key="pidx">
                            <Badge v-if="part.type === 'ingredient'" variant="secondary" class="mx-0.5 text-xs gap-1">
                                <Tag class="h-3 w-3" />
                                {{ part.text }}
                                <span v-if="part.quantity != null" class="font-normal opacity-90">
                                    {{ formatQuantity(part.quantity ?? null, part.unit ?? null) }}
                                </span>
                            </Badge>
                            <Badge v-else-if="part.type === 'cookware'" variant="outline" class="mx-0.5 text-xs gap-1">
                                <CookingPot class="h-3 w-3" />
                                {{ part.text }}
                            </Badge>
                            <Badge v-else-if="part.type === 'timer'" variant="secondary" class="mx-0.5 text-xs gap-1">
                                <Clock class="h-3 w-3" />
                                <span v-if="part.durationSeconds != null">{{ part.text }}: {{ formatDuration(part.durationSeconds) }}</span>
                                <span v-else>{{ part.text }}</span>
                            </Badge>
                            <Badge v-else-if="part.type === 'recipe'" variant="outline" class="mx-0.5 gap-1 border-primary/40 text-xs text-primary">
                                <BookOpen class="h-3 w-3" />
                                {{ part.text.replace(/^\.\//, '') }}
                                <span v-if="part.quantity != null" class="font-normal opacity-75">× {{ formatQuantity(part.quantity, part.unit ?? null) }}</span>
                            </Badge>
                            <span v-else>{{ part.text }}</span>
                        </template>
                    </div>
                </template>
            </div>
            <p v-else class="text-sm text-muted-foreground italic">
                {{ modelValue || t('recipes.cooklangEditor.previewEmpty') }}
            </p>
        </div>
    </div>

    <!-- New Ingredient Dialog -->
    <Dialog v-model:open="showIngredientDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('recipes.cooklangEditor.newIngredient') }}</DialogTitle>
                <DialogDescription>{{ t('recipes.cooklangEditor.newIngredientDescription') }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-2">
                <div class="grid gap-2">
                    <Label for="ing-name">{{ t('recipes.cooklangEditor.ingredientName') }}</Label>
                    <Input id="ing-name" v-model="ingredientName" :placeholder="t('recipes.cooklangEditor.ingredientNamePlaceholder')" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="ing-qty">{{ t('recipes.cooklangEditor.quantity') }}</Label>
                        <Input id="ing-qty" v-model="ingredientQuantity" type="number" step="0.5" placeholder="200" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="ing-unit">{{ t('recipes.cooklangEditor.unit') }}</Label>
                        <Input id="ing-unit" v-model="ingredientUnit" :placeholder="t('recipes.cooklangEditor.unitPlaceholder')" />
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label for="ing-prep">{{ t('recipes.cooklangEditor.preparation') }}</Label>
                    <Input id="ing-prep" v-model="ingredientPreparation" :placeholder="t('recipes.cooklangEditor.preparationPlaceholder')" />
                </div>
                <div v-if="ingredientName" class="text-xs text-muted-foreground bg-muted p-2 rounded font-mono">
                    {{ ingredientPreview }}
                </div>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" @click="showIngredientDialog = false">{{ t('common.actions.cancel') }}</Button>
                <Button type="button" @click="insertNewIngredient" :disabled="!ingredientName.trim()">{{ t('recipes.cooklangEditor.insert') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- New Cookware Dialog -->
    <Dialog v-model:open="showCookwareDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('recipes.cooklangEditor.newCookware') }}</DialogTitle>
                <DialogDescription>{{ t('recipes.cooklangEditor.newIngredientDescription') }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-2">
                <div class="grid gap-2">
                    <Label for="cw-name">{{ t('recipes.cooklangEditor.cookwareName') }}</Label>
                    <Input id="cw-name" v-model="cookwareName" :placeholder="t('recipes.cooklangEditor.cookwareNamePlaceholder')" />
                </div>
                <div class="grid gap-2">
                    <Label for="cw-type">{{ t('recipes.cooklangEditor.type') }}</Label>
                    <Select v-model="cookwareType">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="tool">{{ t('recipes.cooklangEditor.tool') }}</SelectItem>
                            <SelectItem value="supply">{{ t('recipes.cooklangEditor.material') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div v-if="cookwareName" class="text-xs text-muted-foreground bg-muted p-2 rounded font-mono">
                    {{ cookwarePreview }}
                </div>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" @click="showCookwareDialog = false">{{ t('common.actions.cancel') }}</Button>
                <Button type="button" @click="insertNewCookware" :disabled="!cookwareName.trim()">{{ t('recipes.cooklangEditor.insert') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Timer Dialog -->
    <Dialog v-model:open="showTimerDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('recipes.cooklangEditor.timer') }}</DialogTitle>
                <DialogDescription>{{ t('recipes.cooklangEditor.timerDescription') }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-2">
                <div class="grid gap-2">
                    <Label for="timer-name">{{ t('recipes.cooklangEditor.timerName') }}</Label>
                    <Input id="timer-name" v-model="timerName" :placeholder="t('recipes.cooklangEditor.timerNamePlaceholder')" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="timer-qty">{{ t('recipes.cooklangEditor.duration') }}</Label>
                        <Input id="timer-qty" v-model="timerQuantity" type="number" min="1" placeholder="5" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="timer-unit">{{ t('recipes.cooklangEditor.unit') }}</Label>
                        <Select v-model="timerUnit">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="minutes">Minutos</SelectItem>
                                <SelectItem value="hours">Horas</SelectItem>
                                <SelectItem value="seconds">Segundos</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <div v-if="timerQuantity" class="text-xs text-muted-foreground bg-muted p-2 rounded font-mono">
                    {{ timerPreview }}
                </div>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" @click="showTimerDialog = false">Cancelar</Button>
                <Button type="button" @click="insertTimer" :disabled="!timerQuantity">Insertar</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
