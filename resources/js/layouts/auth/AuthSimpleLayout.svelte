<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="flex min-h-svh flex-col items-center justify-center p-6 md:p-10 relative overflow-hidden"
    style="background-color: #A326A9; background-image: radial-gradient(circle at 50% 50%, rgba(216, 48, 190, 0.45) 0%, rgba(163, 38, 169, 0.7) 65%, rgba(95, 12, 100, 0.95) 100%), url('/images/spiral-pattern.svg'), conic-gradient(from 180deg at 50% 50%, #A326A9 0deg, #D830BE 90deg, #A326A9 180deg, #D830BE 270deg, #A326A9 360deg); background-size: cover, cover, cover; background-position: center; background-repeat: no-repeat;"
>
    <div class="w-full max-w-md rounded-2xl bg-card/95 dark:bg-card/90 backdrop-blur-xl p-8 sm:p-10 shadow-2xl border border-white/25 dark:border-white/10 relative z-10">
        <div class="flex flex-col gap-6">
            <div class="flex flex-col items-center gap-3">
                <Link
                    href={home()}
                    class="flex flex-col items-center gap-2 group text-center"
                >
                    <div
                        class="relative flex size-20 items-center justify-center rounded-full bg-background p-1 shadow-md ring-2 ring-primary/20 transition-transform group-hover:scale-105"
                    >
                        <img
                            src="/images/logo.png"
                            alt="Light Project Bali"
                            class="size-full rounded-full object-contain"
                        />
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-base font-bold tracking-tight text-foreground">Light Project Bali</span>
                        <span class="text-[11px] text-muted-foreground font-medium">Digital Printing & Advertising POS</span>
                    </div>
                </Link>
                <div class="space-y-1 text-center mt-1">
                    <h1 class="text-xl font-semibold tracking-tight text-foreground">{title}</h1>
                    {#if description}
                        <p class="text-center text-xs text-muted-foreground">
                            {description}
                        </p>
                    {/if}
                </div>
            </div>
            {@render children?.()}
        </div>
    </div>
</div>
