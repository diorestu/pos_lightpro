<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
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
    <div class="flex w-full max-w-md flex-col gap-6">
        <Link
            href={home()}
            class="flex flex-col items-center gap-2 self-center font-medium group"
        >
            <div class="flex size-16 items-center justify-center rounded-full bg-background p-1 shadow-md ring-2 ring-primary/20 transition-transform group-hover:scale-105">
                <img
                    src="/images/logo.png"
                    alt="Light Project Bali"
                    class="size-full rounded-full object-contain"
                />
            </div>
            <div class="text-center">
                <span class="text-base font-bold tracking-tight text-foreground">Light Project Bali</span>
                <span class="block text-[11px] text-muted-foreground font-medium">Digital Printing & Advertising POS</span>
            </div>
        </Link>

        <div class="flex flex-col gap-6">
            <Card class="rounded-xl">
                <CardHeader class="px-10 pt-8 pb-0 text-center">
                    <CardTitle class="text-xl">{title}</CardTitle>
                    <CardDescription>
                        {description}
                    </CardDescription>
                </CardHeader>
                <CardContent class="px-10 py-8">
                    {@render children?.()}
                </CardContent>
            </Card>
        </div>
    </div>
</div>
