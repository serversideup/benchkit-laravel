export function resolvePage(name) {
    const pagePath = `./Pages/${name}.vue`;

    const pages = import.meta.glob('./Pages/**/*.vue');

    if( !pages[pagePath] ) {
        throw new Error(`Page not found: ${pagePath}`);
    }

    return typeof pages[pagePath] === 'function' 
        ? pages[pagePath]()
        : pages[pagePath];
}