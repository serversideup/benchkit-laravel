/**
 * The project switcher bar ships as an ESM bundle with no bundled .d.ts, so
 * TypeScript treats its import as an implicit `any` and `nuxt typecheck` fails.
 * Declare the surface we actually use rather than turning off noImplicitAny.
 */
declare module '@serversideup/project-switcher-bar' {
    import type { DefineComponent } from 'vue'

    export const ProjectSwitcherBar: DefineComponent<Record<string, unknown>>
}
