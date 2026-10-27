import { router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';
import type { FlashToast } from '@/types/ui';

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    router.on('success', (event) => {
        const flash = (event.detail?.page?.props as Record<string, any>)?.flash;
        if (!flash || flash.toast) {
            return;
        }

        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
    });
}
