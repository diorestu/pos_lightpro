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

<tr
    data-slot="table-row"
    class={cn('border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted', className)}
    {...rest}
>
    {@render children?.()}
</tr>
