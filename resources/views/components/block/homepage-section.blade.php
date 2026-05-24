@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'block',
])

<x-capell-foundation-theme::block.wrapper
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :$block
    class="capell-block-homepage-section bg-[#faf9f7] text-[#1a1c1b]"
>
    @once
        <style>
            .capell-home-hero-grid {
                display: grid;
                gap: 2.5rem;
                padding-block: 3.5rem;
            }

            .capell-home-hero-title {
                max-width: 18ch;
            }

            .capell-home-hero-carousel {
                --swiper-pagination-bullet-horizontal-gap: 0.2rem;
            }

            @property --capell-home-hero-progress {
                syntax: '<angle>';
                inherits: false;
                initial-value: 0deg;
            }

            .capell-home-hero-carousel .swiper-slide {
                height: auto;
            }

            .capell-home-hero-carousel-image {
                border: 0;
                border-radius: 0;
                height: 16rem;
                object-fit: cover;
                width: 100%;
            }

            .capell-home-hero-carousel-controls .swiper-pagination {
                align-items: center;
                display: flex;
                gap: 0.45rem;
                justify-content: center;
                position: static;
            }

            .capell-home-hero-carousel-controls .swiper-pagination-bullet {
                background: transparent;
                border: 0;
                border-radius: 999px;
                box-shadow: inset 0 0 0 1px #9aa6b5;
                cursor: pointer;
                height: 1rem;
                margin: 0;
                opacity: 1;
                padding: 0;
                position: relative;
                transition:
                    box-shadow 160ms ease,
                    transform 160ms ease;
                width: 1rem;
            }

            .capell-home-hero-carousel-controls
                .swiper-pagination-bullet::before {
                background: conic-gradient(
                    #315f8f var(--capell-home-hero-progress),
                    transparent 0
                );
                border-radius: inherit;
                content: '';
                inset: -0.22rem;
                opacity: 0;
                position: absolute;
                transform-origin: center;
            }

            .capell-home-hero-carousel-controls
                .swiper-pagination-bullet::after {
                background: #9aa6b5;
                border-radius: inherit;
                content: '';
                inset: 0.28rem;
                position: absolute;
            }

            .capell-home-hero-carousel-controls
                .swiper-pagination-bullet-active {
                animation: capellHomeHeroProgress 4200ms linear forwards;
                box-shadow: inset 0 0 0 1px #315f8f;
                transform: scale(1.05);
            }

            .capell-home-hero-carousel-controls
                .swiper-pagination-bullet-active::before {
                animation: capellHomeHeroSpin 900ms linear infinite;
                opacity: 1;
            }

            .capell-home-hero-carousel-controls
                .swiper-pagination-bullet-active::after {
                background: #315f8f;
            }

            @keyframes capellHomeHeroProgress {
                from {
                    --capell-home-hero-progress: 0deg;
                }

                to {
                    --capell-home-hero-progress: 360deg;
                }
            }

            @keyframes capellHomeHeroSpin {
                to {
                    transform: rotate(1turn);
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .capell-home-hero-carousel-controls
                    .swiper-pagination-bullet-active {
                    animation: none;
                }

                .capell-home-hero-carousel-controls
                    .swiper-pagination-bullet-active::before {
                    animation: none;
                    background: #315f8f;
                }
            }

            @media (min-width: 1024px) {
                .capell-home-hero-grid {
                    align-items: center;
                    grid-template-columns: minmax(0, 0.82fr) minmax(
                            30rem,
                            1.18fr
                        );
                    padding-block: 4.5rem 5rem;
                }

                .capell-home-hero-title {
                    max-width: 19ch;
                }

                .capell-home-hero-carousel-image {
                    height: 20rem;
                }
            }
        </style>
    @endonce

    @switch($block->key)
        @case('capell-home-hero-command-center')
            @php
                $heroCarouselId = 'capell-home-hero-carousel-' . ($block->id ?? $loop->index);
                $heroSlides = $block->getMeta('hero_slides', []);

                if (! is_array($heroSlides) || $heroSlides === []) {
                    $heroSlides = [
                        [
                            'image' => $block->getMeta('image_source'),
                            'alt' => 'Capell CMS workspace preview',
                            'label' => 'Page types',
                            'value' => 'Home, Resources, Services',
                            'status' => 'Typed',
                        ],
                        [
                            'image' => [
                                'type' => 'url',
                                'url' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
                            ],
                            'alt' => 'Capell content package dashboard preview',
                            'label' => 'Packages',
                            'value' => 'Layout Builder, SEO, Search, Publishing',
                            'status' => 'Installed',
                        ],
                        [
                            'image' => [
                                'type' => 'url',
                                'url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
                            ],
                            'alt' => 'Capell publishing workflow preview',
                            'label' => 'Workflow',
                            'value' => 'Draft, preview, approve, publish',
                            'status' => 'Traceable',
                        ],
                    ];
                }
            @endphp

            <div class="capell-home-hero-grid">
                <section class="grid gap-5">
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                    >
                        Capell CMS
                    </p>
                    <h1
                        class="capell-home-hero-title text-balance font-[Manrope] text-4xl font-extrabold leading-[1.06] tracking-normal text-[#1a1c1b] md:text-6xl"
                    >
                        Composable content infrastructure for Laravel teams
                    </h1>
                    <p class="max-w-2xl text-lg leading-8 text-[#444650]">
                        Ship multi-site CMS platforms without template sprawl:
                        typed content, editor-owned layouts, package-owned
                        rendering, static output, and diagnostics in one
                        Laravel-native system.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a
                            class="inline-flex min-h-12 items-center justify-center rounded-md border border-[#315f8f] bg-[#315f8f] px-5 font-extrabold text-white no-underline hover:bg-[#24496f]"
                            href="/resources"
                        >
                            Explore the demo
                        </a>
                        <a
                            class="inline-flex min-h-12 items-center justify-center rounded-md border border-[#c7ced8] bg-white px-5 font-extrabold text-[#1a1c1b] no-underline hover:border-[#315f8f] hover:bg-[#f4f3f1]"
                            href="/pricing"
                        >
                            View pricing
                        </a>
                    </div>
                </section>
                <section
                    class="overflow-hidden rounded-md border border-[#d9dee6] bg-white shadow-[0_18px_44px_rgb(26_28_27_/_0.08)]"
                    aria-label="Capell system board"
                >
                    <div
                        class="capell-home-hero-carousel swiper"
                        data-carousel="1"
                        data-carousel-autoplay="1"
                        data-carousel-autoplay-delay="4200"
                        data-carousel-disable-on-interaction="0"
                        data-carousel-effect="fade"
                        data-carousel-id="{{ $heroCarouselId }}"
                        data-carousel-loop="1"
                        data-carousel-pagination="1"
                        data-carousel-pause-on-hover="1"
                        data-carousel-speed="600"
                        data-carousel-watch-overflow="1"
                    >
                        <div class="swiper-wrapper">
                            @foreach ($heroSlides as $slide)
                                <div class="swiper-slide">
                                    <x-capell::image-source
                                        :image="$slide['image'] ?? null"
                                        :alt="$slide['alt'] ?? ''"
                                        class="capell-home-hero-carousel-image border-b border-[#e1e5eb]"
                                        loading="{{ $loop->first ? 'eager' : 'lazy' }}"
                                        fetchpriority="{{ $loop->first ? 'high' : null }}"
                                    />
                                    <div
                                        class="grid gap-1 border-t border-[#e1e5eb] bg-[#fbfaf7] p-3.5 sm:grid-cols-[8rem_minmax(0,1fr)_auto] sm:items-center"
                                    >
                                        <span
                                            class="text-xs font-extrabold uppercase text-[#315f8f]"
                                        >
                                            {{ $slide['label'] ?? '' }}
                                        </span>
                                        <strong class="text-[#1a1c1b]">
                                            {{ $slide['value'] ?? '' }}
                                        </strong>
                                        <em
                                            class="text-xs font-bold not-italic text-[#5f6670]"
                                        >
                                            {{ $slide['status'] ?? '' }}
                                        </em>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div
                        class="capell-home-hero-carousel-controls border-t border-[#e1e5eb] bg-[#fbfaf7] px-4 py-3"
                        data-carousel-controls="{{ $heroCarouselId }}"
                    >
                        <div
                            class="swiper-pagination"
                            aria-label="Capell system board pagination"
                        ></div>
                    </div>
                </section>
            </div>

            @break
        @case('capell-home-proof-strip')
            <div
                class="flex snap-x gap-4 overflow-x-auto py-4 [scrollbar-width:none] md:grid md:grid-cols-4 md:gap-0 md:overflow-visible [&::-webkit-scrollbar]:hidden"
                aria-label="Demo proof points"
            >
                <div
                    class="min-w-full snap-start border border-slate-200 bg-white p-5 md:min-w-0"
                >
                    <strong
                        class="block font-[Manrope] text-4xl font-extrabold leading-none text-[#315f8f]"
                    >
                        38
                    </strong>
                    <span class="mt-2 block text-sm font-bold text-slate-600">
                        packages installed
                    </span>
                </div>
                <div
                    class="min-w-full snap-start border border-slate-200 bg-white p-5 md:min-w-0"
                >
                    <strong
                        class="block font-[Manrope] text-4xl font-extrabold leading-none text-[#315f8f]"
                    >
                        7
                    </strong>
                    <span class="mt-2 block text-sm font-bold text-slate-600">
                        custom homepage blocks
                    </span>
                </div>
                <div
                    class="min-w-full snap-start border border-slate-200 bg-white p-5 md:min-w-0"
                >
                    <strong
                        class="block font-[Manrope] text-4xl font-extrabold leading-none text-[#315f8f]"
                    >
                        120+
                    </strong>
                    <span class="mt-2 block text-sm font-bold text-slate-600">
                        static pages generated
                    </span>
                </div>
                <div
                    class="min-w-full snap-start border border-slate-200 bg-white p-5 md:min-w-0"
                >
                    <strong
                        class="block font-[Manrope] text-4xl font-extrabold leading-none text-[#315f8f]"
                    >
                        4
                    </strong>
                    <span class="mt-2 block text-sm font-bold text-slate-600">
                        discovery checks
                    </span>
                </div>
            </div>

            @break
        @case('capell-home-demo-showcase')
            <div class="grid gap-6 py-10 md:py-14">
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                    >
                        What ships in the demo
                    </p>
                    <h2
                        class="mt-3 max-w-[18ch] text-balance font-[Manrope] text-3xl font-extrabold leading-[1.08] text-slate-950 md:text-5xl"
                    >
                        Custom layouts that prove the CMS can change shape
                    </h2>
                    <p class="mt-4 text-lg leading-8 text-slate-600">
                        Each homepage region uses a different composition so the
                        demo feels like a real system, not a repeated stack of
                        generic cards.
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-lg border border-slate-200 bg-white p-2"
                >
                    <x-capell::image-source
                        :image="$block->getMeta('image_source')"
                        alt="Capell demo workspace preview"
                        class="w-full object-cover"
                        style="height: 18rem"
                    />
                </div>
                <div
                    class="flex snap-x gap-4 overflow-x-auto pb-3 [scrollbar-width:none] md:grid md:grid-cols-3 md:overflow-visible md:pb-0 [&::-webkit-scrollbar]:hidden"
                >
                    <article
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                    >
                        <p
                            class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                        >
                            Editorial command center
                        </p>
                        <h3
                            class="mt-3 text-xl font-extrabold leading-tight text-slate-950"
                        >
                            Operational content, not placeholder blocks
                        </h3>
                        <p class="mt-3 text-base leading-7 text-slate-600">
                            Use block translations, page types, layout
                            containers, and package data to show how an
                            editor-owned surface stays structured.
                        </p>
                    </article>
                    <article
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                    >
                        <p
                            class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                        >
                            Package marketplace
                        </p>
                        <h3
                            class="mt-3 text-xl font-extrabold leading-tight text-slate-950"
                        >
                            Extension evidence grid
                        </h3>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span
                                class="rounded-md bg-slate-50 px-3 py-1 text-sm font-bold text-[#24496f]"
                            >
                                SEO Suite
                            </span>
                            <span
                                class="rounded-md bg-slate-50 px-3 py-1 text-sm font-bold text-[#24496f]"
                            >
                                Search
                            </span>
                            <span
                                class="rounded-md bg-slate-50 px-3 py-1 text-sm font-bold text-[#24496f]"
                            >
                                Forms
                            </span>
                            <span
                                class="rounded-md bg-slate-50 px-3 py-1 text-sm font-bold text-[#24496f]"
                            >
                                Access Gate
                            </span>
                            <span
                                class="rounded-md bg-slate-50 px-3 py-1 text-sm font-bold text-[#24496f]"
                            >
                                Newsletter
                            </span>
                            <span
                                class="rounded-md bg-slate-50 px-3 py-1 text-sm font-bold text-[#24496f]"
                            >
                                Insights
                            </span>
                        </div>
                    </article>
                    <article
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                    >
                        <p
                            class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                        >
                            Publishing workflow
                        </p>
                        <h3
                            class="mt-3 text-xl font-extrabold leading-tight text-slate-950"
                        >
                            Timeline plus checklist
                        </h3>
                        <ol class="mt-4 grid gap-3">
                            <li class="grid gap-1">
                                <strong>Model</strong>
                                <span class="text-sm text-slate-600">
                                    Types and blocks
                                </span>
                            </li>
                            <li class="grid gap-1">
                                <strong>Compose</strong>
                                <span class="text-sm text-slate-600">
                                    Layout containers
                                </span>
                            </li>
                            <li class="grid gap-1">
                                <strong>Release</strong>
                                <span class="text-sm text-slate-600">
                                    Cache and sitemap
                                </span>
                            </li>
                        </ol>
                    </article>
                </div>
            </div>

            @break
        @case('capell-home-demo-widgets-carousel')
            @php
                $demoWidgets = [
                    [
                        'code' => 'WF',
                        'label' => 'Workflow',
                        'title' => 'Editorial workflow',
                        'description' => 'Draft, review, preview, approve, and publish from one traceable content queue.',
                        'metric' => '5 states',
                    ],
                    [
                        'code' => 'TH',
                        'label' => 'Theme',
                        'title' => 'Theme controls',
                        'description' => 'Expose colors, spacing, navigation, and footer settings without leaking admin data.',
                        'metric' => '12 tokens',
                    ],
                    [
                        'code' => 'CL',
                        'label' => 'Library',
                        'title' => 'Content library',
                        'description' => 'Reusable sections and typed blocks keep page building consistent across sites.',
                        'metric' => '34 blocks',
                    ],
                    [
                        'code' => 'SI',
                        'label' => 'Insights',
                        'title' => 'Search insights',
                        'description' => 'Show what visitors search for and which pages need better content coverage.',
                        'metric' => '8 queries',
                    ],
                    [
                        'code' => 'NL',
                        'label' => 'Newsletter',
                        'title' => 'Newsletter capture',
                        'description' => 'Place package-owned signup widgets into layouts with clear consent copy.',
                        'metric' => '3 lists',
                    ],
                    [
                        'code' => 'RC',
                        'label' => 'Release',
                        'title' => 'Release checklist',
                        'description' => 'Verify cache, sitemap, assets, forms, and public output before handover.',
                        'metric' => '9 checks',
                    ],
                    [
                        'code' => 'MA',
                        'label' => 'Media',
                        'title' => 'Media automation',
                        'description' => 'Generated conversions and alt text prompts keep image-heavy pages maintainable.',
                        'metric' => '4 sizes',
                    ],
                    [
                        'code' => 'TR',
                        'label' => 'Locales',
                        'title' => 'Translation queue',
                        'description' => 'Track localized content coverage without changing the public rendering contract.',
                        'metric' => '6 locales',
                    ],
                ];
            @endphp

            <section
                class="grid gap-6 py-10 md:py-14"
                x-data="{
                    active: 0,
                    perPage: 4,
                    startX: null,
                    total: {{ count($demoWidgets) }},
                    init() {
                        this.updatePerPage()
                        window.addEventListener('resize', () => this.updatePerPage())
                    },
                    updatePerPage() {
                        this.perPage =
                            window.innerWidth >= 1024 ? 4 : window.innerWidth >= 768 ? 2 : 1
                        this.active = Math.min(this.active, this.maxPage())
                    },
                    maxPage() {
                        return Math.max(this.total - this.perPage, 0)
                    },
                    previous() {
                        this.active = Math.max(this.active - this.perPage, 0)
                    },
                    next() {
                        this.active = Math.min(this.active + this.perPage, this.maxPage())
                    },
                    go(index) {
                        this.active = Math.min(index, this.maxPage())
                    },
                    pageCount() {
                        return Math.ceil(this.total / this.perPage)
                    },
                    activePage() {
                        return Math.floor(this.active / this.perPage)
                    },
                    goPage(page) {
                        this.go(page * this.perPage)
                    },
                    trackStyle() {
                        return `transform: translateX(-${this.active * (100 / this.perPage)}%);`
                    },
                    swipeStart(event) {
                        this.startX = event.touches[0].clientX
                    },
                    swipeEnd(event) {
                        if (this.startX === null) {
                            return
                        }

                        const distance = event.changedTouches[0].clientX - this.startX

                        if (Math.abs(distance) > 40) {
                            distance < 0 ? this.next() : this.previous()
                        }

                        this.startX = null
                    },
                }"
                x-on:touchstart.passive="swipeStart($event)"
                x-on:touchend.passive="swipeEnd($event)"
                aria-labelledby="capell-demo-widgets-title"
            >
                <div
                    class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end"
                >
                    <div class="max-w-3xl">
                        <p
                            class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                        >
                            Demo widgets
                        </p>
                        <h2
                            id="capell-demo-widgets-title"
                            class="mt-3 max-w-[18ch] text-balance font-[Manrope] text-3xl font-extrabold leading-[1.08] text-slate-950 md:text-5xl"
                        >
                            Small interactive blocks that feel like a real CMS
                        </h2>
                        <p class="mt-4 text-lg leading-8 text-slate-600">
                            These package-owned widgets fill out the homepage
                            with concrete CMS behaviours while keeping the
                            public frontend static, inspectable, and safe.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-slate-300 bg-white text-xl font-black text-slate-950 transition hover:border-[#315f8f] hover:text-[#315f8f] disabled:cursor-not-allowed disabled:opacity-40"
                            x-on:click="previous()"
                            x-bind:disabled="active === 0"
                            aria-label="Previous demo widgets"
                        >
                            <span aria-hidden="true">&lt;</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-slate-300 bg-white text-xl font-black text-slate-950 transition hover:border-[#315f8f] hover:text-[#315f8f] disabled:cursor-not-allowed disabled:opacity-40"
                            x-on:click="next()"
                            x-bind:disabled="active === maxPage()"
                            aria-label="Next demo widgets"
                        >
                            <span aria-hidden="true">&gt;</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden">
                    <div
                        class="flex transition-transform duration-300 ease-out"
                        x-bind:style="trackStyle()"
                    >
                        @foreach ($demoWidgets as $widget)
                            <article
                                class="min-w-full px-2 first:pl-0 last:pr-0 md:min-w-[50%] lg:min-w-[25%]"
                            >
                                <div
                                    class="grid min-h-[18rem] content-between gap-6 rounded-lg border border-slate-200 bg-white p-5 shadow-[0_8px_24px_rgb(15_23_42_/_0.04)]"
                                >
                                    <div class="grid gap-4">
                                        <div
                                            class="flex items-center justify-between gap-4"
                                        >
                                            <span
                                                class="inline-flex h-12 w-12 items-center justify-center rounded-md bg-[#eef6f5] text-sm font-black text-[#315f8f]"
                                                aria-hidden="true"
                                            >
                                                {{ $widget['code'] }}
                                            </span>
                                            <span
                                                class="text-xs font-extrabold uppercase tracking-[0.08em] text-slate-500"
                                            >
                                                {{ $widget['label'] }}
                                            </span>
                                        </div>
                                        <div>
                                            <h3
                                                class="text-xl font-extrabold leading-tight text-slate-950"
                                            >
                                                {{ $widget['title'] }}
                                            </h3>
                                            <p
                                                class="mt-3 text-base leading-7 text-slate-600"
                                            >
                                                {{ $widget['description'] }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex items-center justify-between border-t border-slate-200 pt-4"
                                    >
                                        <span
                                            class="text-sm font-bold text-slate-500"
                                        >
                                            Demo state
                                        </span>
                                        <strong
                                            class="text-sm font-black text-[#315f8f]"
                                        >
                                            {{ $widget['metric'] }}
                                        </strong>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <div
                    class="flex justify-center gap-2"
                    role="tablist"
                    aria-label="Demo widget carousel pages"
                >
                    <template
                        x-for="index in pageCount()"
                        x-bind:key="index"
                    >
                        <button
                            type="button"
                            class="h-2.5 rounded-full transition-all"
                            x-bind:class="activePage() === index - 1 ? 'w-8 bg-[#315f8f]' : 'w-2.5 bg-slate-300'"
                            x-on:click="goPage(index - 1)"
                            x-bind:aria-label="`Show demo widget set ${index}`"
                            x-bind:aria-selected="activePage() === index - 1"
                        ></button>
                    </template>
                </div>
            </section>

            @break
        @case('capell-extension-marketplace-showcase')
            <div
                class="grid gap-6 py-10 md:py-14 lg:grid-cols-[0.82fr_1.18fr] lg:items-start"
            >
                <div>
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                    >
                        Marketplace extensions
                    </p>
                    <h2
                        class="mt-3 max-w-[18ch] text-balance font-[Manrope] text-3xl font-extrabold leading-[1.08] text-slate-950 md:text-5xl"
                    >
                        Extension pages that help teams decide
                    </h2>
                    <p class="mt-4 text-lg leading-8 text-slate-600">
                        Extension detail pages show the contract behind each
                        package: install eligibility, licence state, surfaces,
                        dependencies, frontend budget, health status,
                        documentation, feedback controls, and screenshot
                        galleries.
                    </p>
                    <div
                        class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white p-2"
                    >
                        <x-capell::image-source
                            :image="$block->getMeta('image_source')"
                            alt="Capell marketplace screenshot preview"
                            class="w-full object-cover"
                            style="height: 14rem"
                        />
                    </div>
                </div>
                <div
                    class="flex snap-x gap-4 overflow-x-auto pb-3 [scrollbar-width:none] md:grid md:overflow-visible md:pb-0 [&::-webkit-scrollbar]:hidden"
                >
                    <div
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                    >
                        <strong>See the product before installing</strong>
                        <span class="mt-3 block text-slate-600">
                            Large screenshots make admin pages, frontend
                            components, settings screens, and workflows visible
                            without leaving Capell.
                        </span>
                    </div>
                    <div
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                    >
                        <strong>Keep extension boundaries explicit</strong>
                        <span class="mt-3 block text-slate-600">
                            Surfaces, dependencies, contribution counts, and
                            performance budgets tell developers what the
                            extension adds.
                        </span>
                    </div>
                    <div
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                    >
                        <strong>Connect docs to the buying decision</strong>
                        <span class="mt-3 block text-slate-600">
                            Public and entitled documentation sit beside licence
                            status, access checks, version history, and
                            Marketplace actions.
                        </span>
                    </div>
                </div>
            </div>

            @break
        @case('capell-home-technical-pipeline')
            <div
                class="grid gap-6 py-10 md:py-14 lg:grid-cols-[0.82fr_1.18fr] lg:items-start"
            >
                <div>
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                    >
                        Release path
                    </p>
                    <h2
                        class="mt-3 max-w-[18ch] text-balance font-[Manrope] text-3xl font-extrabold leading-[1.08] text-slate-950 md:text-5xl"
                    >
                        From admin edits to verified frontend
                    </h2>
                    <p class="mt-4 text-lg leading-8 text-slate-600">
                        Capell keeps the editable CMS surface and the generated
                        public output connected through explicit ownership and
                        checks.
                    </p>
                </div>
                <ol
                    class="flex snap-x gap-4 overflow-x-auto rounded-lg border border-slate-200 bg-white [scrollbar-width:none] md:grid md:grid-cols-4 md:gap-0 md:overflow-visible [&::-webkit-scrollbar]:hidden"
                >
                    <li
                        class="grid min-w-full snap-start gap-2 border-b border-slate-200 p-5 md:min-w-0 md:border-b-0 md:border-r"
                    >
                        <span class="text-sm font-black text-[#315f8f]">
                            01
                        </span>
                        <strong>Model content</strong>
                        <p class="text-sm leading-6 text-slate-600">
                            Define typed pages, blocks, translations, media, and
                            package fields.
                        </p>
                    </li>
                    <li
                        class="grid min-w-full snap-start gap-2 border-b border-slate-200 p-5 md:min-w-0 md:border-b-0 md:border-r"
                    >
                        <span class="text-sm font-black text-[#315f8f]">
                            02
                        </span>
                        <strong>Compose layout</strong>
                        <p class="text-sm leading-6 text-slate-600">
                            Place blocks into containers that the public theme
                            renders predictably.
                        </p>
                    </li>
                    <li
                        class="grid min-w-full snap-start gap-2 border-b border-slate-200 p-5 md:min-w-0 md:border-b-0 md:border-r"
                    >
                        <span class="text-sm font-black text-[#315f8f]">
                            03
                        </span>
                        <strong>Publish safely</strong>
                        <p class="text-sm leading-6 text-slate-600">
                            Preview changes, approve releases, warm cache, and
                            generate static HTML.
                        </p>
                    </li>
                    <li class="grid min-w-full snap-start gap-2 p-5 md:min-w-0">
                        <span class="text-sm font-black text-[#315f8f]">
                            04
                        </span>
                        <strong>Verify output</strong>
                        <p class="text-sm leading-6 text-slate-600">
                            Run doctor, discovery, sitemap, and runtime asset
                            checks before handover.
                        </p>
                    </li>
                </ol>
            </div>

            @break
        @case('capell-home-route-split')
            <div
                class="flex snap-x gap-4 overflow-x-auto py-10 [scrollbar-width:none] md:grid md:grid-cols-3 md:overflow-visible md:py-14 [&::-webkit-scrollbar]:hidden"
            >
                <a
                    class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 text-slate-950 no-underline md:min-w-0 md:p-6"
                    href="/resources"
                >
                    <span
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                    >
                        Resources hub
                    </span>
                    <strong
                        class="mt-3 block text-xl font-extrabold leading-tight"
                    >
                        Technical guides and launch checklists
                    </strong>
                    <em
                        class="mt-4 block text-sm font-bold not-italic text-slate-600"
                    >
                        Read the CMS playbook
                    </em>
                </a>
                <a
                    class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 text-slate-950 no-underline md:min-w-0 md:p-6"
                    href="/pricing"
                >
                    <span
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                    >
                        Pricing
                    </span>
                    <strong
                        class="mt-3 block text-xl font-extrabold leading-tight"
                    >
                        Licensing and support for production teams
                    </strong>
                    <em
                        class="mt-4 block text-sm font-bold not-italic text-slate-600"
                    >
                        Plan the rollout
                    </em>
                </a>
                <a
                    class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 text-slate-950 no-underline md:min-w-0 md:p-6"
                    href="/contact#scoping"
                >
                    <span
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#315f8f]"
                    >
                        Contact
                    </span>
                    <strong
                        class="mt-3 block text-xl font-extrabold leading-tight"
                    >
                        Architecture, migration, and package support
                    </strong>
                    <em
                        class="mt-4 block text-sm font-bold not-italic text-slate-600"
                    >
                        Start scoping
                    </em>
                </a>
            </div>

            @break
        @case('capell-home-final-cta')
            <div
                class="my-10 grid gap-6 rounded-lg bg-slate-950 p-6 md:my-14 md:grid-cols-[minmax(0,1fr)_auto] md:items-center md:p-10"
            >
                <div>
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-slate-100"
                    >
                        Demo install
                    </p>
                    <h2
                        class="mt-3 max-w-2xl text-balance font-[Manrope] text-3xl font-extrabold leading-tight text-white md:text-5xl"
                    >
                        Show a CMS that feels assembled, verified, and ready to
                        extend.
                    </h2>
                    <p
                        class="mt-4 max-w-3xl text-base leading-7 text-slate-300"
                    >
                        The homepage demonstrates layout shapes, custom block
                        compositions, package boundaries, and public-page
                        discovery paths.
                    </p>
                </div>
                <a
                    class="inline-flex min-h-12 items-center justify-center rounded-lg border border-[#315f8f] bg-[#315f8f] px-5 font-extrabold text-white no-underline hover:bg-[#24496f]"
                    href="/contact#scoping"
                >
                    Start implementation scoping
                </a>
            </div>

            @break
    @endswitch
</x-capell-foundation-theme::block.wrapper>
