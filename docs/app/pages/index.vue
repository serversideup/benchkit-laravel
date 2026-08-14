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

/**
 * The home page describes a piece of software, so it says so. Without this it
 * claimed to be a generic web page, which tells a search engine or an AI crawler
 * nothing about what BenchKit is, what it costs, or where the source lives.
 */
useSchemaOrg([
    defineSoftwareApp({
        name: 'BenchKit for Laravel',
        description,
        applicationCategory: 'DeveloperApplication',
        operatingSystem: 'Linux',
        offers: { '@type': 'Offer', 'price': '0', 'priceCurrency': 'USD' },
        license: 'https://github.com/serversideup/benchkit-laravel/blob/main/LICENSE'
    })
])
</script>
