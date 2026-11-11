<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    let {
        class: className = '',
        children,
        ...rest
    }: {
        class?: string;
        children?: Snippet;
        [key: string]: unknown;
    } = $props();
</script>

<h2
    class={cn(
        'text-base sm:text-lg font-semibold tracking-tight flex items-center gap-2.5 flex-row flex-nowrap leading-snug [&>svg]:shrink-0',
        className
    )}
    {...rest}
>
    {@render children?.()}
</h2>
