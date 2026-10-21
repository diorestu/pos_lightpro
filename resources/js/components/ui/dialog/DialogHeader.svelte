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

<div class={cn('flex flex-col gap-1.5 text-center sm:text-left', className)} {...rest}>
    {@render children?.()}
</div>
