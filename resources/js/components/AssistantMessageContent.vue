<script setup lang="ts">
import { marked } from 'marked';
import { computed } from 'vue';
import CooklangRenderer from '@/components/recipes/CooklangRenderer.vue';
import { hasCooklangSyntax } from '@/composables/useCooklangParser';

type ContentBlock =
    | { type: 'markdown'; content: string }
    | { type: 'cooklang'; content: string; stepNumber: string | null };

const props = defineProps<{
    text: string;
}>();

marked.setOptions({
    breaks: true,
    gfm: true,
});

const blocks = computed<ContentBlock[]>(() => {
    const contentBlocks: ContentBlock[] = [];
    let markdownLines: string[] = [];

    const flushMarkdown = () => {
        if (markdownLines.length === 0) {
            return;
        }

        contentBlocks.push({
            type: 'markdown',
            content: markdownLines.join('\n'),
        });
        markdownLines = [];
    };

    for (const line of props.text.split('\n')) {
        const numberedStep = line.match(/^\s*(\d+)\.\s+(.+)$/);
        const cooklangContent = numberedStep?.[2] ?? line;

        if (!hasCooklangSyntax(cooklangContent)) {
            markdownLines.push(line);
            continue;
        }

        flushMarkdown();
        contentBlocks.push({
            type: 'cooklang',
            content: cooklangContent.replace(/\\([@#~])/g, '$1'),
            stepNumber: numberedStep?.[1] ?? null,
        });
    }

    flushMarkdown();

    return contentBlocks;
});

function renderMarkdown(content: string): string {
    return marked.parse(content, { async: false }) as string;
}
</script>

<template>
    <div class="space-y-2">
        <template v-for="(block, index) in blocks" :key="index">
            <div
                v-if="block.type === 'markdown'"
                class="markdown-body"
                v-html="renderMarkdown(block.content)"
            />
            <div v-else class="flex items-start gap-2 py-1">
                <span
                    v-if="block.stepNumber"
                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                >
                    {{ block.stepNumber }}
                </span>
                <CooklangRenderer
                    :text="block.content"
                    class="min-w-0 flex-1 leading-6"
                />
            </div>
        </template>
    </div>
</template>
