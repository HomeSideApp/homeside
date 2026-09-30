import { computed, onMounted, onUnmounted, provide, ref, watch, type Ref } from 'vue'
import useEmblaCarousel from 'embla-carousel-vue'
import type { CarouselEmits, CarouselProps } from './interface'
import { CarouselKey } from './interface'

export function useCarousel(props: CarouselProps, emits: CarouselEmits) {
    const [emblaRef, emblaApi] = useEmblaCarousel({
        ...props.opts,
        axis: props.orientation === 'horizontal' ? 'x' : 'y',
    }, props.plugins)

    const canScrollNext = ref(true)
    const canScrollPrev = ref(true)
    const scrollSnaps = ref<number[]>([])
    const selectedIndex = ref(0)

    function scrollPrev() {
        emblaApi.value?.scrollPrev()
    }

    function scrollNext() {
        emblaApi.value?.scrollNext()
    }

    function scrollTo(index: number) {
        emblaApi.value?.scrollTo(index)
    }

    function onSelect() {
        const api = emblaApi.value
        if (!api) return

        selectedIndex.value = api.selectedScrollSnap()
        canScrollNext.value = api.canScrollNext()
        canScrollPrev.value = api.canScrollPrev()

        emits('select', api)
    }

    function onScrollEnd(handler: (event: any) => void) {
        emblaApi.value?.on('settle', handler)
    }

    function onInit() {
        const api = emblaApi.value
        if (!api) return

        scrollSnaps.value = api.scrollSnapList()
        onSelect()

        api.on('select', onSelect)
        api.on('reInit', () => {
            scrollSnaps.value = api.scrollSnapList()
            onSelect()
        })

        emits('initReport', api)
    }

    onMounted(() => {
        if (!emblaApi.value) return
        onInit()
    })

    watch(emblaApi, () => {
        if (!emblaApi.value) return
        onInit()
    })

    provide(CarouselKey, {
        carouselRef: emblaRef,
        api: emblaApi as unknown as Ref<import('embla-carousel').EmblaCarouselType>,
        scrollNext,
        scrollPrev,
        canScrollNext,
        canScrollPrev,
        opts: ref(props.opts),
        orientation: ref(props.orientation),
        plugins: ref(props.plugins ?? []),
        currentIndex: selectedIndex,
        scrollSnapList: scrollSnaps,
        onScrollEnd,
    })

    return {
        carouselRef: emblaRef,
        canScrollNext,
        canScrollPrev,
        scrollNext,
        scrollPrev,
        scrollTo,
        currentIndex: selectedIndex,
        scrollSnapList: scrollSnaps,
        onScrollEnd,
    }
}
