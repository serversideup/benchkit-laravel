// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

// Nuxt's stylistic preset defaults to 2-space indentation. This repo's
// .editorconfig sets 4 for everything except YAML, and the code here is written
// that way, so the preset is what's out of step — not the 1,400 lines it would
// otherwise want to reformat against the project's own standard.
export default withNuxt(
    {
        rules: {
            '@stylistic/indent': ['error', 4],
            '@stylistic/indent-binary-ops': ['error', 4],
            // Vue templates are governed by a separate rule from script blocks.
            'vue/html-indent': ['error', 4]
        }
    }
)
