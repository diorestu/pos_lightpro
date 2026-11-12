<script lang="ts">
    let {
        class: className = '',
        alt = 'Light Project Bali',
        ...rest
    }: {
        class?: string;
        alt?: string;
        [key: string]: unknown;
    } = $props();
</script>

<img
    src="/images/logo.png"
    alt={alt}
    class={`object-contain rounded-full ${className}`}
    {...rest}
/>
