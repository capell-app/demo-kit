@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'widget',
])

@php
    use Capell\DemoKit\Support\HomepageDemoContent;
    use Capell\Frontend\Facades\Frontend;
    use Capell\Hero\Actions\ResolveHeroBackgroundDataAction;

    $rawHomepageContent = $widget->getMeta('content', []);
    $homepageContent = HomepageDemoContent::mergeForWidget($widget->key, is_array($rawHomepageContent) ? $rawHomepageContent : []);
    $homepageText = static fn (string $key, string $fallback = ''): string => (string) data_get($homepageContent, $key, $fallback);
    $homepageItems = static function (string $key, array $fallback = []) use ($homepageContent): array {
        $items = data_get($homepageContent, $key, $fallback);

        return is_array($items) ? $items : $fallback;
    };

    $capellHeroBackground = null;
    $capellHeroBackgroundResolver = ResolveHeroBackgroundDataAction::class;

    if (class_exists($capellHeroBackgroundResolver) && view()->exists('capell-hero::components.hero.background')) {
        $capellHeroBackground = $capellHeroBackgroundResolver::run(Frontend::theme(), $widget);
    }
@endphp

<x-capell-theme-foundation::widget.wrapper
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :$widget
    @class([
        'capell-widget-homepage-section relative overflow-hidden text-[#1a1c1b] dark:text-slate-100',
        'bg-[#faf9f7]' => $widget->key !== 'capell-home-final-cta',
        'bg-transparent' => $widget->key === 'capell-home-final-cta',
    ])
>
    @once
        <style>
            .dark .capell-widget-homepage-section.bg-\[\#faf9f7\] {
                background-color: rgb(2 6 23);
            }

            .dark .capell-widget-homepage-section :where(article, div, a, button)[class*='bg-white'],
            .dark .capell-widget-homepage-section :where(article, div, a, button)[class*='bg-slate-50'] {
                background-color: rgb(15 23 42 / 0.84);
            }

            .dark .capell-widget-homepage-section :where(article, div, a, button)[class*='border-slate'],
            .dark .capell-widget-homepage-section :where(article, div, a, button)[class*='border-[#'] {
                border-color: rgb(255 255 255 / 0.12);
            }

            .dark .capell-widget-homepage-section :where(h2, h3, p, span, a, button)[class*='text-slate-950'],
            .dark .capell-widget-homepage-section :where(h2, h3, p, span, a, button)[class*='text-[#1a1c1b]'] {
                color: rgb(248 250 252);
            }

            .dark .capell-widget-homepage-section :where(p, span)[class*='text-slate-600'] {
                color: rgb(203 213 225);
            }

            .capell-home-hero-grid {
                display: grid;
                gap: 2.5rem;
                padding-widget: 3rem;
            }

            .widget-capell-home-hero-command-center .hero-background,
            .widget-capell-home-hero-command-center .capell-hero-background {
                left: 50%;
                right: auto;
                width: 100vw;
                transform: translateX(-50%);
            }

            .capell-home-hero-title {
                max-width: 18ch;
            }

            .capell-home-hero-highlight {
                border-color: #b7c8d9;
                border-color: color-mix(in srgb, var(--theme-primary, #315f8f) 28%, transparent);
                background: #e8eff6;
                background: color-mix(in srgb, var(--theme-primary, #315f8f) 12%, var(--theme-surface, #ffffff));
                color: #24496f;
                color: color-mix(in srgb, var(--theme-primary, #315f8f) 78%, var(--theme-foreground, #1a1c1b));
            }

            .dark .capell-home-hero-highlight {
                border-color: #7895b3;
                border-color: color-mix(in srgb, var(--theme-primary, #315f8f) 52%, #ffffff);
                background: #172a3d;
                background: color-mix(in srgb, var(--theme-primary, #315f8f) 32%, var(--theme-surface, #020617));
                color: #e7f0f8;
                color: color-mix(in srgb, var(--theme-primary, #315f8f) 30%, #ffffff);
            }

            .capell-home-hero-carousel {
                --swiper-pagination-bullet-horizontal-gap: 0.2rem;
            }
            @property
            --capell-home-hero-progress {
                           syntax: '<angle>';
                           inherits: false;
                           initial-value: 0deg;
                       }
            @keyframes
            capellHomeHeroProgress {
                           from {
                               --capell-home-hero-progress: 0deg;
                           }

                           to {
                               --capell-home-hero-progress: 360deg;
                           }
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
                           transition: box-shadow 160ms ease;
                           width: 1rem;
                       }

                       .capell-home-hero-carousel-controls .swiper-pagination-bullet::before {
                           background: conic-gradient(
                               rgb(49 95 143 / 0.38) var(--capell-home-hero-progress),
                               rgb(154 166 181 / 0.12) 0
                           );
                           border-radius: inherit;
                           content: '';
                           inset: -0.2rem;
                           opacity: 0;
                           position: absolute;
                       }

                       .capell-home-hero-carousel-controls .swiper-pagination-bullet::after {
                           background: #9aa6b5;
                           border-radius: inherit;
                           content: '';
                           inset: 0.34rem;
                           position: absolute;
                       }

                       .capell-home-hero-carousel-controls .swiper-pagination-bullet-active {
                           box-shadow: inset 0 0 0 1px #315f8f;
                       }

                       .capell-home-hero-carousel-controls .swiper-pagination-bullet-active::before {
                           animation: capellHomeHeroProgress 4200ms linear forwards;
                           background: conic-gradient(
                               rgb(49 95 143 / 0.38) var(--capell-home-hero-progress),
                               rgb(154 166 181 / 0.12) 0
                           );
                           opacity: 0.55;
                       }

                       .capell-home-hero-carousel-controls .swiper-pagination-bullet-active::after {
                           background: #315f8f;
                       }
            @media(prefers-reduced-motion: reduce)
            {
                           .capell-home-hero-carousel-controls .swiper-pagination-bullet-active::before {
                               animation: none;
                               background: conic-gradient(rgb(49 95 143 / 0.38) 360deg, rgb(154 166 181 / 0.12) 0);
                           }
                       }
            @media(min-width: 1024px)
            {
                           .capell-home-hero-grid {
                               align-items: center;
                               grid-template-columns: minmax(0, 0.82fr) minmax(30rem, 1.18fr);
                               padding-widget: 3.5rem 4rem;
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

    @switch ($widget->key)
        @case ('capell-home-hero-command-center')
            @php
                $heroCarouselId = 'capell-home-hero-carousel-' . ($widget->id ?? $loop->index);
                $rawHeroSlides = $widget->getMeta('hero_slides', []);
                $heroSlides = $homepageItems('slides', is_array($rawHeroSlides) ? $rawHeroSlides : []);
                $heroHighlights = $homepageItems('highlights');

                if ($heroSlides === []) {
                    $heroSlides = is_array($rawHeroSlides) ? $rawHeroSlides : [];
                }
            @endphp

            @if ($capellHeroBackground?->enabled)
                <x-capell-hero::hero.background
                    :background="$capellHeroBackground"
                />
            @endif

            <div class="capell-home-hero-grid relative z-10">
                <section class="capell-home-hero-copy grid gap-6">
                    <p class="text-xs font-extrabold tracking-[0.08em] text-[#315f8f] uppercase">
                        {{ $homepageText('eyebrow') }}
                    </p>
                    <h1
                        class="capell-home-hero-title font-[Manrope] text-4xl leading-[1.06] font-extrabold tracking-normal text-balance text-[#1a1c1b] md:text-5xl"
                    >
                        {{ $homepageText('heading') }}
                    </h1>
                    <p class="max-w-2xl text-lg leading-8 text-[#444650]">{{ $homepageText('copy') }}</p>
                    @if ($heroHighlights !== [])
                        <ul class="flex max-w-2xl flex-wrap gap-2">
                            @foreach ($heroHighlights as $highlight)
                                <li
                                    class="capell-home-hero-highlight rounded-full border px-3.5 py-1.5 text-sm font-extrabold shadow-[0_10px_24px_rgb(26_28_27_/_0.06)] backdrop-blur-sm"
                                >
                                    {{ $highlight }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="flex flex-wrap gap-3">
                        <a
                            class="inline-flex min-h-12 items-center justify-center rounded-md border border-[#315f8f] bg-[#315f8f] px-5 font-extrabold text-white no-underline hover:bg-[#24496f]"
                            href="{{ $homepageText('primary_url') }}"
                        >
                            {{ $homepageText('primary_label') }}
                        </a>
                        <a
                            class="inline-flex min-h-12 items-center justify-center rounded-md border border-[#c7ced8] bg-white px-5 font-extrabold text-[#1a1c1b] no-underline hover:border-[#315f8f] hover:bg-[#f4f3f1]"
                            href="{{ $homepageText('secondary_url') }}"
                        >
                            {{ $homepageText('secondary_label') }}
                        </a>
                    </div>
                </section>
                <section
                    class="overflow-hidden rounded-md border border-[#d9dee6] bg-white shadow-[0_18px_44px_rgb(26_28_27_/_0.08)]"
                    aria-label="{{ $homepageText('system_board_label') }}"
                >
                    <div
                        class="capell-home-hero-carousel swiper"
                        data-carousel="1"
                        data-carousel-autoplay="1"
                        data-carousel-autoplay-delay="4200"
                        data-carousel-disable-on-interaction="0"
                        data-carousel-effect="fade"
                        data-carousel-id="{{ $heroCarouselId }}"
                        data-carousel-loop="0"
                        data-carousel-pagination="1"
                        data-carousel-pause-on-hover="0"
                        data-carousel-rewind="1"
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
                                            class="text-xs font-extrabold text-[#315f8f] uppercase"
                                        >
                                            {{ $slide['label'] ?? '' }}
                                        </span>
                                        <strong class="text-[#1a1c1b]">
                                            {{ $slide['value'] ?? '' }}
                                        </strong>
                                        <em
                                            class="text-xs font-bold text-[#5f6670] not-italic"
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
                            aria-label="{{ $homepageText('pagination_label') }}"
                        ></div>
                    </div>
                </section>
            </div>
            @break
        @case ('capell-home-proof-strip')
            <div
                class="[&::-webkit-scrollbar]:hidden flex snap-x [scrollbar-width:none] gap-4 overflow-x-auto py-4 md:grid md:grid-cols-4 md:gap-0 md:overflow-visible"
                aria-label="{{ $homepageText('label') }}"
            >
                @foreach ($homepageItems('metrics') as $metric)
                    <div
                        class="min-w-full snap-start border border-slate-200 bg-white p-5 md:min-w-0"
                    >
                        <strong
                            class="widget font-[Manrope] text-4xl leading-none font-extrabold text-[#315f8f]"
                        >
                            {{ $metric['value'] ?? '' }}
                        </strong>
                        <span
                            class="widget mt-2 text-sm font-bold text-slate-600"
                        >
                            {{ $metric['label'] ?? '' }}
                        </span>
                    </div>
                @endforeach
            </div>
            @break
        @case ('capell-home-demo-showcase')
            <div class="grid gap-6 py-10 md:py-14">
                <div class="max-w-3xl">
                    <p class="text-xs font-extrabold tracking-[0.08em] text-[#315f8f] uppercase">
                        {{ $homepageText('eyebrow') }}
                    </p>
                    <h2
                        class="mt-3 max-w-[18ch] font-[Manrope] text-3xl leading-[1.08] font-extrabold text-balance text-slate-950 md:text-5xl"
                    >
                        {{ $homepageText('heading') }}
                    </h2>
                    <p class="mt-4 text-lg leading-8 text-slate-600">{{ $homepageText('copy') }}</p>
                </div>
                <div
                    class="overflow-hidden rounded-lg border border-slate-200 bg-white p-2"
                >
                    <x-capell::image-source
                        :image="$widget->getMeta('image_source')"
                        alt="{{ $homepageText('image_alt') }}"
                        class="w-full object-cover"
                        style="height: 18rem"
                    />
                </div>
                <div
                    class="[&::-webkit-scrollbar]:hidden flex snap-x [scrollbar-width:none] gap-4 overflow-x-auto pb-3 md:grid md:grid-cols-3 md:overflow-visible md:pb-0"
                >
                    @foreach ($homepageItems('cards') as $card)
                        <article
                            class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                        >
                            <p class="text-xs font-extrabold tracking-[0.08em] text-[#315f8f] uppercase">
                                {{ $card['eyebrow'] ?? '' }}
                            </p>
                            <h3
                                class="mt-3 text-xl leading-tight font-extrabold text-slate-950"
                            >
                                {{ $card['title'] ?? '' }}
                            </h3>
                            @if (filled($card['copy'] ?? null))
                                <p class="mt-3 text-base leading-7 text-slate-600">{{ $card['copy'] }}</p>
                            @endif

                            @if (is_array($card['badges'] ?? null))
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach ($card['badges'] as $badge)
                                        <span
                                            class="rounded-md bg-slate-50 px-3 py-1 text-sm font-bold text-[#24496f]"
                                        >
                                            {{ $badge }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if (is_array($card['steps'] ?? null))
                                <ol class="mt-4 grid gap-3">
                                    @foreach ($card['steps'] as $step)
                                        <li class="grid gap-1">
                                            <strong>
                                                {{ $step['title'] ?? '' }}
                                            </strong>
                                            <span
                                                class="text-sm text-slate-600"
                                            >
                                                {{ $step['copy'] ?? '' }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
            @break
        @case ('capell-home-demo-widgets-carousel')
            @php
                $demoWidgets = $homepageItems('items');
            @endphp

            <section
                class="grid max-w-full gap-6 overflow-x-clip py-10 md:py-14"
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
                        <p class="text-xs font-extrabold tracking-[0.08em] text-[#315f8f] uppercase">
                            {{ $homepageText('eyebrow') }}
                        </p>
                        <h2
                            id="capell-demo-widgets-title"
                            class="mt-3 max-w-[18ch] font-[Manrope] text-3xl leading-[1.08] font-extrabold text-balance text-slate-950 md:text-5xl"
                        >
                            {{ $homepageText('heading') }}
                        </h2>
                        <p class="mt-4 text-lg leading-8 text-slate-600">{{ $homepageText('copy') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-slate-300 bg-white text-xl font-black text-slate-950 transition hover:border-[#315f8f] hover:text-[#315f8f] disabled:cursor-not-allowed disabled:opacity-40"
                            x-on:click="previous()"
                            x-bind:disabled="active === 0"
                            aria-label="{{ $homepageText('previous_label') }}"
                        >
                            <span aria-hidden="true">&lt;</span>
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-md border border-slate-300 bg-white text-xl font-black text-slate-950 transition hover:border-[#315f8f] hover:text-[#315f8f] disabled:cursor-not-allowed disabled:opacity-40"
                            x-on:click="next()"
                            x-bind:disabled="active === maxPage()"
                            aria-label="{{ $homepageText('next_label') }}"
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
                                                class="text-xs font-extrabold tracking-[0.08em] text-slate-500 uppercase"
                                            >
                                                {{ $widget['label'] }}
                                            </span>
                                        </div>
                                        <div>
                                            <h3
                                                class="text-xl leading-tight font-extrabold text-slate-950"
                                            >
                                                {{ $widget['title'] }}
                                            </h3>
                                            <p class="mt-3 text-base leading-7 text-slate-600">
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
                                            {{ $homepageText('state_label') }}
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
                    aria-label="{{ $homepageText('pages_label') }}"
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
                            x-bind:aria-label="`{{ $homepageText('page_button_label') }} ${index}`"
                            x-bind:aria-selected="activePage() === index - 1"
                        ></button>
                    </template>
                </div>
            </section>
            @break
        @case ('capell-extension-marketplace-showcase')
            <div
                class="grid gap-6 py-10 md:py-14 lg:grid-cols-[0.82fr_1.18fr] lg:items-start"
            >
                <div>
                    <p class="text-xs font-extrabold tracking-[0.08em] text-[#315f8f] uppercase">
                        {{ $homepageText('eyebrow') }}
                    </p>
                    <h2
                        class="mt-3 max-w-[18ch] font-[Manrope] text-3xl leading-[1.08] font-extrabold text-balance text-slate-950 md:text-5xl"
                    >
                        {{ $homepageText('heading') }}
                    </h2>
                    <p class="mt-4 text-lg leading-8 text-slate-600">{{ $homepageText('copy') }}</p>
                    <div
                        class="mt-6 overflow-hidden rounded-lg border border-slate-200 bg-white p-2"
                    >
                        <x-capell::image-source
                            :image="$widget->getMeta('image_source')"
                            alt="{{ $homepageText('image_alt') }}"
                            class="w-full object-cover"
                            style="height: 14rem"
                        />
                    </div>
                </div>
                <div
                    class="[&::-webkit-scrollbar]:hidden flex snap-x [scrollbar-width:none] gap-4 overflow-x-auto pb-3 md:grid md:overflow-visible md:pb-0"
                >
                    @foreach ($homepageItems('cards') as $card)
                        <div
                            class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                        >
                            <strong>{{ $card['title'] ?? '' }}</strong>
                            <span class="widget mt-3 text-slate-600">
                                {{ $card['copy'] ?? '' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
            @break
        @case ('capell-home-technical-pipeline')
            <div
                class="grid gap-6 py-10 md:py-14 lg:grid-cols-[0.82fr_1.18fr] lg:items-start"
            >
                <div>
                    <p class="text-xs font-extrabold tracking-[0.08em] text-[#315f8f] uppercase">
                        {{ $homepageText('eyebrow') }}
                    </p>
                    <h2
                        class="mt-3 max-w-[18ch] font-[Manrope] text-3xl leading-[1.08] font-extrabold text-balance text-slate-950 md:text-5xl"
                    >
                        {{ $homepageText('heading') }}
                    </h2>
                    <p class="mt-4 text-lg leading-8 text-slate-600">{{ $homepageText('copy') }}</p>
                </div>
                <ol
                    class="[&::-webkit-scrollbar]:hidden flex snap-x [scrollbar-width:none] gap-4 overflow-x-auto rounded-lg border border-slate-200 bg-white md:grid md:grid-cols-4 md:gap-0 md:overflow-visible"
                >
                    @foreach ($homepageItems('steps') as $step)
                        <li
                            @class([
                            'grid min-w-full snap-start gap-2 p-5 md:min-w-0',
                            'border-b border-slate-200 md:border-r md:border-b-0' => ! $loop->last,
                        ])
                        >
                            <span class="text-sm font-black text-[#315f8f]">
                                {{ $step['number'] ?? '' }}
                            </span>
                            <strong>{{ $step['title'] ?? '' }}</strong>
                            <p class="text-sm leading-6 text-slate-600">{{ $step['copy'] ?? '' }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
            @break
        @case ('capell-home-route-split')
            <div
                class="[&::-webkit-scrollbar]:hidden flex snap-x [scrollbar-width:none] gap-4 overflow-x-auto py-10 md:grid md:grid-cols-3 md:overflow-visible md:py-14"
            >
                @foreach ($homepageItems('items') as $item)
                    <a
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 text-slate-950 no-underline md:min-w-0 md:p-6"
                        href="{{ $item['url'] ?? '#' }}"
                    >
                        <span
                            class="text-xs font-extrabold tracking-[0.08em] text-[#315f8f] uppercase"
                        >
                            {{ $item['eyebrow'] ?? '' }}
                        </span>
                        <strong
                            class="widget mt-3 text-xl leading-tight font-extrabold"
                        >
                            {{ $item['title'] ?? '' }}
                        </strong>
                        <em
                            class="widget mt-4 text-sm font-bold text-slate-600 not-italic"
                        >
                            {{ $item['cta'] ?? '' }}
                        </em>
                    </a>
                @endforeach
            </div>
            @break
        @case ('capell-home-final-cta')
            <div
                class="grid gap-8 py-20 md:grid-cols-[minmax(0,1fr)_auto] md:items-center md:py-28"
            >
                <div>
                    <p class="text-xs font-extrabold tracking-[0.08em] text-slate-100 uppercase">
                        {{ $homepageText('eyebrow') }}
                    </p>
                    <h2
                        class="mt-3 max-w-2xl font-[Manrope] text-3xl leading-tight font-extrabold text-balance text-white md:text-5xl"
                    >
                        {{ $homepageText('heading') }}
                    </h2>
                    <p class="mt-4 max-w-3xl text-base leading-7 text-slate-300">{{ $homepageText('copy') }}</p>
                </div>
                <a
                    class="inline-flex min-h-12 items-center justify-center rounded-lg border border-[#315f8f] bg-[#315f8f] px-5 font-extrabold text-white no-underline hover:bg-[#24496f]"
                    href="{{ $homepageText('action_url') }}"
                >
                    {{ $homepageText('action_label') }}
                </a>
            </div>
            @break

    @endswitch
</x-capell-theme-foundation::widget.wrapper>
