<script module lang="ts">
    import { edit as editAppearance } from '@/routes/appearance';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Pengaturan Tampilan',
                href: editAppearance(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppearanceTabs from '@/components/AppearanceTabs.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
</script>

<AppHead title="Pengaturan Tampilan" />

<h1 class="sr-only">Pengaturan Tampilan</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Tema & Tampilan"
        description="Atur tema tampilan sistem untuk kenyamanan mata Anda"
    />
    <AppearanceTabs />
</div>
