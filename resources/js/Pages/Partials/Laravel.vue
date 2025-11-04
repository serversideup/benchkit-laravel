<template>
    <Deferred data="laravel">
        <template #fallback>
            <div>Loading...</div>
        </template>

        <div class="flex flex-col mt-3">
            <div class="flex font-bold font-mono text-sm text-white h-5">
                🎨️ php artisan about
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label class="text-[#66C61C]">Environment</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Application Name</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="">
                    {{ laravel.environment.application_name }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Framework</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    <svg class="mr-2.5" xmlns="http://www.w3.org/2000/svg" width="24" height="25" viewBox="0 0 24 25" fill="none">
                        <path d="M22.1835 6.62163C22.1812 6.61185 22.1752 6.60357 22.1722 6.59379C22.1654 6.57647 22.1601 6.55841 22.1511 6.5426C22.1451 6.53207 22.136 6.52304 22.1285 6.51325C22.1187 6.50046 22.1105 6.48616 22.0992 6.47487C22.0894 6.46508 22.0773 6.45831 22.0668 6.45003C22.0548 6.44025 22.0442 6.42971 22.0307 6.42218L18.083 4.14919C17.9814 4.09048 17.8565 4.09048 17.7549 4.14919L13.8072 6.42218C13.7937 6.42971 13.7832 6.441 13.7711 6.45003C13.7606 6.45831 13.7485 6.46508 13.7388 6.47487C13.7275 6.48616 13.7192 6.50046 13.7094 6.51325C13.7019 6.52304 13.6928 6.53207 13.6868 6.5426C13.6778 6.55841 13.6718 6.57647 13.6657 6.59379C13.662 6.60282 13.6567 6.61185 13.6545 6.62163C13.6469 6.64948 13.6432 6.67808 13.6432 6.70743V11.0246L10.3533 12.919V4.47282C10.3533 4.44347 10.3496 4.41487 10.3421 4.38702C10.3398 4.37724 10.3338 4.36896 10.3308 4.35917C10.324 4.34186 10.3187 4.3238 10.3097 4.30799C10.3037 4.29746 10.2946 4.28843 10.2871 4.27864C10.2773 4.26585 10.269 4.25155 10.2578 4.24026C10.248 4.23047 10.2359 4.2237 10.2254 4.21542C10.2133 4.20563 10.2028 4.1951 10.1893 4.18757L6.24163 1.91457C6.14002 1.85587 6.01508 1.85587 5.91347 1.91457L1.96584 4.18757C1.95229 4.1951 1.94175 4.20639 1.92971 4.21542C1.91917 4.2237 1.90713 4.23047 1.89734 4.24026C1.88605 4.25155 1.87778 4.26585 1.86799 4.27864C1.86046 4.28843 1.85143 4.29746 1.84541 4.30799C1.83638 4.3238 1.83036 4.34186 1.82434 4.35917C1.82057 4.36821 1.81531 4.37724 1.81305 4.38702C1.80552 4.41487 1.80176 4.44347 1.80176 4.47282V17.9949C1.80176 18.1123 1.86498 18.2214 1.96659 18.2801L9.86261 22.8261C9.87993 22.8359 9.89874 22.8419 9.91681 22.8487C9.92509 22.8517 9.93336 22.857 9.9424 22.8593C9.97024 22.8668 9.99809 22.8705 10.0267 22.8705C10.0553 22.8705 10.0831 22.8668 10.111 22.8593C10.1185 22.857 10.1253 22.8525 10.1328 22.8502C10.1524 22.8435 10.172 22.8367 10.19 22.8269L18.086 18.2809C18.1884 18.2222 18.2509 18.1138 18.2509 17.9956V13.6785L22.0337 11.5003C22.136 11.4416 22.1985 11.3332 22.1985 11.215V6.70743C22.1947 6.67808 22.1902 6.64948 22.1835 6.62163ZM10.0207 17.6163L6.73687 15.758L10.1855 13.7725L13.9691 11.5944L17.2559 13.4865L14.8444 14.8631L10.0207 17.6163ZM17.5885 9.17085V12.919L16.2067 12.1235L14.298 11.0246V7.27644L15.6798 8.07199L17.5885 9.17085ZM17.9175 4.81377L21.2058 6.70743L17.9175 8.6011L14.6291 6.70743L17.9175 4.81377ZM7.78456 14.3965L6.40269 15.192V6.93624L8.31141 5.83737L9.69327 5.04182V13.2969L7.78456 14.3965ZM6.07379 2.57992L9.3621 4.47358L6.07379 6.36724L2.78547 4.47358L6.07379 2.57992ZM2.4543 5.04182L3.83616 5.83737L5.74488 6.93624V15.7603V15.761V15.7618C5.74488 15.7746 5.74864 15.7866 5.75015 15.7987C5.75241 15.8145 5.75241 15.831 5.75692 15.8468V15.8476C5.76069 15.8604 5.76746 15.8724 5.77273 15.8845C5.77875 15.898 5.78251 15.9131 5.79004 15.9259C5.79004 15.9259 5.79004 15.9266 5.79079 15.9266C5.79757 15.9379 5.80735 15.9477 5.81563 15.9582C5.82466 15.9703 5.83219 15.9823 5.84272 15.9928L5.84348 15.9936C5.85251 16.0026 5.86455 16.0094 5.87509 16.0177C5.88713 16.0267 5.89767 16.0373 5.91046 16.0448C5.91121 16.0448 5.91121 16.0448 5.91197 16.0455C5.91272 16.0455 5.91272 16.0463 5.91347 16.0463L9.69478 18.1861V21.9711L2.4543 17.8045V5.04182ZM17.5885 17.8045L10.3503 21.9719V18.1861L15.7107 15.1258L17.5885 14.054V17.8045ZM21.5362 11.0246L18.2464 12.919V9.17085L20.1551 8.07199L21.5369 7.27644V11.0246H21.5362Z" fill="#FF2D20"/>
                    </svg>
                    {{ laravel.environment.laravel_version }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>PHP Version</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    <svg class="mr-2.5" xmlns="http://www.w3.org/2000/svg" width="24" height="25" viewBox="0 0 24 25" fill="none">
                        <circle cx="12" cy="12.3705" r="10.5" fill="#8892BF"/>
                        <path d="M10.8294 7.87054H12.0894L11.7333 9.76371H12.8655C13.4864 9.77699 13.949 9.91575 14.2534 10.18C14.5638 10.4443 14.6551 10.9466 14.5273 11.6866L13.9156 14.9873H12.6373L13.2216 11.8353C13.2824 11.5048 13.2642 11.2703 13.1668 11.1315C13.0695 10.9928 12.8595 10.9234 12.5368 10.9234L11.5233 10.9135L10.7746 14.9873H9.51457L10.8294 7.87054Z" fill="white"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M5.05569 9.76374H7.50271C8.22094 9.77029 8.74139 9.99508 9.06406 10.4377C9.38674 10.8804 9.4932 11.485 9.38364 12.2516C9.34108 12.6019 9.24668 12.9454 9.10058 13.2825C8.96052 13.6195 8.76585 13.9234 8.51622 14.1943C8.21181 14.5379 7.8862 14.756 7.53924 14.8485C7.19227 14.9411 6.83307 14.9873 6.46182 14.9873H5.36613L5.01917 16.8705H3.75L5.05569 9.76374ZM5.57614 13.8673L6.12215 10.9036H6.30659C6.37361 10.9036 6.44355 10.9002 6.5166 10.8937C7.00363 10.8871 7.40831 10.9367 7.73098 11.0424C8.05969 11.148 8.16925 11.5479 8.05969 12.2417C7.92583 13.0678 7.66396 13.5501 7.27445 13.6888C6.88493 13.8211 6.3979 13.8837 5.81354 13.8772H5.68571C5.64918 13.8772 5.61266 13.8738 5.57614 13.8673Z" fill="white"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M18.3274 9.76374H15.8804L14.5747 16.8705H15.8439L16.1908 14.9873H17.2865C17.6578 14.9873 18.017 14.9411 18.3639 14.8485C18.7109 14.756 19.0365 14.5379 19.3409 14.1943C19.5905 13.9234 19.7852 13.6195 19.9253 13.2825C20.0714 12.9454 20.1658 12.6019 20.2083 12.2516C20.3179 11.485 20.2114 10.8804 19.8888 10.4377C19.5661 9.99508 19.0456 9.77029 18.3274 9.76374ZM16.9468 10.9036L16.4008 13.8673C16.4374 13.8738 16.4739 13.8772 16.5104 13.8772H16.6382C17.2226 13.8837 17.7096 13.8211 18.0991 13.6888C18.4887 13.5501 18.7505 13.0678 18.8844 12.2417C18.9939 11.5479 18.8844 11.148 18.5557 11.0424C18.233 10.9367 17.8283 10.8871 17.3413 10.8937C17.2682 10.9002 17.1983 10.9036 17.1313 10.9036H16.9468Z" fill="white"/>
                    </svg>
                    {{ laravel.environment.php_version }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Composer Version</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.environment.composer_version }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Environment</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.environment.environment }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Debug Mode</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center" :class="{ 'text-yellow-500': laravel.environment.debug_mode, 'text-red-500': !laravel.environment.debug_mode }">
                    {{ laravel.environment.debug_mode ? 'ENABLED' : 'DISABLED' }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>URL</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.environment.url }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Maintenance Mode</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.environment.maintenance_mode ? 'ON' : 'OFF' }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Timezone</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.environment.timezone }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Locale</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.environment.locale }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label class="text-[#66C61C]">Cache</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Config</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center" :class="{ 'text-yellow-500': laravel.cache.config, 'text-red-500': !laravel.cache.config }">
                    {{ laravel.cache.config ? 'CACHED' : 'NOT CACHED' }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Events</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center" :class="{ 'text-yellow-500': laravel.cache.events, 'text-red-500': !laravel.cache.events }">
                    {{ laravel.cache.events ? 'CACHED' : 'NOT CACHED' }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Routes</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center" :class="{ 'text-yellow-500': laravel.cache.routes, 'text-red-500': !laravel.cache.routes }">
                    {{ laravel.cache.routes ? 'CACHED' : 'NOT CACHED' }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Views</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center" :class="{ 'text-yellow-500': laravel.cache.views, 'text-red-500': !laravel.cache.views }">
                    {{ laravel.cache.views ? 'CACHED' : 'NOT CACHED' }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label class="text-[#66C61C]">Drivers</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Broadcasting</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.drivers.broadcasting }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Cache</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.drivers.cache }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Database</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.drivers.database }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Logs</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.drivers.logs.join(' / ') }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Mail</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.drivers.mail }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Queue</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.drivers.queue }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>Session</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center">
                    {{ laravel.drivers.session }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label class="text-[#66C61C]">Storage</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
            </div>
            <div class="flex font-mono mt-1 text-xs text-white h-5">
                <label>public/storage</label>
                <div class="flex-1 truncate text-clip mx-2.5">
                    {{ ".".repeat(200) }}
                </div>
                <div class="flex items-center" :class="{ 'text-yellow-500': laravel.storage['/var/www/html/public/storage'], 'text-red-500': !laravel.storage['/var/www/html/public/storage'] }">
                    {{ laravel.storage['/var/www/html/public/storage'] 
                        ? 'LINKED' 
                        : 'NOT LINKED' }}
                </div>
            </div>
        </div>
    </Deferred>
</template>

<script setup>
import { Deferred, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const laravel = computed(() => JSON.parse(usePage().props.laravel));

</script>