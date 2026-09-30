import type { InjectionKey, Ref } from 'vue'
import type { EmblaOptionsType, EmblaCarouselType } from 'embla-carousel'

export interface CarouselProps {
    opts?: EmblaOptionsType
    plugins?: any[]
    orientation?: 'horizontal' | 'vertical'
    class?: string
}

export interface CarouselEmits {
    (e: 'initReport', value: EmblaCarouselType): void
    (e: 'select', value: EmblaCarouselType): void
    (e: 'scroll', value: EmblaCarouselType): void
}

export type CarouselContext = {
    carouselRef: Ref<HTMLElement | undefined>
    api: Ref<EmblaCarouselType | undefined>
    scrollNext: () => void
    scrollPrev: () => void
    canScrollNext: Ref<boolean>
    canScrollPrev: Ref<boolean>
    opts: Ref<EmblaOptionsType | undefined>
    orientation: Ref<CarouselProps['orientation']>
    plugins: Ref<any[]>
    currentIndex: Ref<number>
    scrollSnapList: Ref<number[]>
    onScrollEnd: (handler: (event: any) => void) => void
}

export const CarouselKey: InjectionKey<CarouselContext> = Symbol('carousel')
