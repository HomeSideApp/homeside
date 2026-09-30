export const avatarFallbackColors = [
    '#ef4444',
    '#f97316',
    '#eab308',
    '#22c55e',
    '#06b6d4',
    '#3b82f6',
    '#8b5cf6',
    '#ec4899',
    '#f43f5e',
    '#14b8a6',
] as const;

export function getFallbackColor(id: string): string {
    let hash = 0;

    for (let index = 0; index < id.length; index++) {
        hash = id.charCodeAt(index) + ((hash << 5) - hash);
    }

    return avatarFallbackColors[Math.abs(hash) % avatarFallbackColors.length];
}
