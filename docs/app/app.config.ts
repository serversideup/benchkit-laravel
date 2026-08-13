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
        /**
         * The docs page header. The eyebrow is the same tracked mono rule that
         * opens every section on the homepage, so a docs page and a marketing
         * section announce themselves in one voice. Title is Inter, tightened
         * at display size the way the homepage headlines are.
         */
        pageHeader: {
            slots: {
                root: 'relative border-b border-default py-10',
                headline: 'mb-4 flex items-center gap-3 font-mono text-[11px] font-normal uppercase tracking-[0.16em] text-neutral-500 before:h-px before:w-6 before:bg-flame-500/70 before:content-[\'\']',
                title: 'text-3xl sm:text-4xl text-balance font-semibold leading-[1.1] tracking-[-0.03em] text-highlighted',
                description: 'text-lg text-pretty leading-relaxed text-muted'
            }
        },
        prose: {
            /**
             * Heading scale for docs content: 24 / 18 / 16, semibold rather
             * than bold, tightened as it gets larger. Each heading sits far
             * from the section above it and close to the copy it introduces,
             * so the page reads as groups instead of an even ladder of text.
             */
            h1: {
                slots: {
                    base: 'text-4xl text-balance font-semibold tracking-[-0.03em] text-highlighted mb-8'
                }
            },
            h2: {
                slots: {
                    base: 'text-2xl text-balance font-semibold tracking-[-0.025em] text-highlighted mt-16 mb-5'
                }
            },
            h3: {
                slots: {
                    base: 'text-lg font-semibold tracking-[-0.015em] text-highlighted mt-12 mb-3'
                }
            },
            h4: {
                slots: {
                    base: 'text-base font-semibold tracking-[-0.01em] text-highlighted mt-8 mb-2'
                }
            },
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
        /**
         * Right rail. Its headings are labels, not headlines, so they take the
         * mono voice and step back to let the page's own headings lead.
         */
        contentToc: {
            slots: {
                title: 'truncate font-mono text-[11px] font-normal uppercase tracking-[0.16em] text-neutral-500'
            }
        },
        pageLinks: {
            slots: {
                title: 'font-mono text-[11px] font-normal uppercase tracking-[0.16em] text-neutral-500'
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
        title: 'On this page',
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
