<script module lang="ts">
    export interface ColumnDef<T = any> {
        key: string;
        label: string;
        sortable?: boolean;
        align?: 'left' | 'center' | 'right';
        class?: string;
        hideable?: boolean;
        visible?: boolean;
    }
</script>

<script lang="ts">
    import type { Snippet } from 'svelte';
    import { cn } from '@/lib/utils';
    import { createSortable } from '@/lib/sortable.svelte';
    import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from './index';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import Search from '@lucide/svelte/icons/search';
    import X from '@lucide/svelte/icons/x';
    import ChevronDown from '@lucide/svelte/icons/chevron-down';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import Download from '@lucide/svelte/icons/download';
    import Inbox from '@lucide/svelte/icons/inbox';

    interface PaginationMeta {
        current_page?: number;
        last_page?: number;
        per_page?: number;
        total?: number;
        from?: number;
        to?: number;
        links?: Array<{ url: string | null; label: string; active: boolean }>;
    }

    let {
        columns = [],
        data = [],
        pagination = null,
        searchable = true,
        search = '',
        searchPlaceholder = 'Cari data...',
        onSearch,
        filtersSnippet,
        showPerPage = true,
        perPage = 15,
        perPageOptions = [10, 15, 25, 50, 100],
        onPerPageChange,
        onPageChange,
        defaultSortKey,
        defaultSortDir = 'asc',
        class: className = '',
        emptyMessage = 'Tidak ada data ditemukan.',
        rowSnippet,
        headerRightSnippet,
        showExport = true,
        exportLabel = 'Export Data',
        onExport,
    }: {
        columns: ColumnDef[];
        data: any[] | { data: any[]; links?: any[]; current_page?: number; last_page?: number; from?: number; to?: number; total?: number; per_page?: number };
        pagination?: PaginationMeta | null;
        searchable?: boolean;
        search?: string;
        searchPlaceholder?: string;
        onSearch?: (value: string) => void;
        filtersSnippet?: Snippet;
        showPerPage?: boolean;
        perPage?: number;
        perPageOptions?: number[];
        onPerPageChange?: (perPage: number) => void;
        onPageChange?: (page: number, url?: string | null) => void;
        defaultSortKey?: string;
        defaultSortDir?: 'asc' | 'desc';
        class?: string;
        emptyMessage?: string;
        rowSnippet?: Snippet<[{ item: any; index: number; isVisible: (key: string) => boolean; activeColumns: ColumnDef[] }]>;
        headerRightSnippet?: Snippet;
        showExport?: boolean;
        exportLabel?: string;
        onExport?: () => void;
    } = $props();

    // Normalise incoming data and pagination
    const itemsList = $derived(
        Array.isArray(data)
            ? data
            : (data && Array.isArray((data as any).data) ? (data as any).data : [])
    );

    const activePagination = $derived<PaginationMeta | null>(
        pagination || (!Array.isArray(data) && data && (data as any).current_page ? (data as any) : null)
    );

    const isServerPaginated = $derived(Boolean(activePagination && activePagination.total !== undefined));

    // Search state
    // svelte-ignore state_referenced_locally
    let searchValue = $state(search || '');
    $effect(() => {
        searchValue = search || '';
    });

    let searchTimeout: any = null;
    function handleSearchInput(e: Event) {
        const val = (e.currentTarget as HTMLInputElement).value;
        searchValue = val;
        if (onSearch) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                onSearch(val);
            }, 350);
        }
    }

    function clearSearch() {
        searchValue = '';
        if (onSearch) {
            onSearch('');
        }
    }

    // Column visibility
    let visibleColumnsMap = $state<Record<string, boolean>>({});
    $effect(() => {
        columns.forEach((col) => {
            if (visibleColumnsMap[col.key] === undefined) {
                visibleColumnsMap[col.key] = col.visible !== false;
            }
        });
    });

    function isVisible(key: string): boolean {
        return visibleColumnsMap[key] ?? true;
    }

    const activeColumns = $derived(columns.filter((c) => isVisible(c.key)));

    // Client-side pagination state (when not server-paginated)
    let clientPage = $state(1);
    // svelte-ignore state_referenced_locally
    let selectedPerPage = $state(perPage);
    $effect(() => {
        selectedPerPage = perPage;
    });

    function handlePerPageChange(newVal: number) {
        selectedPerPage = newVal;
        clientPage = 1;
        if (onPerPageChange) {
            onPerPageChange(newVal);
        }
    }

    // Client-side filtering when server-pagination is not used
    const filteredClientData = $derived.by(() => {
        if (isServerPaginated) return itemsList;
        if (!searchValue.trim()) return itemsList;

        const q = searchValue.toLowerCase();
        return itemsList.filter((item: any) => {
            return Object.values(item).some((val) => {
                if (val === null || val === undefined) return false;
                if (typeof val === 'object') return false;
                return String(val).toLowerCase().includes(q);
            });
        });
    });

    // Sorter integration
    const sorter = createSortable(
        () => filteredClientData,
        () => defaultSortKey,
        () => defaultSortDir
    );

    // Final displayed data
    const totalRecords = $derived(
        isServerPaginated ? (activePagination?.total ?? 0) : sorter.sortedData.length
    );

    const currentPageNum = $derived(
        isServerPaginated ? (activePagination?.current_page ?? 1) : clientPage
    );

    const totalPagesNum = $derived(
        isServerPaginated
            ? (activePagination?.last_page ?? 1)
            : Math.max(1, Math.ceil(totalRecords / selectedPerPage))
    );

    const recordFrom = $derived(
        isServerPaginated
            ? (activePagination?.from ?? (totalRecords > 0 ? 1 : 0))
            : (totalRecords > 0 ? (clientPage - 1) * selectedPerPage + 1 : 0)
    );

    const recordTo = $derived(
        isServerPaginated
            ? (activePagination?.to ?? totalRecords)
            : Math.min(clientPage * selectedPerPage, totalRecords)
    );

    const displayedData = $derived.by<any[]>(() => {
        if (isServerPaginated) {
            return sorter.sortedData as any[];
        }
        const start = (clientPage - 1) * selectedPerPage;
        return (sorter.sortedData as any[]).slice(start, start + selectedPerPage);
    });

    function goToPage(page: number, url?: string | null) {
        if (page < 1 || page > totalPagesNum) return;
        if (isServerPaginated) {
            let targetUrl = url;
            if (!targetUrl && activePagination?.links) {
                if (page === currentPageNum - 1 && activePagination.links[0]?.url) {
                    targetUrl = activePagination.links[0].url;
                } else if (page === currentPageNum + 1 && activePagination.links[activePagination.links.length - 1]?.url) {
                    targetUrl = activePagination.links[activePagination.links.length - 1].url;
                } else {
                    const match = activePagination.links.find((l) => l.label === String(page));
                    if (match?.url) targetUrl = match.url;
                }
            }
            if (!targetUrl && typeof window !== 'undefined') {
                const u = new URL(window.location.href);
                u.searchParams.set('page', String(page));
                targetUrl = u.pathname + u.search;
            }
            if (onPageChange) {
                onPageChange(page, targetUrl);
            }
        } else {
            clientPage = page;
        }
    }

    function defaultExportCsv() {
        const rowsToExport = itemsList.length > 0 ? itemsList : displayedData;
        if (!rowsToExport || rowsToExport.length === 0) {
            alert('Tidak ada data untuk diekspor.');
            return;
        }

        const exportCols = activeColumns.filter((c) => c.key !== 'actions');
        const headers = exportCols.map((c) => `"${c.label.replace(/"/g, '""')}"`).join(',');
        const rows = rowsToExport.map((item: any) => {
            return exportCols
                .map((c) => {
                    const val = item[c.key] ?? '';
                    return `"${String(val).replace(/"/g, '""')}"`;
                })
                .join(',');
        });

        const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers, ...rows].join('\r\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `export_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function handleExport() {
        if (onExport) {
            onExport();
        } else {
            defaultExportCsv();
        }
    }
</script>

<div class={cn('space-y-3', className)}>
    <!-- Snippet Reusable Pagination & View Records (Matches exact specification) -->
    {#snippet paginationControls()}
        <div class="flex flex-wrap items-center gap-3 text-sm">
            {#if showPerPage}
                <div class="flex items-center gap-2">
                    <span class="text-sm font-normal text-muted-foreground whitespace-nowrap">Tampilkan:</span>
                    <div class="relative inline-flex items-center">
                        <select
                            value={selectedPerPage}
                            onchange={(e) => handlePerPageChange(Number(e.currentTarget.value))}
                            class="h-9 appearance-none rounded-xl border border-input bg-background pl-3.5 pr-9 text-sm font-medium text-foreground shadow-2xs hover:bg-accent/40 focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer !bg-none"
                        >
                            {#each perPageOptions as opt}
                                <option value={opt}>{opt}</option>
                            {/each}
                        </select>
                        <ChevronDown class="pointer-events-none absolute right-3.5 size-4 opacity-50 text-foreground" />
                    </div>
                </div>
            {/if}

            <span class="text-sm font-normal text-muted-foreground whitespace-nowrap px-0.5">
                {totalRecords > 0 ? `${recordFrom}–${recordTo} of ${totalRecords}` : '0 of 0'}
            </span>

            <div class="flex items-center gap-0.5">
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md text-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-30 cursor-pointer"
                    disabled={currentPageNum <= 1}
                    onclick={() => goToPage(currentPageNum - 1)}
                    title="Halaman Sebelumnya"
                >
                    <ChevronLeft class="size-4" />
                </button>
                <button
                    type="button"
                    class="inline-flex size-8 items-center justify-center rounded-md text-foreground transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-30 cursor-pointer"
                    disabled={currentPageNum >= totalPagesNum}
                    onclick={() => goToPage(currentPageNum + 1)}
                    title="Halaman Selanjutnya"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>

            {#if showExport}
                <button
                    type="button"
                    onclick={handleExport}
                    class="h-9 inline-flex items-center gap-2 rounded-xl border border-input bg-background px-3.5 text-sm font-medium text-foreground shadow-2xs hover:bg-accent hover:text-accent-foreground cursor-pointer transition-colors"
                    title="Export Data"
                >
                    <Download class="size-4 shrink-0 text-foreground" />
                    <span>{exportLabel}</span>
                </button>
            {/if}
        </div>
    {/snippet}

    <!-- Head Table Card: Filter Pencarian (Kiri) & Filter Kolom, Pagination, View Record Per Page (Kanan) -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 bg-card p-3 rounded-t-lg border border-b-0 shadow-2xs">
        <!-- Sisi Kiri: Filter Pencarian & Filter Kustom Halaman -->
        <div class="flex flex-1 flex-wrap items-center gap-2.5">
            {#if searchable}
                <div class="relative w-full sm:w-64 md:w-72">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                    <Input
                        type="text"
                        value={searchValue}
                        oninput={handleSearchInput}
                        placeholder={searchPlaceholder}
                        class="h-9 pl-8 pr-8 text-xs sm:text-sm bg-background rounded-xl"
                    />
                    {#if searchValue}
                        <button
                            type="button"
                            onclick={clearSearch}
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground p-0.5 rounded-sm"
                            title="Hapus pencarian"
                        >
                            <X class="size-3.5" />
                        </button>
                    {/if}
                </div>
            {/if}

            {#if filtersSnippet}
                {@render filtersSnippet()}
            {/if}
        </div>

        <!-- Sisi Kanan: View Record Per Page & Pagination -->
        <div class="flex flex-wrap items-center justify-end gap-3 shrink-0">
            {#if headerRightSnippet}
                {@render headerRightSnippet()}
            {/if}

            <!-- View Record Per Page & Pagination (Screenshot specification) -->
            {@render paginationControls()}
        </div>
    </div>

    <!-- Table Container -->
    <div class="relative w-full overflow-x-auto rounded-b-lg border border-t bg-card shadow-2xs">
        <Table>
            <TableHeader class="bg-muted/40">
                <TableRow>
                    {#each activeColumns as col (col.key)}
                        <TableHead
                            class={cn(
                                col.align === 'right' && 'text-right',
                                col.align === 'center' && 'text-center',
                                'text-xs font-semibold py-3',
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
                {#if displayedData.length === 0}
                    <TableRow>
                        <TableCell colspan={Math.max(1, activeColumns.length)} class="text-center py-12 text-muted-foreground text-xs">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <Inbox class="size-8 opacity-40 text-muted-foreground" />
                                <span class="font-medium">{emptyMessage}</span>
                            </div>
                        </TableCell>
                    </TableRow>
                {:else}
                    {#each displayedData as item, index (item.id || index)}
                        {#if rowSnippet}
                            {@render rowSnippet({ item, index, isVisible, activeColumns })}
                        {:else}
                            <TableRow class="hover:bg-muted/30 transition-colors">
                                {#each activeColumns as col (col.key)}
                                    <TableCell
                                        class={cn(
                                            col.align === 'right' && 'text-right',
                                            col.align === 'center' && 'text-center',
                                            'text-xs py-2.5',
                                            col.class
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

        <!-- Bottom Pagination Bar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t bg-card text-xs text-muted-foreground">
            <div>
                Menampilkan <span class="font-semibold text-foreground">{recordFrom}</span> sampai <span class="font-semibold text-foreground">{recordTo}</span> dari <span class="font-semibold text-foreground">{totalRecords}</span> total data
            </div>

            <!-- Pagination & View Records Controls as shown in screenshot -->
            {@render paginationControls()}
        </div>
    </div>
</div>
