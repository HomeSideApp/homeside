<script setup lang="ts">
import {
    Contrast,
    Redo2,
    RotateCcw,
    RotateCw,
    Sliders,
    Sun,
    Undo2,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { ImageProcessingPayload } from './types';

/**
 * Normalized rectangle expressed as fractions of the current image dimensions.
 */
interface CropRect {
    x: number;
    y: number;
    width: number;
    height: number;
}

/**
 * Snapshot of every editable value, used as an undo/redo step.
 */
interface EditorSnapshot {
    rotate: number;
    brightness: number;
    contrast: number;
    greyscale: boolean;
    sharpen: boolean;
    crop: CropRect;
}

type DragMode = 'create' | 'move' | 'resize';

type ResizeHandle =
    | 'nw'
    | 'n'
    | 'ne'
    | 'e'
    | 'se'
    | 's'
    | 'sw'
    | 'w';

const { t } = useI18n();

const props = defineProps<{
    open: boolean;
    imageUrl: string;
    /** Previously applied adjustments, so reopening the editor resumes instead of resetting. */
    initial?: ImageProcessingPayload | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    apply: [payload: ImageProcessingPayload];
}>();

const FULL_CROP: CropRect = { x: 0, y: 0, width: 1, height: 1 };
const MIN_CROP = 0.05;

const rotate = ref(0);
const brightness = ref(0);
const contrast = ref(0);
const greyscale = ref(false);
const sharpen = ref(false);
const crop = ref<CropRect>({ ...FULL_CROP });
const draft = ref<CropRect | null>(null);

const naturalSize = ref({ width: 0, height: 0 });
const rotatedUrl = ref<string>(props.imageUrl);
const stage = ref<HTMLElement | null>(null);

const dragMode = ref<DragMode | null>(null);
const dragHandle = ref<ResizeHandle | null>(null);
const dragOrigin = ref({ x: 0, y: 0 });
const dragSnapshot = ref<CropRect | null>(null);

const history = ref<EditorSnapshot[]>([]);
const historyIndex = ref(-1);

/**
 * Capture the current editable values as an undo step.
 */
function snapshot(): EditorSnapshot {
    return {
        rotate: rotate.value,
        brightness: brightness.value,
        contrast: contrast.value,
        greyscale: greyscale.value,
        sharpen: sharpen.value,
        crop: { ...crop.value },
    };
}

/**
 * Apply a snapshot back onto the editor state.
 */
function restore(state: EditorSnapshot): void {
    rotate.value = state.rotate;
    brightness.value = state.brightness;
    contrast.value = state.contrast;
    greyscale.value = state.greyscale;
    sharpen.value = state.sharpen;
    crop.value = { ...state.crop };
    draft.value = null;
}

/**
 * Record a new undo step, discarding any redo branch.
 */
function pushHistory(): void {
    history.value = history.value.slice(0, historyIndex.value + 1);
    history.value.push(snapshot());
    historyIndex.value = history.value.length - 1;
}

const canUndo = computed(() => historyIndex.value > 0);
const canRedo = computed(
    () => historyIndex.value < history.value.length - 1,
);

function undo(): void {
    if (!canUndo.value) {
        return;
    }

    historyIndex.value -= 1;
    restore(history.value[historyIndex.value]);
}

function redo(): void {
    if (!canRedo.value) {
        return;
    }

    historyIndex.value += 1;
    restore(history.value[historyIndex.value]);
}

/**
 * Dimensions of the image once the rotation is applied, in pixels.
 */
const rotatedSize = computed(() => {
    const swapped = rotate.value === 90 || rotate.value === 270;

    return {
        width: swapped ? naturalSize.value.height : naturalSize.value.width,
        height: swapped ? naturalSize.value.width : naturalSize.value.height,
    };
});

/**
 * Map a pointer event to normalized coordinates inside the stage.
 */
function toNormalized(event: PointerEvent): { x: number; y: number } {
    const rect = stage.value!.getBoundingClientRect();

    return {
        x: Math.min(Math.max((event.clientX - rect.left) / rect.width, 0), 1),
        y: Math.min(Math.max((event.clientY - rect.top) / rect.height, 0), 1),
    };
}

function clamp(value: number, min = 0, max = 1): number {
    return Math.min(Math.max(value, min), max);
}

/**
 * Begin a drag: create a new selection, move it, or resize it.
 */
function startCreate(event: PointerEvent): void {
    if ((event.target as HTMLElement).closest('[data-crop-ui]')) {
        return;
    }

    const point = toNormalized(event);
    dragMode.value = 'create';
    dragOrigin.value = point;
    dragSnapshot.value = null;
    draft.value = { x: point.x, y: point.y, width: 0, height: 0 };
}

function startMove(event: PointerEvent): void {
    dragMode.value = 'move';
    dragOrigin.value = toNormalized(event);
    dragSnapshot.value = { ...draft.value! };
}

function startResize(event: PointerEvent, handle: ResizeHandle): void {
    dragMode.value = 'resize';
    dragHandle.value = handle;
    dragOrigin.value = toNormalized(event);
    dragSnapshot.value = { ...draft.value! };
}

/**
 * Update the active selection while dragging.
 */
function onPointerMove(event: PointerEvent): void {
    if (!dragMode.value || !stage.value) {
        return;
    }

    const point = toNormalized(event);

    if (dragMode.value === 'create') {
        draft.value = {
            x: Math.min(dragOrigin.value.x, point.x),
            y: Math.min(dragOrigin.value.y, point.y),
            width: Math.abs(point.x - dragOrigin.value.x),
            height: Math.abs(point.y - dragOrigin.value.y),
        };

        return;
    }

    if (dragMode.value === 'move' && dragSnapshot.value) {
        const base = dragSnapshot.value;
        const dx = point.x - dragOrigin.value.x;
        const dy = point.y - dragOrigin.value.y;

        draft.value = {
            ...base,
            x: clamp(base.x + dx, 0, 1 - base.width),
            y: clamp(base.y + dy, 0, 1 - base.height),
        };

        return;
    }

    if (dragMode.value === 'resize' && dragSnapshot.value && dragHandle.value) {
        draft.value = resizeRect(
            dragSnapshot.value,
            dragHandle.value,
            point.x - dragOrigin.value.x,
            point.y - dragOrigin.value.y,
        );
    }
}

/**
 * Resize a rectangle through the given handle, keeping it inside the stage.
 */
function resizeRect(
    base: CropRect,
    handle: ResizeHandle,
    dx: number,
    dy: number,
): CropRect {
    let left = base.x;
    let top = base.y;
    let right = base.x + base.width;
    let bottom = base.y + base.height;

    if (handle.includes('w')) {
        left = clamp(base.x + dx, 0, right - MIN_CROP);
    }

    if (handle.includes('e')) {
        right = clamp(right + dx, left + MIN_CROP, 1);
    }

    if (handle.includes('n')) {
        top = clamp(base.y + dy, 0, bottom - MIN_CROP);
    }

    if (handle.includes('s')) {
        bottom = clamp(bottom + dy, top + MIN_CROP, 1);
    }

    return {
        x: left,
        y: top,
        width: right - left,
        height: bottom - top,
    };
}

/**
 * Finish the current drag, discarding selections that ended up too small.
 */
function endDrag(): void {
    if (dragMode.value === 'create' && draft.value) {
        if (
            draft.value.width < MIN_CROP ||
            draft.value.height < MIN_CROP
        ) {
            draft.value = null;
        }
    }

    dragMode.value = null;
    dragHandle.value = null;
    dragSnapshot.value = null;
}

/**
 * Fold the active selection into the committed crop.
 *
 * The selection is expressed in the coordinates of the currently visible crop, so it composes
 * with it: this is what allows cropping the already cropped preview again.
 */
function commitDraft(): void {
    if (!draft.value) {
        return;
    }

    const outer = crop.value;
    const inner = draft.value;

    crop.value = {
        x: outer.x + inner.x * outer.width,
        y: outer.y + inner.y * outer.height,
        width: outer.width * inner.width,
        height: outer.height * inner.height,
    };
    draft.value = null;
    pushHistory();
}

function cancelDraft(): void {
    draft.value = null;
    dragMode.value = null;
}

function rotateBy(degrees: number): void {
    rotate.value = (rotate.value + degrees + 360) % 360;
    // Rotation invalidates the previous crop because it changes the image space entirely.
    crop.value = { ...FULL_CROP };
    draft.value = null;
    pushHistory();
}

function toggleFilter(target: 'greyscale' | 'sharpen', value: boolean): void {
    if (target === 'greyscale') {
        greyscale.value = value;
    } else {
        sharpen.value = value;
    }

    pushHistory();
}

function commitSlider(): void {
    pushHistory();
}

function reset(): void {
    rotate.value = 0;
    brightness.value = 0;
    contrast.value = 0;
    greyscale.value = false;
    sharpen.value = false;
    crop.value = { ...FULL_CROP };
    draft.value = null;
    pushHistory();
}

function apply(): void {
    emit('apply', {
        crop:
            crop.value.width >= 0.999 && crop.value.height >= 0.999
                ? null
                : { ...crop.value },
        rotate: rotate.value,
        brightness: brightness.value,
        contrast: contrast.value,
        greyscale: greyscale.value,
        sharpen: sharpen.value,
    });
    emit('update:open', false);
}

function close(): void {
    emit('update:open', false);
}

/**
 * Keyboard shortcuts: Enter commits the selection, Escape cancels it and Ctrl+Z undoes.
 */
function onKeydown(event: KeyboardEvent): void {
    const modifier = event.ctrlKey || event.metaKey;

    if (modifier && event.key.toLowerCase() === 'z') {
        event.preventDefault();
        event.shiftKey ? redo() : undo();

        return;
    }

    if (modifier && event.key.toLowerCase() === 'y') {
        event.preventDefault();
        redo();

        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        commitDraft();

        return;
    }

    if (event.key === 'Escape') {
        cancelDraft();
    }
}

/**
 * Render the source image with the current rotation baked in.
 *
 * Baking keeps the crop math simple: the crop rectangle always lives in the coordinates of the
 * image the user actually sees, and the backend applies rotation before cropping.
 */
async function bakeRotation(): Promise<void> {
    const degrees = rotate.value;

    if (degrees === 0) {
        rotatedUrl.value = props.imageUrl;

        return;
    }

    const image = await loadImage(props.imageUrl);
    const swapped = degrees === 90 || degrees === 270;
    const width = swapped ? image.naturalHeight : image.naturalWidth;
    const height = swapped ? image.naturalWidth : image.naturalHeight;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    context.translate(width / 2, height / 2);
    context.rotate((degrees * Math.PI) / 180);
    context.drawImage(
        image,
        -image.naturalWidth / 2,
        -image.naturalHeight / 2,
    );

    rotatedUrl.value = canvas.toDataURL('image/jpeg', 0.92);
}

function loadImage(source: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();

        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = source;
    });
}

/**
 * Position the full image so the committed crop fills the stage.
 */
const imageStyle = computed(() => ({
    position: 'absolute' as const,
    width: `${100 / crop.value.width}%`,
    height: `${100 / crop.value.height}%`,
    left: `${-(crop.value.x / crop.value.width) * 100}%`,
    top: `${-(crop.value.y / crop.value.height) * 100}%`,
    maxWidth: 'none',
    filter: previewFilter.value,
    userSelect: 'none' as const,
    pointerEvents: 'none' as const,
}));

const previewFilter = computed(
    () =>
        `brightness(${1 + brightness.value / 100}) contrast(${1 + contrast.value / 100})${greyscale.value ? ' grayscale(1)' : ''}`,
);

/**
 * Aspect ratio of the visible area, derived from the crop and the rotation.
 */
const stageAspect = computed(() => {
    const size = rotatedSize.value;

    if (!size.width || !size.height) {
        return '4 / 3';
    }

    const width = (crop.value.width * size.width) / size.height;

    return `${width / crop.value.height}`;
});

/**
 * Size the preview so a tall receipt never pushes the dialog outside the browser window.
 *
 * The width is derived from the maximum height instead of clamping the height directly: clamping
 * only the height while the width stays at 100% would squash the stage and deform the image, since
 * the image is positioned as a percentage of the stage.
 */
const stageStyle = computed(() => ({
    aspectRatio: stageAspect.value,
    width: `min(100%, calc(45vh * ${stageAspect.value}))`,
}));

const draftStyle = computed(() => {
    if (!draft.value) {
        return {};
    }

    return {
        left: `${draft.value.x * 100}%`,
        top: `${draft.value.y * 100}%`,
        width: `${draft.value.width * 100}%`,
        height: `${draft.value.height * 100}%`,
    };
});

const hasAdjustments = computed(
    () =>
        draft.value !== null ||
        crop.value.width < 0.999 ||
        crop.value.height < 0.999 ||
        rotate.value !== 0 ||
        brightness.value !== 0 ||
        contrast.value !== 0 ||
        greyscale.value ||
        sharpen.value,
);

const cropHint = computed(() => {
    if (draft.value) {
        return t('economy.import.processing.selectionHint', {
            width: Math.round(draft.value.width * 100),
            height: Math.round(draft.value.height * 100),
        });
    }

    if (crop.value.width < 0.999 || crop.value.height < 0.999) {
        return t('economy.import.processing.croppedHint', {
            width: Math.round(crop.value.width * 100),
            height: Math.round(crop.value.height * 100),
        });
    }

    return t('economy.import.processing.cropHint');
});

const handles: ResizeHandle[] = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'];

const handlePosition = (handle: ResizeHandle): string => {
    const vertical = handle.includes('n')
        ? 'top-0'
        : handle.includes('s')
          ? 'bottom-0'
          : 'top-1/2';
    const horizontal = handle.includes('w')
        ? 'left-0'
        : handle.includes('e')
          ? 'right-0'
          : 'left-1/2';
    const translateY =
        vertical === 'top-1/2' ? '-translate-y-1/2' : '';
    const translateX =
        horizontal === 'left-1/2' ? '-translate-x-1/2' : '';

    return `${vertical} ${horizontal} ${translateX} ${translateY}`;
};

const resizeCursor = (handle: ResizeHandle): string =>
    ({
        nw: 'nwse-resize',
        n: 'ns-resize',
        ne: 'nesw-resize',
        e: 'ew-resize',
        se: 'nwse-resize',
        s: 'ns-resize',
        sw: 'nesw-resize',
        w: 'ew-resize',
    })[handle];

function resetEditor(): void {
    const initial = props.initial;

    rotate.value = initial?.rotate ?? 0;
    brightness.value = initial?.brightness ?? 0;
    contrast.value = initial?.contrast ?? 0;
    greyscale.value = initial?.greyscale ?? false;
    sharpen.value = initial?.sharpen ?? false;
    crop.value = initial?.crop ? { ...initial.crop } : { ...FULL_CROP };
    draft.value = null;
    history.value = [];
    historyIndex.value = -1;
    pushHistory();
}

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            dragMode.value = null;

            return;
        }

        resetEditor();
        const image = await loadImage(props.imageUrl);
        naturalSize.value = {
            width: image.naturalWidth,
            height: image.naturalHeight,
        };
        await bakeRotation();
    },
);

watch(rotate, () => {
    void bakeRotation();
});

onMounted(() => {
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('pointermove', onPointerMove);
    window.removeEventListener('pointerup', endDrag);
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="flex max-h-[90vh] max-w-4xl flex-col overflow-hidden"
        >
            <DialogHeader>
                <DialogTitle>{{
                    t('economy.import.processing.title')
                }}</DialogTitle>
                <DialogDescription>{{
                    t('economy.import.processing.description')
                }}</DialogDescription>
            </DialogHeader>

            <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto pr-1">
                <div
                    ref="stage"
                    class="relative mx-auto w-full max-w-2xl shrink-0 cursor-crosshair touch-none select-none overflow-hidden rounded-lg border bg-muted"
                    :style="stageStyle"
                    @pointerdown="startCreate"
                >
                    <img
                        :src="rotatedUrl"
                        :alt="t('economy.import.processing.preview')"
                        :style="imageStyle"
                        draggable="false"
                    />

                    <div
                        v-if="draft"
                        data-crop-ui
                        class="absolute border-2 border-primary bg-primary/10"
                        :style="draftStyle"
                        @pointerdown.stop="startMove"
                    >
                        <div
                            v-for="handle in handles"
                            :key="handle"
                            data-crop-ui
                            class="absolute size-3 rounded-sm border border-primary bg-background"
                            :class="handlePosition(handle)"
                            :style="{ cursor: resizeCursor(handle) }"
                            @pointerdown.stop="
                                startResize($event, handle)
                            "
                        />
                    </div>
                </div>

                <p class="text-center text-xs text-muted-foreground">
                    {{ cropHint }}
                </p>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    :disabled="!canUndo"
                                    :aria-label="
                                        t(
                                            'economy.import.processing.undo',
                                        )
                                    "
                                    @click="undo"
                                >
                                    <Undo2 aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {{
                                    t('economy.import.processing.undo')
                                }}
                                (Ctrl+Z)
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    :disabled="!canRedo"
                                    :aria-label="
                                        t(
                                            'economy.import.processing.redo',
                                        )
                                    "
                                    @click="redo"
                                >
                                    <Redo2 aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {{
                                    t('economy.import.processing.redo')
                                }}
                                (Ctrl+Shift+Z)
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    :aria-label="
                                        t(
                                            'economy.import.processing.rotateLeft',
                                        )
                                    "
                                    @click="rotateBy(-90)"
                                >
                                    <RotateCcw aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                t('economy.import.processing.rotateLeft')
                            }}</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="outline"
                                    size="icon-sm"
                                    :aria-label="
                                        t(
                                            'economy.import.processing.rotateRight',
                                        )
                                    "
                                    @click="rotateBy(90)"
                                >
                                    <RotateCw aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                t('economy.import.processing.rotateRight')
                            }}</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="
                                        t('economy.import.processing.reset')
                                    "
                                    @click="reset"
                                >
                                    <Sliders aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>{{
                                t('economy.import.processing.reset')
                            }}</TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <Field>
                        <FieldLabel>
                            <Sun aria-hidden="true" class="size-4" />
                            {{ t('economy.import.processing.brightness') }}
                        </FieldLabel>
                        <input
                            v-model.number="brightness"
                            type="range"
                            min="-100"
                            max="100"
                            step="5"
                            class="w-full"
                            :aria-valuetext="`${brightness}`"
                            @change="commitSlider"
                        />
                    </Field>
                    <Field>
                        <FieldLabel>
                            <Contrast aria-hidden="true" class="size-4" />
                            {{ t('economy.import.processing.contrast') }}
                        </FieldLabel>
                        <input
                            v-model.number="contrast"
                            type="range"
                            min="-100"
                            max="100"
                            step="5"
                            class="w-full"
                            :aria-valuetext="`${contrast}`"
                            @change="commitSlider"
                        />
                    </Field>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-4">
                    <div class="flex items-center gap-2">
                        <Switch
                            id="processing-greyscale"
                            :model-value="greyscale"
                            @update:model-value="
                                (value: boolean) =>
                                    toggleFilter('greyscale', value)
                            "
                        />
                        <FieldLabel for="processing-greyscale">{{
                            t('economy.import.processing.greyscale')
                        }}</FieldLabel>
                    </div>
                    <div class="flex items-center gap-2">
                        <Switch
                            id="processing-sharpen"
                            :model-value="sharpen"
                            @update:model-value="
                                (value: boolean) =>
                                    toggleFilter('sharpen', value)
                            "
                        />
                        <FieldLabel for="processing-sharpen">{{
                            t('economy.import.processing.sharpen')
                        }}</FieldLabel>
                    </div>
                </div>

                <FieldDescription class="text-center">
                    {{ t('economy.import.processing.shortcuts') }}
                </FieldDescription>
            </div>

            <DialogFooter
                class="flex shrink-0 items-center justify-between gap-2 border-t pt-4"
            >
                <Button variant="outline" @click="close">
                    <X data-icon="inline-start" aria-hidden="true" />
                    {{ t('economy.ui.close') }}
                </Button>
                <Button :disabled="!hasAdjustments" @click="apply">
                    {{ t('economy.import.processing.apply') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
