<script setup lang="ts">
import {
    Image,
    FileText,
    ListOrdered,
    ChefHat,
    UtensilsCrossed,
    FolderOpen,
    Tag,
    Settings,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { cn } from '@/lib/utils';

export interface SidebarSection {
    id: string;
    label: string;
}

const props = defineProps<{
    activeSection: string;
    sections: SidebarSection[];
}>();

const emit = defineEmits<{
    navigate: [sectionId: string];
}>();

const sectionIcons: Record<string, Component> = {
    cover_image: Image,
    basic_data: FileText,
    ingredients: ListOrdered,
    steps: ChefHat,
    cookware: UtensilsCrossed,
    recipe_sections: FolderOpen,
    tags: Tag,
    metadata: Settings,
};

interface OrderedSection extends SidebarSection {
    icon: Component;
}

const orderedSections = computed<OrderedSection[]>(() => {
    return props.sections.map((section) => ({
        ...section,
        icon: sectionIcons[section.id] ?? Settings,
    }));
});

function scrollToSection(sectionId: string): void {
    emit('navigate', sectionId);
}
</script>

<template>
    <nav class="sticky top-6 space-y-1">
        <button
            v-for="section in orderedSections"
            :key="section.id"
            type="button"
            :class="
                cn(
                    'flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    activeSection === section.id
                        ? 'bg-primary/10 text-primary'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                )
            "
            @click="scrollToSection(section.id)"
        >
            <component :is="section.icon" class="h-4 w-4 shrink-0" />
            <span class="truncate">{{ section.label }}</span>
        </button>
    </nav>
</template>
