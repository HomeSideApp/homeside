<script setup lang="ts">
import { useCarousel } from './useCarousel'
import type { CarouselProps, CarouselEmits } from './interface'
import { cn } from '@/lib/utils'

const props = withDefaults(defineProps<CarouselProps>(), {
    orientation: 'horizontal',
    opts: () => ({}),
    plugins: () => [],
    class: '',
})

const emits = defineEmits<CarouselEmits>()

const { canScrollNext, canScrollPrev, scrollNext, scrollPrev, currentIndex, scrollSnapList, onScrollEnd } =
    useCarousel(props, emits)
</script>

<template>
    <div :class="cn('relative', props.class)" role="region" aria-roledescription="carousel" @mouseenter="false">
        <slot
            :current-index="currentIndex"
            :can-scroll-next="canScrollNext"
            :can-scroll-prev="canScrollPrev"
            :scroll-next="scrollNext"
            :scroll-prev="scrollPrev"
            :scroll-snap-list="scrollSnapList"
            :on-scroll-end="onScrollEnd"
        />
    </div>
</template>
