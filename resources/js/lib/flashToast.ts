import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('inertia:flash', ((event: CustomEvent) => {
        const flash = event.detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    }) as EventListener);
}
