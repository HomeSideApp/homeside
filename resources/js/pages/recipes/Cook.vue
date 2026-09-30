<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft, ChefHat, List, X, Tag, CookingPot, ShoppingBasket } from '@lucide/vue'
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n';
import CookingTimer from '@/components/recipes/CookingTimer.vue'
import CooklangRenderer from '@/components/recipes/CooklangRenderer.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious } from '@/components/ui/carousel'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'

interface RecipeTimer {
    id: string
    name: string
    duration_seconds: number
}

interface RecipeStep {
    id: string
    section_id: string | null
    description: string
    image_url: string | null
    order: number
    ingredients: Array<{ id: string; name: string }>
    cookware: Array<{ id: string; name: string }>
    timers: RecipeTimer[]
    recipe_references: Array<{ path: string; referenced_recipe: { id: string; name: string } | null }>
}

interface RecipeSection {
    id: string
    name: string
    order: number
}

interface RecipeCookware {
    id: string
    name: string
    type: string
}

const props = defineProps<{
    recipe: {
        id: string
        name: string
        description: string | null
        servings: number | null
        sections: RecipeSection[]
        ingredients: Array<{
            id: string
            name: string
            quantity: number | null
            quantity_text: string | null
            unit: string | null
            preparation: string | null
        }>
        steps: RecipeStep[]
        cookware: RecipeCookware[]
    }
}>()

const { t } = useI18n();

const currentSlideIndex = ref(0)
const showNavigation = ref(false)
const carouselApi = ref<any>(null)

// Slides: section dividers + steps, in order
type SectionSlide = { type: 'section'; section: RecipeSection; globalIndex: number }
type StepSlide = { type: 'step'; step: RecipeStep; section: RecipeSection | null; globalIndex: number }
type Slide = SectionSlide | StepSlide

const slides = computed<Slide[]>(() => {
    const sections = props.recipe.sections ?? []
    const steps = props.recipe.steps ?? []
    const result: Slide[] = []

    const sectionMap = new Map<string, RecipeSection>()

    for (const s of sections) {
        sectionMap.set(s.id, s)
    }

    const sectionGroups = new Map<string, RecipeStep[]>()
    const ungrouped: RecipeStep[] = []

    for (const step of steps) {
        if (step.section_id && sectionMap.has(step.section_id)) {
            if (!sectionGroups.has(step.section_id)) {
                sectionGroups.set(step.section_id, [])
            }

            sectionGroups.get(step.section_id)!.push(step)
        } else {
            ungrouped.push(step)
        }
    }

    let idx = 0

    for (const section of sections) {
        const groupSteps = sectionGroups.get(section.id)

        if (groupSteps && groupSteps.length > 0) {
            result.push({ type: 'section', section, globalIndex: idx++ })

            for (const step of groupSteps) {
                result.push({ type: 'step', step, section, globalIndex: idx++ })
            }
        }
    }

    for (const step of ungrouped) {
        result.push({ type: 'step', step, section: null, globalIndex: idx++ })
    }

    return result
})

const totalSlides = computed(() => slides.value.length)

function scrollToSlide(index: number) {
    carouselApi.value?.scrollTo(index)
    showNavigation.value = false
}

function onCarouselReady(api: any) {
    carouselApi.value = api
}
</script>

<template>
    <Head :title="t('recipes.cook.title', { name: recipe.name })" />

    <div class="flex h-screen flex-col overflow-hidden bg-background">
        <!-- Header -->
        <header class="flex shrink-0 items-center justify-between border-b px-6 py-3">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="`/recipes/${recipe.id}`">
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                </Button>
                <div class="flex items-center gap-2">
                    <ChefHat class="h-5 w-5 text-primary" />
                    <h1 class="text-lg font-semibold">{{ recipe.name }}</h1>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <span class="font-medium text-foreground">{{ currentSlideIndex + 1 }}</span>
                <span>de {{ totalSlides }}</span>
            </div>
            <div class="flex items-center gap-2">
                <Sheet v-if="recipe.ingredients.length > 0 || recipe.cookware.length > 0">
                    <SheetTrigger as-child>
                        <Button variant="ghost" size="sm">
                            <ShoppingBasket class="mr-2 h-4 w-4" />
                            Mise en place
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="right" class="w-80 sm:w-96">
                        <SheetHeader>
                            <SheetTitle>Lo que necesitas</SheetTitle>
                        </SheetHeader>
                        <div class="mt-8 max-h-[calc(100vh-8rem)] space-y-8 overflow-y-auto px-6 pb-6">
                            <!-- Ingredients -->
                            <div v-if="recipe.ingredients.length > 0" class="space-y-4">
                                <h3 class="flex items-center gap-2 text-sm font-semibold">
                                    <Tag class="h-4 w-4 text-primary" />
                                    Ingredientes
                                </h3>
                                <ul class="space-y-3">
                                    <li
                                        v-for="ingredient in recipe.ingredients"
                                        :key="ingredient.id"
                                        class="flex items-baseline gap-2 rounded-lg bg-muted/50 px-3 py-2 text-sm"
                                    >
                                        <span class="font-medium">{{ ingredient.name }}</span>
                                        <span v-if="ingredient.quantity != null || ingredient.unit" class="whitespace-nowrap text-muted-foreground">
                                            {{ ingredient.quantity ?? ingredient.quantity_text ?? '' }}
                                            {{ ingredient.unit ?? '' }}
                                        </span>
                                        <span v-if="ingredient.preparation" class="whitespace-nowrap text-xs text-muted-foreground">
                                            ({{ ingredient.preparation }})
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Cookware -->
                            <div v-if="recipe.cookware.length > 0" class="space-y-4">
                                <h3 class="flex items-center gap-2 text-sm font-semibold">
                                    <CookingPot class="h-4 w-4 text-primary" />
                                    Utensilios
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        v-for="item in recipe.cookware"
                                        :key="item.id"
                                        variant="outline"
                                        class="text-sm"
                                    >
                                        {{ item.name }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </header>

        <!-- Main content area -->
        <div class="relative min-h-0 flex-1 overflow-hidden">
            <!-- Carousel -->
            <div class="flex h-full items-center justify-center overflow-hidden px-12">
                <Carousel
                    class="w-full"
                    :opts="{ loop: false, align: 'center' }"
                    @select="(api: any) => { currentSlideIndex = api?.selectedScrollSnap() ?? 0 }"
                    @init-report="onCarouselReady"
                >
                    <CarouselContent>
                        <CarouselItem
                            v-for="slide in slides"
                            :key="slide.type === 'section' ? `section-${slide.section.id}` : slide.step.id"
                            :aria-label="slide.type === 'section' ? slide.section.name : `Paso ${slide.step.order}`"
                        >
                            <!-- Section divider slide -->
                            <div v-if="slide.type === 'section'" class="mx-auto max-w-md">
                                <div class="flex flex-col items-center justify-center gap-4 rounded-2xl border-2 border-dashed border-primary/30 bg-primary/5 p-12 text-center">
                                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                        <ChefHat class="h-8 w-8 text-primary" />
                                    </div>
                                    <h2 class="text-2xl font-bold">{{ slide.section.name }}</h2>
                                    <p class="text-sm text-muted-foreground">{{ t('recipes.cook.newSection') }}</p>
                                </div>
                            </div>

                            <!-- Step slide -->
                            <div v-else class="mx-auto max-w-2xl">
                                <div class="flex flex-col gap-6 rounded-2xl border bg-card p-8 shadow-sm">
                                    <!-- Step header -->
                                    <div class="flex items-start gap-4">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary text-lg font-bold text-primary-foreground">
                                            {{ slide.step.order }}
                                        </div>
                                        <div class="flex-1">
                                            <h2 class="text-lg font-semibold">
                                                Paso {{ slide.step.order }}
                                            </h2>
                                        </div>
                                    </div>

                                    <!-- Step image -->
                                    <img
                                        v-if="slide.step.image_url"
                                        :src="slide.step.image_url"
                                        :alt="`Paso ${slide.step.order}`"
                                        class="h-48 w-full rounded-lg object-cover sm:h-64"
                                    />

                                    <!-- Step description -->
                                    <div class="text-base leading-relaxed">
                                        <CooklangRenderer
                                            :text="slide.step.description"
                                            :references="slide.step.recipe_references"
                                            class="text-base"
                                        />
                                    </div>

                                    <!-- Timers -->
                                    <div v-if="(slide.step.timers ?? []).length > 0" class="space-y-3">
                                        <h3 class="text-sm font-medium text-muted-foreground">Temporizadores</h3>
                                        <div class="flex flex-col gap-3">
                                            <CookingTimer
                                                v-for="timer in slide.step.timers"
                                                :key="timer.id"
                                                :name="timer.name"
                                                :duration-seconds="timer.duration_seconds"
                                                :auto-reset-key="slide.step.id"
                                            />
                                        </div>
                                    </div>

                                    <!-- Cookware -->
                                    <div v-if="(slide.step.cookware ?? []).length > 0" class="flex flex-wrap gap-2">
                                        <Badge
                                            v-for="item in slide.step.cookware"
                                            :key="item.id"
                                            variant="outline"
                                            class="text-sm"
                                        >
                                            {{ item.name }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CarouselItem>
                    </CarouselContent>
                    <CarouselPrevious class="-left-4 lg:-left-12" />
                    <CarouselNext class="-right-4 lg:-right-12" />
                </Carousel>
            </div>
        </div>

        <!-- Footer: navigation toggle + dots -->
        <footer class="flex shrink-0 items-center justify-center gap-3 border-t px-6 py-3">
            <Button
                variant="ghost"
                size="icon"
                class="h-7 w-7 shrink-0"
                :title="t('recipes.cook.navigationPanel')"
                @click="showNavigation = !showNavigation"
            >
                <List class="h-4 w-4" />
            </Button>
            <div class="flex gap-1.5">
                <button
                    v-for="(slide, idx) in slides"
                    :key="slide.type === 'section' ? `dot-${slide.section.id}` : `dot-${slide.step.id}`"
                    class="h-2 rounded-full transition-all"
                    :class="idx === currentSlideIndex ? 'w-6 bg-primary' : slide.type === 'section' ? 'w-3 bg-primary/40' : 'w-2 bg-muted-foreground/30'"
                    :aria-label="slide.type === 'section' ? slide.section.name : `Ir al paso ${slide.step.order}`"
                    @click="scrollToSlide(idx)"
                />
            </div>
        </footer>
    </div>

    <!-- Navigation sidebar (Word-style) - Teleported to body -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showNavigation"
                class="fixed inset-0 z-50 bg-black/20"
                @click="showNavigation = false"
            />
        </Transition>
        <Transition
            enter-active-class="transition-transform duration-200 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-150 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <div
                v-if="showNavigation"
                class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col overflow-hidden border-r bg-card shadow-xl"
                @click.stop
            >
                <!-- Sidebar header -->
                <div class="flex items-center justify-between border-b px-4 py-3">
                    <h3 class="text-sm font-semibold">{{ t('recipes.cook.navigation') }}</h3>
                    <Button variant="ghost" size="icon" class="h-7 w-7" @click="showNavigation = false">
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Slide list -->
                <div class="flex-1 overflow-y-auto p-2">
                    <button
                        v-for="(slide, idx) in slides"
                        :key="slide.type === 'section' ? `nav-${slide.section.id}` : `nav-${slide.step.id}`"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors"
                        :class="idx === currentSlideIndex
                            ? 'bg-primary/10 text-primary'
                            : 'hover:bg-muted text-muted-foreground hover:text-foreground'"
                        @click="scrollToSlide(idx)"
                    >
                        <!-- Section slide -->
                        <template v-if="slide.type === 'section'">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded border border-dashed border-primary/40 bg-primary/5">
                                <ChefHat class="h-4 w-4 text-primary/60" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xs font-semibold uppercase tracking-wide">
                                    {{ slide.section.name }}
                                </div>
                                <div class="truncate text-xs text-muted-foreground">{{ t('recipes.cook.section') }}</div>
                            </div>
                        </template>

                        <!-- Step slide -->
                        <template v-else>
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-bold">
                                {{ slide.step.order }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-xs font-medium">
                                    Paso {{ slide.step.order }}
                                </div>
                                <div class="truncate text-xs text-muted-foreground">
                                    {{ slide.step.description.substring(0, 60) }}{{ slide.step.description.length > 60 ? '…' : '' }}
                                </div>
                            </div>
                        </template>

                        <!-- Active indicator -->
                        <div
                            v-if="idx === currentSlideIndex"
                            class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary"
                        />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
