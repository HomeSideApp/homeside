<script setup lang="ts">
import { Play, Pause, RotateCcw, Timer } from '@lucide/vue'
import { ref, computed, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Button } from '@/components/ui/button'

const { t } = useI18n();

const props = withDefaults(defineProps<{
    name?: string | null
    durationSeconds: number
    autoResetKey?: string | number
}>(), {
    name: null,
})

const emit = defineEmits<{
    (e: 'start'): void
    (e: 'pause'): void
    (e: 'complete'): void
    (e: 'reset'): void
}>()

type TimerStatus = 'idle' | 'running' | 'paused' | 'completed'

const status = ref<TimerStatus>('idle')
const remaining = ref(props.durationSeconds)
let intervalId: ReturnType<typeof setInterval> | null = null

const progress = computed(() => {
    if (props.durationSeconds === 0) {
return 0
}

    return ((props.durationSeconds - remaining.value) / props.durationSeconds) * 100
})

const formattedTime = computed(() => {
    const total = remaining.value
    const hours = Math.floor(total / 3600)
    const minutes = Math.floor((total % 3600) / 60)
    const seconds = total % 60

    if (hours > 0) {
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
    }

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
})

const circumference = computed(() => 2 * Math.PI * 44)
const strokeDashoffset = computed(() => {
    return circumference.value - (progress.value / 100) * circumference.value
})

function start() {
    if (remaining.value <= 0) {
return
}

    status.value = 'running'
    emit('start')
    tick()
}

function pause() {
    status.value = 'paused'
    emit('pause')
    clearTimerInterval()
}

function resume() {
    if (remaining.value <= 0) {
return
}

    status.value = 'running'
    emit('start')
    tick()
}

function reset() {
    clearTimerInterval()
    remaining.value = props.durationSeconds
    status.value = 'idle'
    emit('reset')
}

function tick() {
    clearTimerInterval()
    intervalId = setInterval(() => {
        if (remaining.value > 0) {
            remaining.value--
        }

        if (remaining.value <= 0) {
            clearTimerInterval()
            remaining.value = 0
            status.value = 'completed'
            emit('complete')
            notifyComplete()
        }
    }, 1000)
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

function clearTimerInterval() {
    if (intervalId !== null) {
        clearInterval(intervalId)
        intervalId = null
    }
}

function notifyComplete() {
    // Vibrate on mobile
    if ('vibrate' in navigator) {
        navigator.vibrate([200, 100, 200, 100, 200])
    }

    // Play a subtle beep sound
    try {
        const audioCtx = new AudioContext()
        const oscillator = audioCtx.createOscillator()
        const gainNode = audioCtx.createGain()

        oscillator.connect(gainNode)
        gainNode.connect(audioCtx.destination)

        oscillator.frequency.value = 800
        oscillator.type = 'sine'
        gainNode.gain.value = 0.3

        oscillator.start()
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.5)
        oscillator.stop(audioCtx.currentTime + 0.5)
    } catch {
        // Audio not available
    }
}

// Auto-reset when the parent changes the key (e.g., navigating to a different step)
watch(
    () => props.autoResetKey,
    () => {
        reset()
    },
)

onUnmounted(() => {
    clearTimerInterval()
})
</script>

<template>
    <div class="flex items-center gap-3">
        <!-- Circular progress indicator -->
        <div class="relative h-16 w-16 shrink-0">
            <svg class="h-16 w-16 -rotate-90" viewBox="0 0 100 100">
                <!-- Background circle -->
                <circle
                    cx="50"
                    cy="50"
                    r="44"
                    fill="none"
                    stroke-width="6"
                    class="stroke-muted"
                />
                <!-- Progress circle -->
                <circle
                    cx="50"
                    cy="50"
                    r="44"
                    fill="none"
                    stroke-width="6"
                    stroke-linecap="round"
                    class="transition-all duration-1000"
                    :class="status === 'completed' ? 'stroke-green-500' : 'stroke-primary'"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="strokeDashoffset"
                />
            </svg>
            <!-- Timer icon or time in center -->
            <div class="absolute inset-0 flex items-center justify-center">
                <span
                    v-if="status === 'idle'"
                    class="text-xs font-medium text-muted-foreground"
                >
                    <Timer class="h-4 w-4" />
                </span>
                <span
                    v-else
                    class="text-xs font-bold"
                    :class="status === 'completed' ? 'text-green-500' : 'text-foreground'"
                >
                    {{ formattedTime }}
                </span>
            </div>
        </div>

        <!-- Timer info and controls -->
        <div class="flex flex-col gap-1">
            <span class="text-sm font-medium">{{ name ?? t('recipes.timer.label') }}</span>
            <span v-if="status === 'idle'" class="text-xs text-muted-foreground">
                {{ formatDuration(props.durationSeconds) }}
            </span>
            <span v-else-if="status === 'completed'" class="text-xs font-medium text-green-500">
                {{ t('recipes.timer.completed') }}
            </span>
            <div class="flex gap-1">
                <Button
                    v-if="status === 'idle'"
                    variant="outline"
                    size="sm"
                    class="h-7 px-2"
                    @click="start"
                >
                    <Play class="mr-1 h-3 w-3" />
                </Button>
                <Button
                    v-else-if="status === 'running'"
                    variant="outline"
                    size="sm"
                    class="h-7 px-2"
                    @click="pause"
                >
                    <Pause class="mr-1 h-3 w-3" />
                    Pausar
                </Button>
                <Button
                    v-else-if="status === 'paused'"
                    variant="outline"
                    size="sm"
                    class="h-7 px-2"
                    @click="resume"
                >
                    <Play class="mr-1 h-3 w-3" />
                    Reanudar
                </Button>
                <Button
                    v-if="status !== 'idle'"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2"
                    @click="reset"
                >
                    <RotateCcw class="h-3 w-3" />
                </Button>
            </div>
        </div>
    </div>
</template>
