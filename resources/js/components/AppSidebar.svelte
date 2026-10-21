<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ArrowUpDown from '@lucide/svelte/icons/arrow-up-down';
    import BarChart3 from '@lucide/svelte/icons/bar-chart-3';
    import Boxes from '@lucide/svelte/icons/boxes';
    import Clock from '@lucide/svelte/icons/clock';
    import LayoutDashboard from '@lucide/svelte/icons/layout-dashboard';
    import Package from '@lucide/svelte/icons/package';
    import Printer from '@lucide/svelte/icons/printer';
    import Receipt from '@lucide/svelte/icons/receipt';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import Tags from '@lucide/svelte/icons/tags';
    import Users from '@lucide/svelte/icons/users';
    import type { Snippet } from 'svelte';
    import AppLogo from '@/components/AppLogo.svelte';
    import NavUser from '@/components/NavUser.svelte';
    import {
        Sidebar,
        SidebarContent,
        SidebarFooter,
        SidebarGroup,
        SidebarGroupLabel,
        SidebarHeader,
        SidebarMenu,
        SidebarMenuButton,
        SidebarMenuItem,
    } from '@/components/ui/sidebar';
    import { currentUrlState } from '@/lib/currentUrl.svelte';

    let {
        children,
    }: {
        children?: Snippet;
    } = $props();

    const url = currentUrlState();

    const menuGroups = [
        {
            title: 'Transaksi',
            items: [
                { title: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
                { title: 'Kasir (POS)', href: '/pos', icon: ShoppingCart },
                { title: 'Riwayat Penjualan', href: '/orders', icon: Receipt },
            ],
        },
        {
            title: 'Katalog & Bahan',
            items: [
                { title: 'Produk Percetakan', href: '/products', icon: Package },
                { title: 'Kategori Produk', href: '/categories', icon: Tags },
                { title: 'Bahan Baku', href: '/raw-materials', icon: Boxes },
                { title: 'Pelanggan', href: '/customers', icon: Users },
            ],
        },
        {
            title: 'Operasional',
            items: [
                { title: 'Shift Kasir', href: '/shifts', icon: Clock },
            ],
        },
        {
            title: 'Laporan',
            items: [
                { title: 'Laporan Penjualan', href: '/reports', icon: BarChart3 },
                { title: 'Laporan Stok (In/Out)', href: '/stock-reports', icon: ArrowUpDown },
            ],
        },
        {
            title: 'Pengaturan',
            items: [
                { title: 'Pengaturan Nota', href: '/settings/receipt', icon: Printer },
            ],
        },
    ];
</script>

<Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild>
                    {#snippet children(props)}
                        <Link
                            {...props}
                            href="/dashboard"
                            class={props.class}
                        >
                            <AppLogo />
                        </Link>
                    {/snippet}
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarHeader>

    <SidebarContent class="gap-2">
        {#each menuGroups as group}
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/80">
                    {group.title}
                </SidebarGroupLabel>
                <SidebarMenu>
                    {#each group.items as item (item.href)}
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                asChild
                                isActive={url.isCurrentUrl(item.href, url.currentUrl)}
                                tooltip={item.title}
                            >
                                {#snippet children(props)}
                                    <Link
                                        {...props}
                                        href={item.href}
                                        class={props.class}
                                    >
                                        <item.icon class="size-4 shrink-0" />
                                        <span class="font-medium">{item.title}</span>
                                    </Link>
                                {/snippet}
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    {/each}
                </SidebarMenu>
            </SidebarGroup>
        {/each}
    </SidebarContent>

    <SidebarFooter>
        <NavUser />
    </SidebarFooter>
</Sidebar>
{@render children?.()}
