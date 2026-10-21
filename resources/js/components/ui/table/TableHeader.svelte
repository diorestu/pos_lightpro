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

<thead data-slot="table-header" class={cn('[&_tr]:border-b bg-muted/40', className)} {...rest}>
    {@render children?.()}
</thead>
