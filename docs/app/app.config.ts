export default defineAppConfig({
    ui: {
        colors: {
            primary: 'flame',
            neutral: 'neutral'
        },
        banner: {
            slots: {
                icon: 'text-white size-5 shrink-0 pointer-events-none',
                title: 'text-white font-bold text-sm truncate'
            }
        },
        pageCard: {
            slots: {
                leadingIcon: 'size-12 text-primary-500',
                title: 'text-xl font-bold text-white mb-2',
                description: 'text-gray-300 text-md leading-relaxed'
            }
        },
        prose: {
            codeIcon: {
                'compose.yml': 'i-simple-icons-docker',
                'compose.yaml': 'i-simple-icons-docker',
                'docker-compose.yaml': 'i-simple-icons-docker',
                'docker-compose.yml': 'i-simple-icons-docker',
                'dockerfile': 'i-simple-icons-docker',
                'Dockerfile': 'i-simple-icons-docker',
                '.env': 'i-lucide-file-cog',
                'nginx.conf': 'i-simple-icons-nginx',
                'Terminal': 'i-lucide-terminal'
            },
            callout: {
                slots: {
                    base: 'text-white [&_a]:text-white [&_a]:hover:text-white [&_a]:hover:border-white'
                },
                variants: {
                    color: {
                        primary: { base: '[&_a]:text-white [&_a]:hover:text-white [&_a]:hover:border-white' },
                        secondary: { base: '[&_a]:text-white [&_a]:hover:text-white [&_a]:hover:border-white' },
                        success: { base: '[&_a]:text-white [&_a]:hover:text-white [&_a]:hover:border-white' },
                        info: { base: '[&_a]:text-white [&_a]:hover:text-white [&_a]:hover:border-white' },
                        warning: { base: '[&_a]:text-white [&_a]:hover:text-white [&_a]:hover:border-white' },
                        error: { base: '[&_a]:text-white [&_a]:hover:text-white [&_a]:hover:border-white' },
                        neutral: { base: '[&_a]:text-white [&_a]:hover:text-white [&_a]:hover:border-white' }
                    }
                }
            }
        },
        mode: 'dark',
        header: {
            slots: {
                right: 'flex items-center justify-end lg:flex-1 gap-3'
            }
        },
        footer: {
            slots: {
                root: 'border-t border-default',
                left: 'text-sm text-muted'
            }
        }
    },
    seo: {
        siteName: 'BenchKit'
    },
    header: {
        title: 'BenchKit',
        to: '/',
        logo: {
            alt: 'BenchKit — by Server Side Up',
            light: '/images/logos/benchkit-wide.svg',
            dark: '/images/logos/benchkit-wide.svg'
        },
        search: true,
        links: [{
            'icon': 'i-lucide-book-open',
            'to': '/docs/getting-started',
            'aria-label': 'Documentation',
            'label': 'Docs',
            'variant': 'ghost',
            'size': 'xl',
            'class': 'font-bold'
        }, {
            'icon': 'i-lucide-gauge',
            'to': '/results',
            'aria-label': 'Community results',
            'label': 'Results',
            'variant': 'ghost',
            'size': 'xl',
            'class': 'font-bold'
        }, {
            'icon': 'i-simple-icons-discord',
            'to': 'https://serversideup.net/discord',
            'target': '_blank',
            'aria-label': 'Server Side Up on Discord',
            'label': 'Discord',
            'variant': 'ghost',
            'size': 'xl',
            'class': 'font-bold'
        }, {
            'icon': 'i-simple-icons-github',
            'to': 'https://github.com/serversideup/benchkit-laravel',
            'target': '_blank',
            'aria-label': 'BenchKit on GitHub',
            'label': 'GitHub',
            'variant': 'ghost',
            'size': 'xl',
            'class': 'font-bold'
        }, {
            'trailingIcon': 'i-lucide-heart',
            'label': 'Sponsor',
            'to': 'https://github.com/sponsors/serversideup',
            'target': '_blank',
            'aria-label': 'Sponsor Server Side Up',
            'size': 'xl',
            'variant': 'outline',
            'class': 'font-bold'
        }, {
            'trailingIcon': 'i-lucide-arrow-right',
            'label': 'Get Started',
            'to': '/docs/getting-started',
            'aria-label': 'Get Started',
            'size': 'xl',
            'variant': 'solid',
            'color': 'primary',
            'class': 'font-bold'
        }]
    },
    footer: {
        credits: `⚡️ Powered by Server Side Up`,
        colorMode: false,
        links: [{
            'icon': 'i-simple-icons-discord',
            'to': 'https://serversideup.net/discord',
            'target': '_blank',
            'aria-label': 'Server Side Up on Discord'
        }, {
            'icon': 'i-simple-icons-x',
            'to': 'https://x.com/serversideup',
            'target': '_blank',
            'aria-label': 'Server Side Up on X'
        }, {
            'icon': 'i-simple-icons-github',
            'to': 'https://github.com/serversideup/benchkit-laravel',
            'target': '_blank',
            'aria-label': 'BenchKit on GitHub'
        }]
    },
    toc: {
        title: 'Table of Contents',
        bottom: {
            title: 'Community',
            edit: 'https://github.com/serversideup/benchkit-laravel/edit/main/docs/content',
            links: [{
                icon: 'i-lucide-star',
                label: 'Star on GitHub',
                to: 'https://github.com/serversideup/benchkit-laravel',
                target: '_blank'
            }, {
                icon: 'i-lucide-bell-ring',
                label: 'Subscribe',
                to: 'https://serversideup.net/subscribe',
                target: '_blank'
            }, {
                icon: 'i-lucide-handshake',
                label: 'Professional Help',
                to: 'https://serversideup.net/professional-support',
                target: '_blank'
            }]
        }
    }
})
