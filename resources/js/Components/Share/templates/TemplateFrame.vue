<template>
    <!-- Inline styles only: this node is rasterized by html-to-image -->
    <div :style="`display: flex; flex-direction: column; width: ${width}px; height: ${height}px;`">
        <div style="background-color: #771A0D; height: 88px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; border-top-left-radius: 12px; border-top-right-radius: 12px; width: 100%;">
            <img src="/images/results/title.png" style="max-width: 475px; margin: auto; display: block;"/>
        </div>

        <div style="background-color: #0C0E12; flex: 1; min-height: 0; width: 100%; display: flex;">
            <slot />
        </div>

        <div style="background-color: #771A0D; height: 56px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; width: 100%; padding-left: 24px; padding-right: 24px;">
            <img src="/images/results/benchkit-by-server-side-up.png" style="height: 32px;"/>
            <span style="font-size: 18px; color: #CECFD2; font-family: var(--font-mono); font-weight: 500;">{{ timestamp }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    width: {
        type: Number,
        default: 1200,
    },
    height: {
        type: Number,
        default: 675,
    },
    timestamp: {
        type: String,
        required: true,
    },
});
</script>
