<script lang="ts">
    import ArrowDown from '@lucide/svelte/icons/arrow-down';
    import ArrowUp from '@lucide/svelte/icons/arrow-up';
    import ArrowUpDown from '@lucide/svelte/icons/arrow-up-down';
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';

    type SortDirection = 'asc' | 'desc' | null | undefined;

    let {
        class: className = '',
        sortable = false,
        sortDirection = null,
        onSort,
        children,
        ...rest
    }: {
        class?: string;
        sortable?: boolean;
        sortDirection?: SortDirection;
        onSort?: () => void;
        children?: Snippet;
        [key: string]: unknown;
    } = $props();
</script>

<th
    data-slot="table-head"
    class={cn(
        'h-10 px-4 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0 select-none',
        sortable && 'cursor-pointer hover:text-foreground transition-colors group',
        className
    )}
    onclick={sortable ? onSort : undefined}
    aria-sort={sortDirection === 'asc' ? 'ascending' : sortDirection === 'desc' ? 'descending' : undefined}
    {...rest}
>
    {#if sortable}
        <div class="inline-flex items-center gap-1.5 w-full">
            <span class="truncate">{@render children?.()}</span>
            <span class="inline-flex shrink-0">
                {#if sortDirection === 'asc'}
                    <ArrowUp class="size-3.5 text-primary" />
                {:else if sortDirection === 'desc'}
                    <ArrowDown class="size-3.5 text-primary" />
                {:else}
                    <ArrowUpDown class="size-3.5 text-muted-foreground/40 group-hover:text-muted-foreground transition-colors" />
                {/if}
            </span>
        </div>
    {:else}
        {@render children?.()}
    {/if}
</th>
