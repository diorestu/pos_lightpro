<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import ArrowRight from '@lucide/svelte/icons/arrow-right';
    import BarChart3 from '@lucide/svelte/icons/bar-chart-3';
    import Boxes from '@lucide/svelte/icons/boxes';
    import Clock from '@lucide/svelte/icons/clock';
    import Package from '@lucide/svelte/icons/package';
    import Printer from '@lucide/svelte/icons/printer';
    import Receipt from '@lucide/svelte/icons/receipt';
    import ShoppingCart from '@lucide/svelte/icons/shopping-cart';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
    import { toUrl } from '@/lib/utils';
    import { dashboard, login } from '@/routes';

    const auth = $derived(page.props.auth);
</script>

<AppHead title="Selamat Datang - LightPrint POS Percetakan Digital" />

<div class="min-h-screen bg-neutral-50 text-foreground flex flex-col justify-between selection:bg-primary selection:text-primary-foreground">
    <!-- Header Nav -->
    <header class="border-b bg-white/80 backdrop-blur-md sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex aspect-square size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                    <Printer class="size-5" />
                </div>
                <div>
                    <span class="font-bold text-base tracking-tight text-foreground">LightPrint POS</span>
                    <span class="block text-[11px] text-muted-foreground font-medium -mt-1">Sistem Percetakan Digital</span>
                </div>
            </div>

            <nav class="flex items-center gap-2.5">
                {#if auth.user}
                    <Link href={toUrl(dashboard())}>
                        <Button class="bg-primary hover:bg-primary/90 text-primary-foreground shadow-sm text-xs font-semibold">
                            Buka Dashboard
                            <ArrowRight class="size-3.5 ml-1.5" />
                        </Button>
                    </Link>
                {:else}
                    <Link href={toUrl(login())}>
                        <Button size="sm" class="bg-primary hover:bg-primary/90 text-primary-foreground text-xs font-semibold shadow-xs">
                            Masuk ke Sistem POS
                            <ArrowRight class="size-3.5 ml-1.5" />
                        </Button>
                    </Link>
                {/if}
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 py-12 md:py-20 flex flex-col items-center text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-medium mb-6">
            <span class="size-2 rounded-full bg-emerald-500 animate-pulse"></span>
            Aplikasi Point of Sale Siap Pakai untuk Bisnis Percetakan & Digital Printing
        </div>

        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight max-w-4xl leading-tight">
            Kelola Transaksi, Produksi Cetak, dan Stok Bahan Jadi Lebih Mudah
        </h1>

        <p class="mt-5 text-sm sm:text-base text-muted-foreground max-w-2xl leading-relaxed">
            LightPrint POS dirancang khusus untuk alur kerja percetakan: kalkulasi meter persegi (m²), potong bahan baku roll otomatis, pembayaran bertahap (DP & Pelunasan), pergantian shift kasir, dan laporan laba kotor instan.
        </p>

        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            {#if auth.user}
                <Link href="/pos">
                    <Button size="lg" class="h-11 px-6 text-sm font-semibold bg-primary hover:bg-primary/90 shadow-sm">
                        <ShoppingCart class="size-4 mr-2" />
                        Mulai Transaksi Kasir (POS)
                    </Button>
                </Link>
                <Link href={toUrl(dashboard())}>
                    <Button size="lg" variant="outline" class="h-11 px-6 text-sm font-medium">
                        Buka Dashboard
                    </Button>
                </Link>
            {:else}
                <Link href={toUrl(login())}>
                    <Button size="lg" class="h-11 px-6 text-sm font-semibold bg-primary hover:bg-primary/90 shadow-sm">
                        Masuk ke Aplikasi
                        <ArrowRight class="size-4 ml-2" />
                    </Button>
                </Link>
            {/if}
        </div>

        <!-- Features Grid -->
        <div class="mt-16 sm:mt-24 grid grid-cols-1 md:grid-cols-3 gap-6 w-full text-left">
            <Card class="border shadow-xs bg-white">
                <CardHeader class="pb-3">
                    <div class="size-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
                        <ShoppingCart class="size-5" />
                    </div>
                    <CardTitle class="text-base font-bold">Kalkulasi Fleksibel & Cepat</CardTitle>
                    <CardDescription class="text-xs">
                        Mendukung harga per meter persegi (P x L), satuan pcs, varian laminasi, mata ayam, dan finishing cetak dalam hitungan detik.
                    </CardDescription>
                </CardHeader>
            </Card>

            <Card class="border shadow-xs bg-white">
                <CardHeader class="pb-3">
                    <div class="size-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
                        <Receipt class="size-5" />
                    </div>
                    <CardTitle class="text-base font-bold">Split Payment & Termin DP</CardTitle>
                    <CardDescription class="text-xs">
                        Terima uang muka (DP), atur termin pembayaran berkala, dan catat pelunasan nota dengan riwayat pembayaran yang transparan.
                    </CardDescription>
                </CardHeader>
            </Card>

            <Card class="border shadow-xs bg-white">
                <CardHeader class="pb-3">
                    <div class="size-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
                        <Boxes class="size-5" />
                    </div>
                    <CardTitle class="text-base font-bold">Pengurangan Bahan Baku Otomatis</CardTitle>
                    <CardDescription class="text-xs">
                        Setiap banner atau stiker yang dicetak otomatis memotong stok bahan baku roll/lembar dan memberikan peringatan saat stok menipis.
                    </CardDescription>
                </CardHeader>
            </Card>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t bg-white py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-muted-foreground">
            <div>
                &copy; {new Date().getFullYear()} LightPrint POS. Hak Cipta Dilindungi.
            </div>
            <div class="flex items-center gap-4">
                <span>Versi POS 1.0 (Laravel 13 + Svelte)</span>
            </div>
        </div>
    </footer>
</div>
