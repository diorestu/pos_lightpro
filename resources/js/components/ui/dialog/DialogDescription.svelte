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

<p
    class={cn(
        'text-xs text-muted-foreground mt-1 mb-4 leading-relaxed',
        className
    )}
    {...rest}
>
    {@render children?.()}
</p>
