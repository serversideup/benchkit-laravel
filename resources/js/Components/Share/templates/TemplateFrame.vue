<template>
    <!-- Inline styles only: this node is rasterized by html-to-image.
         Owns the chrome both share cards have in common: accent bar, brand
         row (hosting details earn the corner; otherwise the timestamp keeps
         it), and the quiet footer. -->
    <div :style="`display: flex; flex-direction: column; width: 1200px; height: 675px; background-color: #0C0E12; background-image: ${glow};`">
        <div style="height: 5px; flex-shrink: 0; background: linear-gradient(90deg, #E62E05, #F79009);"></div>

        <div style="flex: 1; min-height: 0; display: flex; flex-direction: column; padding: 36px 64px 0;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                <img src="/images/results/title.png" style="height: 36px; display: block;"/>
                <div v-if="host" style="display: flex; flex-direction: column; align-items: flex-end; text-align: right;">
                    <span v-if="host.provider" :style="`font-size: 22px; color: #F7F7F7; font-family: ${MONO}; font-weight: 600; line-height: 1.2;`">{{ host.provider }}</span>
                    <span v-if="host.details" :style="`font-size: 17px; color: #94979C; font-family: ${MONO}; margin-top: 2px;`">{{ host.details }}</span>
                </div>
                <span v-else :style="`font-size: 18px; color: #61656C; font-family: ${MONO};`">{{ timestamp }}</span>
            </div>

            <slot />
        </div>

        <div style="flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #1D222B; padding: 16px 64px 20px;">
            <img src="/images/results/benchkit-by-server-side-up.png" style="height: 28px; display: block;"/>
            <span :style="`font-size: 17px; color: #61656C; font-family: ${MONO};`"><template v-if="host">{{ timestamp }} &middot; </template>#BenchKit</span>
        </div>
    </div>
</template>

<script setup>
import { MONO } from '@/share/templateStyles';

defineProps({
    host: {
        type: Object,
        default: null,
    },
    timestamp: {
        type: String,
        required: true,
    },
    glow: {
        type: String,
        required: true,
    },
});
</script>
