<script lang="ts">
    let {
        class: className = '',
    }: {
        class?: string;
    } = $props();
</script>

<div
    class="flex aspect-square size-8.5 shrink-0 items-center justify-center rounded-lg overflow-hidden bg-background shadow-2xs ring-1 ring-border/40 p-0.5 {className}"
>
    <img
        src="/images/logo.png"
        alt="LPRO Light Project Bali"
        class="size-full rounded-md object-contain"
    />
</div>
<div class="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden min-w-0">
    <span class="truncate font-bold tracking-tight text-foreground">Light Project Bali</span>
    <span class="truncate text-[10px] text-muted-foreground font-medium">Digital Printing POS</span>
</div>
