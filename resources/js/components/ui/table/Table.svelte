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

<div class="relative w-full overflow-auto">
    <table data-slot="table" class={cn('w-full caption-bottom text-sm', className)} {...rest}>
        {@render children?.()}
    </table>
</div>
