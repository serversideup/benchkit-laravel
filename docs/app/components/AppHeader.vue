<script setup lang="ts">
import type { ContentNavigationItem } from '@nuxt/content'
import type { ButtonProps } from '@nuxt/ui'

const navigation = inject<Ref<ContentNavigationItem[]>>('navigation')

const { header } = useAppConfig()

/**
 * app.config.ts values widen to `string`, while UButton wants literal unions,
 * so the variant/color have to be asserted back. Defaults are applied after the
 * spread rather than before it: declaring `variant` on both sides of a spread
 * is a type error, and "config wins over default" is the intent either way.
 */
const buttonProps = (link: Record<string, unknown>): ButtonProps => ({
    ...link,
    color: (link.color ?? 'neutral') as ButtonProps['color'],
    variant: (link.variant ?? 'ghost') as ButtonProps['variant'],
    size: link.size as ButtonProps['size']
})
</script>

<template>
    <UHeader
        class="bg-black"
        :ui="{ center: 'flex-1' }"
        :to="header?.to || '/'"
    >
        <template #title>
            <NuxtImg
                v-if="header?.logo?.dark || header?.logo?.light"
                :src="header?.logo?.dark || header?.logo?.light"
                :alt="header?.logo?.alt"
                class="w-44 xl:w-52 shrink-0"
            />

            <span v-else-if="header?.title">
                {{ header.title }}
            </span>
        </template>

        <template #right>
            <UContentSearchButton
                v-if="header?.search"
                class="lg:hidden"
            />

            <UContentSearchButton
                v-if="header?.search"
                :collapsed="false"
                variant="ghost"
                :label="'Search'"
                :size="'xl'"
                :kbds="[]"
                class="hidden lg:flex cursor-pointer font-bold"
            />

            <template v-if="header?.links">
                <UButton
                    v-for="(link, index) of header.links"
                    :key="index"
                    class="hidden lg:flex font-bold"
                    v-bind="buttonProps(link)"
                />
            </template>
        </template>

        <template #body>
            <UContentNavigation
                highlight
                :navigation="navigation?.[0]?.children"
            />
        </template>
    </UHeader>
</template>
