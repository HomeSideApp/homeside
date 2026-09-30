<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Tag, CookingPot, Clock, BookOpen } from '@lucide/vue'
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { hasCooklangSyntax, parseCooklangSegments } from '@/composables/useCooklangParser'
import { show } from '@/routes/recipes'

interface RecipeIngredient {
    id: string
    name: string
    quantity: number | null
    unit: string | null
    preparation: string | null
}

interface RecipeCookware {
    id: string
    name: string
}

interface RecipeTimer {
    id: string
    name: string | null
    duration_seconds: number
}

interface RecipeReference {
    path: string
    referenced_recipe: { id: string; name: string } | null
}

const props = defineProps<{
    text: string
    ingredients?: RecipeIngredient[]
    cookware?: RecipeCookware[]
    timers?: RecipeTimer[]
    references?: RecipeReference[]
}>()

interface TextSegment {
    type: 'text' | 'ingredient' | 'cookware' | 'timer' | 'recipe' | 'section' | 'note'
    content: string
    quantity?: number | null
    unit?: string | null
    preparation?: string | null
    durationSeconds?: number | null
}

function resolvedRecipe(path: string): RecipeReference['referenced_recipe'] {
    return props.references?.find((reference) => reference.path === path)?.referenced_recipe ?? null
}

const segments = computed<TextSegment[]>(() => {
    if (!props.text) {
return []
}

    // If no Cooklang syntax, return as plain text
    if (!hasCooklangSyntax(props.text)) {
        return [{ type: 'text', content: props.text }]
    }

    const result: TextSegment[] = []
    const lines = props.text.split('\n')

    for (const line of lines) {
        const trimmed = line.trim()

        // Section header: = Name or == Name ==
        const sectionMatch = trimmed.match(/^={1,2}\s+(.+?)\s+=\s*$/)

        if (sectionMatch) {
            result.push({ type: 'section', content: sectionMatch[1] })
            continue
        }

        // Note block: > text
        const noteMatch = trimmed.match(/^>\s*(.+)$/)

        if (noteMatch) {
            result.push({ type: 'note', content: noteMatch[1] })
            continue
        }

        // Cooklang comment: -- text
        if (trimmed.startsWith('--')) {
            result.push({ type: 'text', content: trimmed })
            continue
        }

        // Parse the line for inline references
        result.push(...parseLine(trimmed))
    }

    return result
})

function parseLine(text: string): TextSegment[] {
    return parseCooklangSegments(text).map((segment) => ({
        type: segment.type,
        content: segment.type === 'timer' && !segment.content ? segment.durationRaw ?? '' : segment.content,
        quantity: segment.quantity,
        unit: segment.unit,
        preparation: segment.preparation,
        durationSeconds: segment.durationSeconds,
    }))
}

function formatDuration(seconds: number): string {
    const h = Math.floor(seconds / 3600)
    const m = Math.floor((seconds % 3600) / 60)
    const s = seconds % 60

    if (h > 0) {
return `${h}h ${m > 0 ? m + 'min' : ''}`
}

    if (m > 0) {
return `${m}min${s > 0 ? ' ' + s + 's' : ''}`
}

    return `${s}s`
}

function formatQuantity(quantity: number | null, unit: string | null): string {
    if (quantity === null) {
return ''
}

    const qty = quantity % 1 === 0 ? quantity.toString() : quantity.toFixed(1)

    return unit ? `${qty} ${unit}` : qty
}
</script>

<template>
    <div class="cooklang-renderer space-y-2">
        <template v-for="(segment, idx) in segments" :key="idx">
            <!-- Section header -->
            <h4 v-if="segment.type === 'section'" class="text-lg font-semibold mt-4">
                {{ segment.content }}
            </h4>

            <!-- Note block -->
            <blockquote v-else-if="segment.type === 'note'" class="border-l-4 border-muted-foreground/30 pl-4 italic text-muted-foreground">
                {{ segment.content }}
            </blockquote>

            <!-- Ingredient chip -->
            <Badge v-else-if="segment.type === 'ingredient'" variant="secondary" class="mx-0.5 gap-1">
                <Tag class="h-3 w-3" />
                {{ segment.content }}
                <span v-if="segment.quantity != null" class="font-normal opacity-90">
                    {{ formatQuantity(segment.quantity ?? null, segment.unit ?? null) }}
                </span>
                <span v-if="segment.preparation" class="font-normal opacity-75">
                    ({{ segment.preparation }})
                </span>
            </Badge>

            <!-- Cookware chip -->
            <Badge v-else-if="segment.type === 'cookware'" variant="outline" class="mx-0.5 gap-1">
                <CookingPot class="h-3 w-3" />
                {{ segment.content }}
                <span v-if="segment.quantity != null" class="font-normal opacity-75">
                    {{ formatQuantity(segment.quantity ?? null, segment.unit ?? null) }}
                </span>
            </Badge>

            <!-- Timer chip -->
            <Badge v-else-if="segment.type === 'timer'" variant="secondary" class="mx-0.5 gap-1">
                <Clock class="h-3 w-3" />
                <span v-if="segment.content && segment.durationSeconds != null">
                    {{ segment.content }}: {{ formatDuration(segment.durationSeconds ?? 0) }}
                </span>
                <span v-else-if="segment.durationSeconds != null">
                    {{ formatDuration(segment.durationSeconds ?? 0) }}
                </span>
            </Badge>

            <!-- Referenced recipe chip -->
            <Badge v-else-if="segment.type === 'recipe'" variant="outline" class="mx-0.5 gap-1 border-primary/40 text-primary" as-child>
                <Link v-if="resolvedRecipe(segment.content)" :href="show(resolvedRecipe(segment.content)!.id).url">
                    <BookOpen class="h-3 w-3" />
                    {{ resolvedRecipe(segment.content)!.name }}
                    <span v-if="segment.quantity != null" class="font-normal opacity-75">
                        × {{ formatQuantity(segment.quantity ?? null, segment.unit ?? null) }}
                    </span>
                </Link>
                <span v-else>
                    <BookOpen class="h-3 w-3" />
                    {{ segment.content.replace(/^\.\//, '') }}
                </span>
            </Badge>

            <!-- Plain text -->
            <span v-else class="text-sm">{{ segment.content }} </span>
        </template>
    </div>
</template>
