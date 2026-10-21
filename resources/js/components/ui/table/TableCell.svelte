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

<td
    data-slot="table-cell"
    class={cn('p-4 align-middle [&:has([role=checkbox])]:pr-0', className)}
    {...rest}
>
    {@render children?.()}
</td>
