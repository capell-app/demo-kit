@props ([
    'sections',
    'sectionClass',
    'splitSectionClass',
    'carouselClass',
    'compactCarouselClass',
    'carouselItemClass',
    'cardClass',
    'eyebrowClass',
    'headingClass',
    'introClass',
    'labelClass',
    'cardTitleClass',
    'cardCopyClass',
])

@foreach ($sections as $section)
    @php
        $layout = $section['layout'] ?? $section['variant'];
        $items = $section['items'] ?? [];
        $metrics = $section['metrics'] ?? [];
        $steps = $section['steps'] ?? [];
        $filters = $section['filters'] ?? [];
        $cta = $section['cta'] ?? [];
    @endphp

    @if ($layout === 'services-workbench')
        <section
            class="capell-demo-services-atelier service-delivery-lanes {{ $splitSectionClass }}"
        >
            <div class="grid gap-5">
                <p class="{{ $eyebrowClass }}">{{ $section['eyebrow'] }}</p>
                <h2 class="{{ $headingClass }}">{{ $section['title'] }}</h2>
                <p class="{{ $introClass }}">{{ $section['intro'] }}</p>
            </div>

            <div class="capell-demo-service-board {{ $carouselClass }}">
                @foreach ($items as $item)
                    <article class="{{ $carouselItemClass }} {{ $cardClass }}">
                        <span class="{{ $labelClass }}">
                            {{ $item['label'] }}
                        </span>
                        <h3
                            class="text-2xl leading-none font-black text-slate-950"
                        >
                            {{ $item['title'] }}
                        </h3>
                        <p class="{{ $cardCopyClass }}">{{ $item['copy'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        @if ($metrics !== [])
            <section
                class="grid gap-3 border-y border-slate-200 bg-white py-4 sm:grid-cols-2 lg:grid-cols-4"
                aria-label="Service proof points"
            >
                @foreach ($metrics as $metric)
                    <div
                        class="border border-slate-200 bg-white p-5 md:border-y-0 md:border-l-0 md:p-6"
                    >
                        <strong
                            class="widget font-[Manrope] text-3xl leading-none font-extrabold text-[#0f766e] md:text-4xl"
                        >
                            {{ $metric['value'] }}
                        </strong>
                        <span
                            class="widget mt-2 text-sm font-bold text-slate-600"
                        >
                            {{ $metric['label'] }}
                        </span>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($steps !== [])
            <section
                class="capell-demo-engineering-pipeline {{ $sectionClass }}"
            >
                <div class="grid gap-5 lg:max-w-3xl">
                    <p class="{{ $eyebrowClass }}">Engineering pipeline</p>
                    <h2 class="{{ $headingClass }}">
                        A delivery path that stays visible
                    </h2>
                </div>
                <ol
                    class="grid rounded-lg border border-slate-200 bg-white md:grid-cols-4"
                >
                    @foreach ($steps as $step)
                        <li
                            class="grid content-start gap-2 border-b border-slate-200 p-5 last:border-b-0 md:border-r md:border-b-0 md:last:border-r-0"
                        >
                            <span class="text-sm font-black text-[#0f766e]">
                                {{ $step['label'] }}
                            </span>
                            <strong
                                class="text-lg leading-snug font-extrabold text-slate-950"
                            >
                                {{ $step['title'] }}
                            </strong>
                            <p class="text-sm leading-6 text-slate-600">
                                {{ $step['copy'] }}
                            </p>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    @elseif ($layout === 'pricing-matrix')
        <section
            class="capell-demo-pricing-matrix pricing-plan-matrix {{ $sectionClass }}"
        >
            <div class="grid gap-5 lg:max-w-3xl">
                <p class="{{ $eyebrowClass }}">{{ $section['eyebrow'] }}</p>
                <h2 class="{{ $headingClass }}">{{ $section['title'] }}</h2>
                <p class="{{ $introClass }}">{{ $section['intro'] }}</p>
            </div>

            <div class="capell-demo-pricing-grid {{ $carouselClass }}">
                @foreach ($items as $item)
                    <article
                        @class ([$carouselItemClass, $cardClass, 'min-h-72', 'border-[#0f766e] bg-teal-50 shadow-[0_18px_48px_rgb(0_92_85_/_0.12)]' => $loop->iteration === 2])
                    >
                        <span
                            class="{{ $loop->iteration === 2 ? 'text-xs font-black tracking-normal text-green-700 uppercase' : $labelClass }}"
                        >
                            {{ $item['label'] }}
                        </span>
                        <h3
                            class="text-4xl leading-none font-black text-slate-950"
                        >
                            {{ $item['title'] }}
                        </h3>
                        <p class="{{ $cardCopyClass }}">{{ $item['copy'] }}</p>
                    </article>
                @endforeach
            </div>

            @if ($steps !== [])
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ($steps as $step)
                        <article
                            class="rounded-lg border border-slate-200 bg-slate-950 p-5 text-white"
                        >
                            <span
                                class="text-xs font-extrabold tracking-[0.08em] text-teal-200 uppercase"
                            >
                                {{ $step['label'] }}
                            </span>
                            <h3 class="mt-3 text-xl leading-tight font-black">
                                {{ $step['title'] }}
                            </h3>
                            <p class="mt-2 text-sm leading-6 text-slate-200">
                                {{ $step['copy'] }}
                            </p>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @elseif ($layout === 'resources-library')
        <section
            class="capell-demo-resources-library resource-featured-panel {{ $splitSectionClass }}"
        >
            <div class="grid gap-5">
                <p class="{{ $eyebrowClass }}">{{ $section['eyebrow'] }}</p>
                <h2 class="{{ $headingClass }}">{{ $section['title'] }}</h2>
                <p class="{{ $introClass }}">{{ $section['intro'] }}</p>
            </div>

            @if (($items[0] ?? null) !== null)
                @php ($featured = $items[0])
                <article
                    class="grid gap-6 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-[minmax(0,1fr)_14rem] md:items-end md:p-8"
                >
                    <div>
                        <span class="{{ $labelClass }}">
                            {{ $featured['label'] }}
                        </span>
                        <h3
                            class="mt-3 font-[Manrope] text-2xl leading-tight font-extrabold text-slate-950 md:text-4xl"
                        >
                            {{ $featured['title'] }}
                        </h3>
                        <p class="mt-4 text-base leading-7 text-slate-600">
                            {{ $featured['copy'] }}
                        </p>
                    </div>
                    <aside class="border-l-4 border-[#0f766e] bg-teal-50 p-4">
                        <strong
                            class="widget font-[Manrope] text-3xl leading-none font-extrabold text-[#0f766e]"
                        >
                            18 min
                        </strong>
                        <span class="widget mt-2 font-bold text-slate-600">
                            Architecture
                        </span>
                    </aside>
                </article>
            @endif
        </section>

        <livewire:capell-demo-kit.resources-library
            :items="array_slice($items, 1)"
            :filters="$filters"
            :cta="$cta"
        />
    @elseif ($layout === 'architecture-layers')
        <section
            class="capell-demo-architecture-map architecture-layer-map {{ $sectionClass }}"
        >
            <div class="grid gap-5 lg:max-w-3xl">
                <p class="{{ $eyebrowClass }}">{{ $section['eyebrow'] }}</p>
                <h2 class="{{ $headingClass }}">{{ $section['title'] }}</h2>
                <p class="{{ $introClass }}">{{ $section['intro'] }}</p>
            </div>

            <div class="grid gap-4">
                @foreach ($items as $item)
                    <article
                        class="grid gap-3 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-[8rem_minmax(0,1fr)] md:items-start"
                    >
                        <span class="{{ $labelClass }}">
                            {{ $item['label'] }}
                        </span>
                        <div>
                            <h3 class="{{ $cardTitleClass }}">
                                {{ $item['title'] }}
                            </h3>
                            <p class="{{ $cardCopyClass }} mt-2">
                                {{ $item['copy'] }}
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @elseif ($layout === 'faq-support')
        <section
            class="capell-demo-showcase-page capell-demo-showcase-page--faq faq-support-panel {{ $sectionClass }} max-w-4xl"
        >
            <div class="grid gap-5">
                <p class="{{ $eyebrowClass }}">{{ $section['eyebrow'] }}</p>
                <h2 class="{{ $headingClass }}">{{ $section['title'] }}</h2>
                <p class="{{ $introClass }}">{{ $section['intro'] }}</p>
            </div>

            <div class="grid gap-4">
                @foreach ($items as $item)
                    <details
                        class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                        @if ($loop->first) open @endif
                    >
                        <summary
                            class="cursor-pointer text-lg leading-snug font-extrabold text-slate-950"
                        >
                            {{ $item['title'] }}
                        </summary>
                        <p class="mt-3 text-base leading-7 text-slate-600">
                            {{ $item['copy'] }}
                        </p>
                    </details>
                @endforeach
            </div>
        </section>
    @elseif ($layout === 'contact-routing')
        <section
            id="scoping"
            class="capell-demo-contact-gateway contact-routing-board grid gap-6 border-b border-slate-200/70 py-8 md:py-12"
        >
            <div
                class="grid gap-4 rounded-lg border border-slate-200 bg-white p-6 shadow-[0_24px_80px_rgb(15_23_42_/_0.08)] md:p-8"
            >
                <p class="{{ $eyebrowClass }}">{{ $section['eyebrow'] }}</p>
                <h2
                    class="max-w-[11ch] font-[Manrope] text-4xl leading-[1.02] font-extrabold tracking-normal text-balance text-[#131b2e] md:text-6xl"
                >
                    {{ $section['title'] }}
                </h2>
                <p
                    class="max-w-2xl text-base leading-8 text-pretty text-slate-600 md:text-lg"
                >
                    {{ $section['intro'] }}
                </p>
                @if (($cta['label'] ?? '') !== '' && ($cta['href'] ?? '') !== '')
                    <a
                        class="inline-flex min-h-12 items-center justify-center justify-self-start rounded-lg border border-slate-200 bg-slate-950 px-5 font-extrabold text-white no-underline hover:bg-[#0f766e]"
                        href="{{ $cta['href'] }}"
                    >
                        {{ $cta['label'] }}
                    </a>
                @endif
            </div>

            <div class="capell-demo-contact-grid grid gap-4 sm:grid-cols-2">
                @foreach ($items as $item)
                    <article
                        class="grid min-h-40 content-start gap-3 rounded-lg border border-slate-200 bg-white p-5 shadow-none transition duration-200 hover:border-teal-200 hover:shadow-[0_16px_40px_rgb(15_23_42_/_0.08)]"
                    >
                        <span class="{{ $labelClass }}">
                            {{ $item['label'] }}
                        </span>
                        <h3 class="{{ $cardTitleClass }}">
                            {{ $item['title'] }}
                        </h3>
                        <p class="{{ $cardCopyClass }}">{{ $item['copy'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section
            class="grid gap-4 border-y border-slate-200 bg-white py-5 md:grid-cols-3 md:gap-0 md:py-0"
            aria-label="Contact expectations"
        >
            @foreach ([['Response', 'Within 4 business hours'], ['Location', 'London, UK and remote-first'], ['Handover', 'Directly routed to the right team']] as [$label, $value])
                <div
                    class="border-slate-200 px-5 md:border-l md:px-6 md:py-5 md:first:border-l-0"
                >
                    <span class="{{ $labelClass }}">{{ $label }}</span>
                    <strong
                        class="widget mt-1 text-base font-extrabold text-slate-950"
                    >
                        {{ $value }}
                    </strong>
                </div>
            @endforeach
        </section>
    @elseif ($layout === 'case-study')
        <section
            class="capell-demo-project-detail case-study-timeline {{ $sectionClass }}"
        >
            <div class="grid gap-5 lg:max-w-3xl">
                <p class="{{ $eyebrowClass }}">{{ $section['eyebrow'] }}</p>
                <h2 class="{{ $headingClass }}">{{ $section['title'] }}</h2>
                <p class="{{ $introClass }}">{{ $section['intro'] }}</p>
            </div>

            <ol class="grid gap-4 md:grid-cols-3">
                @foreach ($steps as $step)
                    <li class="{{ $cardClass }}">
                        <span class="{{ $labelClass }}">
                            {{ $step['label'] }}
                        </span>
                        <h3 class="{{ $cardTitleClass }}">
                            {{ $step['title'] }}
                        </h3>
                        <p class="{{ $cardCopyClass }}">{{ $step['copy'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>
    @else
        <section
            class="capell-demo-asset-showcase capell-demo-asset-showcase--{{ $section['variant'] }} {{ $splitSectionClass }}"
        >
            <div class="grid gap-5">
                <p class="{{ $eyebrowClass }}">{{ $section['eyebrow'] }}</p>
                <h2 class="{{ $headingClass }}">{{ $section['title'] }}</h2>
                <p class="{{ $introClass }}">{{ $section['intro'] }}</p>
            </div>

            <div
                class="{{ in_array($layout, ['compact-route', 'quote-board'], true) ? $compactCarouselClass : $carouselClass }}"
            >
                @foreach ($items as $item)
                    <article class="{{ $carouselItemClass }} {{ $cardClass }}">
                        <span class="{{ $labelClass }}">
                            {{ $item['label'] }}
                        </span>
                        <h3 class="{{ $cardTitleClass }}">
                            {{ $item['title'] }}
                        </h3>
                        <p class="{{ $cardCopyClass }}">{{ $item['copy'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endforeach
