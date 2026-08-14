<template>
    <UApp>
        <NuxtLoadingIndicator />

        <div class="sticky top-0 z-50">
            <ProjectSwitcherBar />

            <UBanner
                icon="i-lucide-zap"
                title="Spin up 100% replicated Laravel environments on any VPS — with Spin Pro"
                to="https://getspin.pro/?ref=benchkit"
                color="primary"
                class="text-white bg-linear-to-r from-[#E62E05] to-[#F79009] hover:opacity-90"
                target="_blank"
            />

            <AppHeader />
        </div>

        <UMain class="bg-black">
            <NuxtLayout>
                <NuxtPage />
            </NuxtLayout>
        </UMain>

        <AppFooter />

        <ClientOnly>
            <LazyUContentSearch
                :files="files"
                :navigation="navigation"
            />
        </ClientOnly>
    </UApp>
</template>

<script setup lang="ts">
import { joinURL, withoutTrailingSlash } from 'ufo'
import { ProjectSwitcherBar } from '@serversideup/project-switcher-bar'

const { seo } = useAppConfig()

/**
 * Canonical URLs, which nothing was emitting before. The site is served from a
 * sub-path of serversideup.net, so site.url is only the origin and the base URL
 * has to be joined onto it or every canonical points at a page that does not
 * exist.
 */
const route = useRoute()
const siteConfig = useSiteConfig()
const { app } = useRuntimeConfig()

/**
 * The origin is recovered from site.url rather than trusted, because the
 * deployment has had NUXT_SITE_URL set to the full sub-path URL, which joined a
 * second copy of the base onto every canonical on the site. Both values are
 * accepted now; the sitemap and OG modules already handle either.
 */
const canonicalOrigin = computed(() => {
    const site = withoutTrailingSlash(siteConfig.url)
    const base = withoutTrailingSlash(app.baseURL || '')

    return base !== '/' && site.endsWith(base) ? site.slice(0, -base.length) : site
})

useHead({
    link: [{
        rel: 'canonical',
        href: computed(() => joinURL(canonicalOrigin.value, app.baseURL, route.path))
    }]
})

const { data: navigation } = await useAsyncData('navigation', () => queryCollectionNavigation('docs'))
const { data: files } = useLazyAsyncData('search', () => queryCollectionSearchSections('docs'), {
    server: false
})

useHead({
    meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' }
    ],
    link: [
        { rel: 'icon', type: 'image/png', href: publicAsset('/favicon-96x96.png'), sizes: '96x96' },
        { rel: 'icon', type: 'image/svg+xml', href: publicAsset('/favicon.svg') },
        { rel: 'shortcut icon', href: publicAsset('/favicon.ico') },
        { rel: 'apple-touch-icon', sizes: '180x180', href: publicAsset('/apple-touch-icon.png') },
        { rel: 'manifest', href: publicAsset('/site.webmanifest') }
    ],
    htmlAttrs: {
        lang: 'en',
        class: 'dark'
    }
})

useSeoMeta({
    titleTemplate: `%s - ${seo?.siteName}`,
    ogSiteName: seo?.siteName,
    ogType: 'website',
    twitterCard: 'summary_large_image',
    // Attribution on shared cards, which is otherwise blank on X.
    twitterSite: '@serversideup'
})

provide('navigation', navigation)
</script>
