import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useHousehold() {
    const page = usePage();

    // householdContext es la key compartida por HandleInertiaRequests
    const householdContext = computed(() => (page.props as any).householdContext as any);
    const activeHousehold = computed(() => householdContext.value?.active || null);
    const households = computed(() => householdContext.value?.households || []);
    const enabledModules = computed(() => (householdContext.value?.enabledModules as string[]) || []);

    // ID del hogar actual (de la URL o del default)
    const currentHouseholdId = computed(() => {
        if (typeof window === 'undefined') {
            return activeHousehold.value?.id || null;
        }

        // Extraer de la URL actual: /households/{id}/...
        const match = window.location.pathname.match(/\/households\/([^/]+)/);

        return match?.[1] || activeHousehold.value?.id || null;
    });

    // Construir URL con household en la ruta
    function householdUrl(path: string, householdId?: string): string {
        const id = householdId || currentHouseholdId.value;

        if (!id) {
return path;
}

        // Si la path ya empieza con /households/, reemplazar el ID
        if (path.startsWith('/households/')) {
            return path.replace('/households/', `/households/${id}/`);
        }

        return `/households/${id}${path}`;
    }

    // Navegar manteniendo el contexto de hogar
    function navigateTo(path: string, options?: { preserveScroll?: boolean }) {
        router.get(householdUrl(path), {}, {
            preserveState: true,
            ...options,
        });
    }

    // Cambiar de hogar
    function switchHousehold(householdId: string) {
        router.post(`/households/switch/${householdId}`);
    }

    return {
        activeHousehold,
        households,
        enabledModules,
        currentHouseholdId,
        householdUrl,
        navigateTo,
        switchHousehold,
    };
}
