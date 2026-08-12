<template>
    <div>
        <HomeHero />
        <HomeTooling />
        <HomeWorkload />
        <HomeSandbox />
        <HomeCommunity />
        <HomeQuestions />
        <HomeClosing />
    </div>
</template>

<script setup lang="ts">
const title = 'BenchKit: benchmark your Laravel stack'
const description = 'BenchKit is a free and open source benchmark for Laravel. Run it on your own hardware to see what your host, your hardware, and your PHP configuration are really doing, then share what you learn with the community.'

useSeoMeta({
    titleTemplate: '',
    title,
    ogTitle: title,
    description,
    ogDescription: description
})

defineOgImageComponent('Docs', {
    title: 'BenchKit',
    description: 'Understand true Laravel performance'
})
</script>
