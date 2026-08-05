<script setup>
// inherited attrs can mess up the satori parser
defineOptions({
    inheritAttrs: false
})

defineProps({
    title: {
        type: String,
        default: ''
    },
    description: {
        type: String,
        default: ''
    }
})
</script>

<template>
    <div style="width:100%;height:100%;display:flex;padding:14px;background:#E62E05;border-radius:20px;">
        <div style="width:100%;height:100%;display:flex;flex-direction:column;justify-content:space-between;background:#000000;border-radius:14px;padding:64px;">
            <div style="display:flex;">
                <span style="color:#E62E05;font-size:52px;font-weight:700;font-family:'JetBrains Mono';" v-text="'BenchKit'" />
            </div>

            <div style="display:flex;flex-direction:column;">
                <span style="color:#ffffff;font-size:76px;font-weight:700;line-height:1.05;" v-text="title" />
                <span style="color:#9ca3af;font-size:30px;margin-top:24px;" v-text="description" />
            </div>

            <div style="display:flex;">
                <span style="color:#6b7280;font-size:24px;" v-text="'serversideup.net/open-source/benchkit'" />
            </div>
        </div>
    </div>
</template>
