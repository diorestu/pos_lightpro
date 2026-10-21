<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';
    import { createSortable } from '@/lib/sortable.svelte';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from './index';

    export interface ColumnDef<T = any> {
        key: string;
        label: string;
        sortable?: boolean;
        align?: 'left' | 'center' | 'right';
        class?: string;
    }

    let {
        columns = [],
        data = [],
        defaultSortKey,
        defaultSortDir = 'asc',
        class: className = '',
        emptyMessage = 'Tidak ada data ditemukan.',
        rowSnippet,
    }: {
        columns: ColumnDef[];
        data: any[];
        defaultSortKey?: string;
        defaultSortDir?: 'asc' | 'desc';
        class?: string;
        emptyMessage?: string;
        rowSnippet?: Snippet<[{ item: any; index: number }]>;
    } = $props();

    const sorter = createSortable(() => data, defaultSortKey, defaultSortDir);
</script>

<div class={cn('relative w-full overflow-auto rounded-md border', className)}>
    <Table>
        <TableHeader>
            <TableRow>
                {#each columns as col}
                    <TableHead
                        class={cn(
                            col.align === 'right' && 'text-right',
                            col.align === 'center' && 'text-center',
                            col.class
                        )}
                        sortable={col.sortable !== false}
                        sortDirection={sorter.sortKey === col.key ? sorter.sortDirection : null}
                        onSort={() => sorter.toggleSort(col.key)}
                    >
                        {col.label}
                    </TableHead>
                {/each}
            </TableRow>
        </TableHeader>
        <TableBody>
            {#if sorter.sortedData.length === 0}
                <TableRow>
                    <TableCell colspan={columns.length} class="text-center py-8 text-muted-foreground text-xs">
                        {emptyMessage}
                    </TableCell>
                </TableRow>
            {:else}
                {#each sorter.sortedData as item, index (item.id || index)}
                    {#if rowSnippet}
                        {@render rowSnippet({ item, index })}
                    {:else}
                        <TableRow>
                            {#each columns as col}
                                <TableCell
                                    class={cn(
                                        col.align === 'right' && 'text-right',
                                        col.align === 'center' && 'text-center',
                                        'text-xs'
                                    )}
                                >
                                    {item[col.key] ?? '-'}
                                </TableCell>
                            {/each}
                        </TableRow>
                    {/if}
                {/each}
            {/if}
        </TableBody>
    </Table>
</div>
