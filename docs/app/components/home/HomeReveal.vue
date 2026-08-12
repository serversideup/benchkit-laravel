<script setup lang="ts">
/**
 * Fades its content up as it scrolls into view.
 *
 * Where the browser supports scroll-driven animations the `reveal` class in
 * main.css does the whole job in CSS, tied to the element's real position. That
 * is what makes a section peeking in at the bottom of a tall window still
 * animate instead of appearing fully formed ahead of the hero.
 *
 * Everything else falls back to an observer. Either way the element renders
 * *visible* on the server and is only ever hidden on the client, so content is
 * never gated behind hydration and a no-JS visit reads normally.
 */
const props = withDefaults(defineProps<{ delay?: number }>(), { delay: 0 })

const root = ref<HTMLElement | null>(null)
const revealed = ref(true)
const armed = ref(false)

onMounted(() => {
    // CSS is handling it; no observer, no work.
    if (CSS.supports('animation-timeline', 'view()')) return

    if (!('IntersectionObserver' in window)) return
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    const element = root.value
    if (!element) return

    // Already on screen, or scrolled past while JS was loading: leave it be
    // rather than hide something the reader is looking at.
    if (element.getBoundingClientRect().top < window.innerHeight) return

    revealed.value = false
    armed.value = true

    const observer = new IntersectionObserver(([entry]) => {
        if (!entry?.isIntersecting) return

        revealed.value = true
        observer.disconnect()
    }, { threshold: 0.1 })

    observer.observe(element)

    onBeforeUnmount(() => observer.disconnect())
})
</script>

<template>
    <div
        ref="root"
        class="reveal"
        :class="[
            armed ? 'transition-[opacity,transform] duration-700 ease-out' : '',
            revealed ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0'
        ]"
        :style="armed ? { transitionDelay: `${props.delay}ms` } : undefined"
    >
        <slot />
    </div>
</template>
