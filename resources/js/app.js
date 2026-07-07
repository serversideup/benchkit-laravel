import './bootstrap';

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePage } from './resolvePage';

// The tab title is managed by useDocumentTitle (idle/running/completed
// states), not by Inertia Head components
createInertiaApp({
    resolve: name => {
        return resolvePage(name)
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el)
    },
})