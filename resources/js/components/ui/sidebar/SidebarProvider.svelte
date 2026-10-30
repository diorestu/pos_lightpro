<script lang="ts">
    import type { Snippet } from 'svelte';
    import { derived, readable, writable } from 'svelte/store';
    import { onMount, setContext } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { cn } from '@/lib/utils';
    import { TooltipProvider } from '@/components/ui/tooltip';
    import {
        SIDEBAR_CONTEXT,
        SIDEBAR_COOKIE_MAX_AGE,
        SIDEBAR_COOKIE_NAME,
        SIDEBAR_KEYBOARD_SHORTCUT,
        SIDEBAR_WIDTH,
        SIDEBAR_WIDTH_ICON,
        type SidebarContext,
    } from './context';

    let {
        defaultOpen = false,
        class: className = '',
        children,
    }: {
        defaultOpen?: boolean;
        class?: string;
        children?: Snippet;
    } = $props();

    const initialOpen = () => {
        if (typeof window !== 'undefined' && window.location.pathname.startsWith('/pos')) {
            return false;
        }
        if (typeof document === 'undefined') return defaultOpen;
        const match = document.cookie.match(new RegExp('(^|;\\s*)' + SIDEBAR_COOKIE_NAME + '=([^;]*)'));
        if (match) {
            return match[2] === 'true';
        }
        return defaultOpen;
    };

    const open = writable(initialOpen());
    const openMobile = writable(false);

    let previousUrl = '';

    $effect(() => {
        const currentUrl = page?.url || '';
        const isPos = currentUrl.startsWith('/pos');
        const wasPos = previousUrl.startsWith('/pos');
        previousUrl = currentUrl;

        if (isPos) {
            open.set(false);
            openMobile.set(false);
        } else if (wasPos && typeof document !== 'undefined') {
            const match = document.cookie.match(new RegExp('(^|;\\s*)' + SIDEBAR_COOKIE_NAME + '=([^;]*)'));
            if (match && match[2] === 'true') {
                open.set(true);
            }
        }
    });

    const isMobile = readable(false, (set) => {
        if (typeof window === 'undefined') {
            return () => {};
        }

        const media = window.matchMedia('(max-width: 768px)');
        const update = () => set(media.matches);
        update();
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    });

    const state = derived(open, ($open) => ($open ? 'expanded' : 'collapsed'));

    const setOpen = (value: boolean) => {
        open.set(value);
        if (typeof document !== 'undefined') {
            document.cookie = `${SIDEBAR_COOKIE_NAME}=${value}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;
        }
    };

    const setOpenMobile = (value: boolean) => {
        openMobile.set(value);
    };

    const toggleSidebar = () => {
        if ($isMobile) {
            setOpenMobile(!$openMobile);
        } else {
            setOpen(!$open);
        }
    };

    onMount(() => {
        const handler = (event: KeyboardEvent) => {
            if (event.key === SIDEBAR_KEYBOARD_SHORTCUT && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                toggleSidebar();
            }
        };

        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    });

    const context: SidebarContext = {
        state,
        open,
        setOpen,
        isMobile,
        openMobile,
        setOpenMobile,
        toggleSidebar,
    };

    setContext(SIDEBAR_CONTEXT, context);
</script>

<TooltipProvider>
    <div
        data-slot="sidebar-wrapper"
        style={`--sidebar-width: ${SIDEBAR_WIDTH}; --sidebar-width-icon: ${SIDEBAR_WIDTH_ICON};`}
        class={cn(
            'group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full',
            className,
        )}
    >
        {@render children?.()}
    </div>
</TooltipProvider>
