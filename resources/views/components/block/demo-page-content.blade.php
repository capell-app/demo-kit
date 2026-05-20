@php
    use Capell\Frontend\Facades\Frontend;
    use Illuminate\Support\Str;
@endphp

@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'pageRecord' => null,
    'block',
    'blockData' => [],
])

@php
    $pageRecord ??= Frontend::page();
    $pageName = match (Str::lower((string) ($pageRecord?->name ?? ''))) {
        'faq' => 'FAQ',
        'home, buildings and architecture' => 'Home, Buildings and Architecture',
        'platform architecture' => 'Platform Architecture',
        default => (string) ($pageRecord?->name ?? ''),
    };
    $pageSlug = Str::slug($pageName);
    $pageTranslation = $pageRecord?->relationLoaded('translation') ? $pageRecord->translation : null;
    $pageType = $pageRecord?->relationLoaded('type') ? $pageRecord->type : null;
    $pageMeta = is_array($pageRecord?->meta ?? null) ? $pageRecord->meta : [];
    $hasVisibleHero = ($pageMeta['show_hero'] ?? true) !== false;
    $content = $pageTranslation?->content;
    $contentStructure = $pageType?->content_structure;

    $eyebrowClass = 'text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]';
    $headingClass = 'max-w-[18ch] text-balance font-[Manrope] text-3xl font-extrabold leading-[1.08] tracking-normal text-[#131b2e] md:text-5xl';
    $introClass = 'max-w-3xl text-pretty text-base leading-8 text-slate-600 md:text-lg';
    $sectionClass = 'grid gap-6 border-b border-slate-200/70 py-10 md:gap-8 md:py-16';
    $splitSectionClass = $sectionClass . ' lg:grid-cols-[minmax(18rem,0.72fr)_minmax(0,1.28fr)] lg:items-start';
    $carouselClass = 'flex snap-x gap-4 overflow-x-auto pb-3 [scrollbar-width:none] md:grid md:overflow-visible md:pb-0 md:[grid-template-columns:repeat(auto-fit,minmax(min(100%,18rem),1fr))] [&::-webkit-scrollbar]:hidden';
    $compactCarouselClass = 'flex snap-x gap-4 overflow-x-auto pb-3 [scrollbar-width:none] md:grid md:overflow-visible md:pb-0 md:[grid-template-columns:repeat(auto-fit,minmax(min(100%,15rem),1fr))] [&::-webkit-scrollbar]:hidden';
    $carouselItemClass = 'min-w-full snap-start md:min-w-0';
    $cardClass = 'grid min-h-44 content-start gap-3 rounded-lg border border-slate-200 bg-white p-5 shadow-none transition duration-200 hover:border-teal-200 hover:shadow-[0_16px_40px_rgb(15_23_42_/_0.08)] md:p-6';
    $labelClass = 'text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]';
    $cardTitleClass = 'text-xl font-extrabold leading-tight tracking-normal text-slate-950';
    $cardCopyClass = 'text-pretty text-base leading-7 text-slate-600';

    $showcaseContent = [
        'About Us' => [
            'eyebrow' => 'Platform experience',
            'title' => 'Experienced in flexible content systems',
            'intro' => 'Use Capell when a site needs more than pages and prose. The same model can power media-heavy marketing pages, resource libraries, navigation-led microsites, and governed multi-site publishing.',
            'items' => [
                ['label' => '01', 'title' => 'Model content', 'copy' => 'Store durable page stories as simple CMS content that can move between renderers.'],
                ['label' => '02', 'title' => 'Render in Blade', 'copy' => 'Keep the designed public surface inside package-owned views.'],
                ['label' => '03', 'title' => 'Verify output', 'copy' => 'Connect admin records to frontend rendering without exposing editor concerns.'],
            ],
        ],
        'Homepage 2' => [
            'eyebrow' => 'Homepage variant',
            'title' => 'A second homepage for service-led Capell builds',
            'intro' => 'This page proves the same content system can support a different homepage rhythm: stronger service positioning, proof modules, and route-specific calls to action.',
            'items' => [
                ['label' => 'Hero', 'title' => 'Service-led opening', 'copy' => 'A compact proposition for teams evaluating implementation support.'],
                ['label' => 'Proof', 'title' => 'Capability modules', 'copy' => 'Reusable proof cards make the page feel distinct without another template stack.'],
                ['label' => 'Routes', 'title' => 'Next-step links', 'copy' => 'Pricing, contact, resources, and services stay connected from the variant.'],
            ],
        ],
        'Team' => [
            'eyebrow' => 'Delivery team',
            'title' => 'Implementation specialists for Capell websites',
            'intro' => 'A team page should prove capability, not just show profiles. These roles map to the work needed to build flexible Capell sites.',
            'items' => [
                ['label' => 'Strategy', 'title' => 'CMS architecture', 'copy' => 'Owns page models, routes, package boundaries, and release shape.'],
                ['label' => 'Frontend', 'title' => 'Public rendering', 'copy' => 'Builds Tailwind and Blade surfaces that stay clean for visitors.'],
                ['label' => 'Publishing', 'title' => 'Workflow setup', 'copy' => 'Connects Filament editing, preview, approval, and handover.'],
            ],
        ],
        'Testimonials' => [
            'eyebrow' => 'Customer proof',
            'title' => 'What Capell builders say',
            'intro' => 'Customer proof should connect outcomes to the delivery model behind them.',
            'items' => [
                ['label' => 'Agency', 'title' => 'Faster rebuilds', 'copy' => 'Reusable blocks reduced one-off template work across the site.'],
                ['label' => 'Editor', 'title' => 'Clear ownership', 'copy' => 'Teams can update copy and media without touching implementation details.'],
                ['label' => 'Engineering', 'title' => 'Cleaner releases', 'copy' => 'Public output remains cacheable and separate from admin tooling.'],
            ],
        ],
        'Projects' => [
            'eyebrow' => 'Project library',
            'title' => 'Capell implementation project library',
            'intro' => 'Project listings show how Capell can present structured work, media, and calls to action from reusable public templates.',
            'items' => [
                ['label' => 'Case study', 'title' => 'Layout builder redesign', 'copy' => 'A flexible page system rebuilt around reusable sections and assets.'],
                ['label' => 'Migration', 'title' => 'Resource library import', 'copy' => 'Structured content and redirects moved into a governed CMS workflow.'],
                ['label' => 'Launch', 'title' => 'Static delivery rollout', 'copy' => 'Cache generation and public verification before handover.'],
            ],
        ],
        'Project Detail' => [
            'eyebrow' => 'Project detail',
            'title' => 'Layout builder redesign for a flexible Capell website',
            'intro' => 'A project detail page can explain scope, delivery, results, and ownership without hard-coding the case-study layout into CMS prose.',
            'items' => [
                ['label' => 'Scope', 'title' => 'Reusable page sections', 'copy' => 'The implementation kept existing content intent while improving layout ownership.'],
                ['label' => 'Result', 'title' => 'Cleaner publishing', 'copy' => 'Editors gained safer composition and developers kept package-owned rendering.'],
                ['label' => 'Handover', 'title' => 'Documented release path', 'copy' => 'QA, cache, and frontend checks are part of the delivery.'],
            ],
        ],
        'Blog' => [
            'eyebrow' => 'Latest news',
            'title' => 'Our blog for Capell builders',
            'intro' => 'Blog listings can use the same editorial rhythm as the rest of the site while staying powered by structured article content.',
            'items' => [
                ['label' => 'News', 'title' => 'Home, buildings and architecture', 'copy' => 'How architecture-style page systems map to Capell layout builder websites.'],
                ['label' => 'Guide', 'title' => 'Designing a better homepage flow', 'copy' => 'Turning mixed CMS objects into one coherent public page.'],
                ['label' => 'Tips', 'title' => 'How to avoid rigid templates', 'copy' => 'Use block boundaries, assets, and reusable sections to keep pages flexible.'],
            ],
        ],
        'Platform Architecture' => [
            'eyebrow' => 'Architecture',
            'title' => 'Platform architecture for maintainable CMS delivery',
            'intro' => 'Capell separates content records, layouts, render data, public components, and package extension points.',
            'items' => [
                ['label' => 'Core', 'title' => 'Content records', 'copy' => 'Pages, translations, media, layouts, and URLs stay structured.'],
                ['label' => 'Theme', 'title' => 'Public rendering', 'copy' => 'Blade components own the frontend surface and cacheable output.'],
                ['label' => 'Package', 'title' => 'Extension points', 'copy' => 'Packages add behaviour without leaking admin concerns to visitors.'],
            ],
        ],
    ];

    $footerContent = [
        'Integrations' => ['title' => 'Integration surfaces for teams that need traceable sync', 'signal' => 'Connector map', 'proof' => 'Sync health', 'copy' => 'Show how package integration status, connector ownership, and data movement are explained to visitors.'],
        'Locations' => ['title' => 'Multi-site delivery without losing local context', 'signal' => 'Network signal', 'proof' => 'Operational proof', 'copy' => 'Regional teams can publish local obligations, evidence, and support details while sharing the same Capell rendering system.'],
        'Partners' => ['title' => 'Partner delivery paths with clear implementation boundaries', 'signal' => 'Partner ladder', 'proof' => 'Delivery proof', 'copy' => 'Partner pages explain routes to market, support ownership, and when a project moves from referral to implementation.'],
        'Roadmap' => ['title' => 'A roadmap page that turns product direction into trust', 'signal' => 'Release board', 'proof' => 'Decision log', 'copy' => 'Roadmap content keeps upcoming platform work, delivery confidence, and constraints visible without promising vague features.'],
        'Governance' => ['title' => 'Governance content for teams that publish with consequences', 'signal' => 'Control panel', 'proof' => 'Audit trail', 'copy' => 'Governance pages make permissions, approval flow, content ownership, and release responsibilities explicit.'],
        'Training' => ['title' => 'Training pages that help teams actually own the CMS', 'signal' => 'Training map', 'proof' => 'Handover proof', 'copy' => 'Training content turns editor onboarding, support paths, and repeatable publishing habits into a visible page.'],
    ];

    $lessonContent = [
        'Contact' => ['Contact form', 'Intro copy, routing cards, and form fields are separate blocks so qualification can change without rebuilding the page.'],
        'Services' => ['Service layout', 'The atelier view combines a split introduction, service board carousel, proof metrics, and a process timeline.'],
        'Pricing' => ['Pricing layout', 'Plan cards, pricing questions, and implementation scoping live on the pricing route instead of overloading the homepage.'],
        'Implementation' => ['Scoped child page', 'This child page keeps commercial guardrails under Pricing while reusing the same content renderer. block'],
        'Resources' => ['Resource hub', 'Featured content, category filters, resource cards, and toolkit CTA are assembled as distinct reusable sections.'],
        'FAQ' => ['Support layout', 'A no-hero page can still use saved CMS copy, accordion content, and a calm support template.'],
        'Home, Buildings and Architecture' => ['Article layout', 'Article metadata and body copy stay focused while related proof modules remain in the page template.'],
        'Compliance' => ['Location child page', 'Regional detail pages inherit the shared location structure while keeping local obligations editable.'],
        'Sustainability' => ['Location child page', 'Local initiatives reuse the same page model as compliance with different copy and evidence.'],
    ];

    $showcase = $showcaseContent[$pageName] ?? null;
    $footer = $footerContent[$pageName] ?? null;
    $lesson = $lessonContent[$pageName] ?? (
        $showcase
            ? ['Reusable page shape', 'The saved page body stays portable while the Blade block adds the designed public modules for this route.']
            : ($footer ? ['Footer route', 'Footer pages share one layout block, then swap local copy, proof cards, and navigation labels.'] : null)
    );
@endphp

<x-capell-foundation-theme::block.wrapper
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :$block
    class="capell-block-demo-page-content capell-demo-page-content overflow-x-clip bg-[#faf8ff] text-[#131b2e] [container-type:inline-size] [text-rendering:optimizeLegibility]"
    tag="section"
>
    @if ($content)
        <div class="mx-auto max-w-4xl px-[6%] py-8 xl:px-0">
            <x-capell::content
                class="prose-p:text-pretty prose-p:text-slate-600 prose-p:leading-8"
                :content="$content"
                :content-type="$contentStructure"
                :title="null"
                width="content"
            />
        </div>
    @endif

    <div
        class="capell-demo-page capell-demo-page--{{ $pageSlug }} mx-auto max-w-[1200px] px-[6%] xl:px-0"
    >
        @if (! $hasVisibleHero && $pageName !== '')
            <h1 class="sr-only">
                {{ $pageTranslation?->title ?: $pageName }}
            </h1>
        @endif

        @if ($lesson)
            <section
                class="grid gap-4 border-b border-slate-200/70 py-8 md:grid-cols-[minmax(0,0.7fr)_minmax(0,1.3fr)] md:items-start md:py-10"
            >
                <div>
                    <p class="{{ $eyebrowClass }}">
                        How this page is assembled
                    </p>
                    <h2
                        class="mt-3 max-w-[18ch] font-[Manrope] text-2xl font-extrabold leading-tight tracking-normal text-slate-950 md:text-3xl"
                    >
                        {{ $lesson[0] }}
                    </h2>
                </div>

                <p
                    class="max-w-3xl text-pretty text-base leading-8 text-slate-600"
                >
                    {{ $lesson[1] }}
                </p>
            </section>
        @endif

        @if ($showcase)
            <section class="{{ $splitSectionClass }}">
                <div class="grid gap-5">
                    <p class="{{ $eyebrowClass }}">
                        {{ $showcase['eyebrow'] }}
                    </p>
                    <h2 class="{{ $headingClass }}">
                        {{ $showcase['title'] }}
                    </h2>
                    <p class="{{ $introClass }}">{{ $showcase['intro'] }}</p>
                </div>

                <div class="capell-demo-showcase-grid {{ $carouselClass }}">
                    @foreach ($showcase['items'] as $item)
                        <article
                            class="{{ $carouselItemClass }} {{ $cardClass }}"
                        >
                            <span class="{{ $labelClass }}">
                                {{ $item['label'] }}
                            </span>
                            <h3 class="{{ $cardTitleClass }}">
                                {{ $item['title'] }}
                            </h3>
                            <p class="{{ $cardCopyClass }}">
                                {{ $item['copy'] }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </section>
        @elseif ($pageName === 'Contact')
            <section
                id="scoping"
                class="capell-demo-contact-gateway {{ $splitSectionClass }}"
            >
                <div class="grid gap-5">
                    <p class="{{ $eyebrowClass }}">Contact gateway</p>
                    <h2 class="{{ $headingClass }}">
                        Send a message about your Capell build
                    </h2>
                    <p class="{{ $introClass }}">
                        Route implementation, migration, package, and support
                        enquiries through one clear public surface.
                    </p>
                </div>

                <div class="capell-demo-contact-routing grid gap-6">
                    <div
                        class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-none md:p-6"
                    >
                        <span class="{{ $labelClass }}">Routing model</span>
                        <h3 class="{{ $cardTitleClass }}">
                            One gateway, clear handoff
                        </h3>
                        <p class="{{ $cardCopyClass }}">
                            The real contact form renders in the adjacent form
                            block. This panel explains how enquiries are routed
                            before the CMS hands the request to the form
                            builder.
                        </p>
                        <a
                            class="inline-flex min-h-12 items-center justify-center justify-self-start rounded-lg border border-slate-200 bg-slate-50 px-5 font-extrabold text-slate-950 no-underline hover:border-[#0f766e] hover:text-[#0f766e]"
                            href="#contact-form-contact-form-0"
                        >
                            Use the contact form
                        </a>
                    </div>

                    <div
                        class="capell-demo-contact-grid {{ $compactCarouselClass }}"
                    >
                        @foreach ([['Address', 'Capell Studio, London', 'Remote-first delivery with UK timezone handover.'], ['Response', 'Two business days', 'Enough context to qualify the right delivery path.'], ['Routing', 'Project, support, migration', 'Contact topics map to the same governed CMS model.']] as [$label, $title, $copy])
                            <article
                                class="{{ $carouselItemClass }} {{ $cardClass }}"
                            >
                                <span class="{{ $labelClass }}">
                                    {{ $label }}
                                </span>
                                <h3 class="{{ $cardTitleClass }}">
                                    {{ $title }}
                                </h3>
                                <p class="{{ $cardCopyClass }}">
                                    {{ $copy }}
                                </p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @elseif ($pageName === 'Services')
            <section
                class="capell-demo-services-atelier {{ $splitSectionClass }}"
            >
                <div class="grid gap-5">
                    <p class="{{ $eyebrowClass }}">Services atelier</p>
                    <h2 class="{{ $headingClass }}">
                        Implementation services for complex Capell rollouts
                    </h2>
                    <p class="{{ $introClass }}">
                        Content modelling, migration paths, layout architecture,
                        package boundaries, and launch verification stay
                        connected in one delivery path.
                    </p>
                </div>

                <div class="capell-demo-service-board {{ $carouselClass }}">
                    @foreach ([['Audit board', 'Content model review', 'Map pages, assets, routes, redirects, and ownership before implementation starts.'], ['Build board', 'Layout architecture', 'Create reusable blocks that editors can compose without breaking public output.'], ['Launch board', 'Release checks', 'Verify cache, navigation, search, SEO, and anonymous page safety before handover.']] as [$label, $title, $copy])
                        <article
                            class="{{ $carouselItemClass }} {{ $cardClass }}"
                        >
                            <span class="{{ $labelClass }}">
                                {{ $label }}
                            </span>
                            <h3
                                class="text-2xl font-black leading-none text-slate-950"
                            >
                                {{ $title }}
                            </h3>
                            <p class="{{ $cardCopyClass }}">{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section
                class="flex snap-x gap-4 overflow-x-auto border-y border-slate-200 bg-white py-4 [scrollbar-width:none] md:grid md:grid-cols-4 md:gap-0 md:overflow-visible md:py-0 [&::-webkit-scrollbar]:hidden"
                aria-label="Service proof points"
            >
                @foreach ([['6 wk', 'typical build sprint'], ['12+', 'page shapes mapped'], ['0', 'admin metadata leaks'], ['4', 'handover checkpoints']] as [$value, $label])
                    <div
                        class="min-w-full snap-start border border-slate-200 bg-white p-5 md:min-w-0 md:border-y-0 md:border-l-0 md:p-6"
                    >
                        <strong
                            class="block font-[Manrope] text-3xl font-extrabold leading-none text-[#0f766e] md:text-4xl"
                        >
                            {{ $value }}
                        </strong>
                        <span
                            class="mt-2 block text-sm font-bold text-slate-600"
                        >
                            {{ $label }}
                        </span>
                    </div>
                @endforeach
            </section>

            <section
                class="capell-demo-engineering-pipeline {{ $sectionClass }}"
            >
                <div class="grid gap-5 lg:max-w-3xl">
                    <p class="{{ $eyebrowClass }}">Engineering pipeline</p>
                    <h2 class="{{ $headingClass }}">
                        A delivery path that stays visible
                    </h2>
                    <p class="{{ $introClass }}">
                        The services page should feel like an implementation
                        workbench: decisions, sequence, risks, and launch
                        criteria are laid out before anyone commits to a build.
                    </p>
                </div>
                <ol
                    class="flex snap-x gap-4 overflow-x-auto rounded-lg border border-slate-200 bg-white [scrollbar-width:none] md:grid md:grid-cols-4 md:gap-0 md:overflow-visible [&::-webkit-scrollbar]:hidden"
                >
                    @foreach ([['01', 'Audit the content model', 'Inventory pages, media, routes, redirects, permissions, integrations, and editorial risks.'], ['02', 'Shape reusable layouts', 'Turn page intent into governed sections instead of another stack of bespoke templates.'], ['03', 'Build package-owned surfaces', 'Keep Blade, render data, cache, and tests close to the package that owns the behaviour.'], ['04', 'Verify public output', 'Check anonymous rendering, navigation, search, SEO, and visual regressions before handover.']] as [$step, $title, $copy])
                        <li
                            class="grid min-w-full snap-start content-start gap-2 border-b border-slate-200 p-5 last:border-b-0 md:min-w-0 md:border-b-0 md:border-r md:last:border-r-0"
                        >
                            <span class="text-sm font-black text-[#0f766e]">
                                {{ $step }}
                            </span>
                            <strong
                                class="text-lg font-extrabold leading-snug text-slate-950"
                            >
                                {{ $title }}
                            </strong>
                            <p class="text-sm leading-6 text-slate-600">
                                {{ $copy }}
                            </p>
                        </li>
                    @endforeach
                </ol>
            </section>
        @elseif ($pageName === 'FAQ')
            <section
                class="capell-demo-showcase-page capell-demo-showcase-page--faq {{ $sectionClass }} max-w-4xl"
            >
                <div class="grid gap-5">
                    <p class="{{ $eyebrowClass }}">Support layout</p>
                    <h2 class="{{ $headingClass }}">
                        FAQ content without a hero dependency
                    </h2>
                </div>

                <div class="grid gap-4">
                    @foreach ([['Can a page skip the hero entirely?', 'Yes. Pages can render directly into support, article, pricing, or project layouts without needing a hero block.'], ['Where does the designed markup live?', 'The demo page-content block owns the Blade presentation. The database stores portable content only.'], ['Can editors still update the copy?', 'Yes. The saved page content renders before the template-specific proof modules.']] as [$question, $answer])
                        <details
                            class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                            @if ($loop->first) open @endif
                        >
                            <summary
                                class="cursor-pointer text-lg font-extrabold leading-snug text-slate-950"
                            >
                                {{ $question }}
                            </summary>
                            <p class="mt-3 text-base leading-7 text-slate-600">
                                {{ $answer }}
                            </p>
                        </details>
                    @endforeach
                </div>
            </section>
        @elseif ($pageName === 'Pricing')
            <section class="capell-demo-pricing-matrix {{ $sectionClass }}">
                <div class="grid gap-5 lg:max-w-3xl">
                    <p class="{{ $eyebrowClass }}">Pricing matrix</p>
                    <h2 class="{{ $headingClass }}">
                        Simple pricing for Capell CMS delivery
                    </h2>
                    <p class="{{ $introClass }}">
                        Compare the commercial model without making the homepage
                        carry the full pricing table.
                    </p>
                </div>

                <div class="capell-demo-pricing-grid {{ $carouselClass }}">
                    @foreach ([['Developer', 'GBP 0', 'For evaluation, prototypes, and local proof-of-concept work.', 'Self-guided', false], ['Agency', 'GBP 99', 'For production delivery with commercial support and implementation confidence.', 'Popular', true], ['Enterprise', 'Custom', 'For governed estates, multi-site publishing, and dedicated support paths.', 'Scoped', false]] as [$label, $price, $copy, $badge, $featured])
                        <article
                            @class([
                                $carouselItemClass,
                                $cardClass,
                                'border-[#0f766e] bg-teal-50 shadow-[0_18px_48px_rgb(0_92_85_/_0.12)]' => $featured,
                                'min-h-72' => true,
                            ])
                        >
                            <span
                                class="{{ $featured ? 'text-xs font-black uppercase tracking-normal text-green-700' : $labelClass }}"
                            >
                                {{ $label }}
                            </span>
                            <h3
                                class="text-4xl font-black leading-none text-slate-950"
                            >
                                {{ $price }}
                            </h3>
                            <p class="{{ $cardCopyClass }}">{{ $copy }}</p>
                            <em
                                class="mt-auto justify-self-start rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-extrabold not-italic text-slate-950"
                            >
                                {{ $badge }}
                            </em>
                        </article>
                    @endforeach
                </div>

                <section
                    class="max-w-5xl rounded-lg bg-slate-950 p-6 shadow-[0_18px_60px_rgb(15_23_42_/_0.18)] md:p-8"
                >
                    <h3 class="text-2xl font-black tracking-normal text-white">
                        Common pricing questions
                    </h3>
                    <p
                        class="mt-3 max-w-3xl text-pretty text-base leading-8 text-slate-200"
                    >
                        Support level, response time, migration help, and
                        implementation depth are separated so teams can pick the
                        right path.
                    </p>
                </section>
            </section>
        @elseif ($pageName === 'Implementation')
            <section
                class="capell-demo-implementation-plan bg-linear-to-br my-10 grid gap-8 rounded-2xl from-slate-950 to-blue-900 p-6 shadow-2xl md:my-16 md:p-10"
            >
                <div class="grid gap-5">
                    <p
                        class="text-xs font-black uppercase tracking-normal text-blue-200"
                    >
                        Implementation scoping
                    </p>
                    <h2
                        class="max-w-[16ch] text-balance text-3xl font-black leading-[1.02] tracking-normal text-white md:text-5xl xl:text-6xl"
                    >
                        Implementation plan with commercial guardrails
                    </h2>
                    <p
                        class="max-w-3xl text-pretty text-base leading-8 text-blue-100 md:text-lg"
                    >
                        Turn scope, timeline, risk, and price confidence into a
                        visible delivery surface.
                    </p>
                </div>

                <div
                    class="capell-demo-implementation-grid {{ $carouselClass }}"
                >
                    @foreach ([['Scope confidence', 'High', 'Known page types, content model, integrations, and launch criteria.'], ['Delivery rhythm', '4 phases', 'Audit, build, migrate, verify.'], ['Guardrails', 'Change controlled', 'Commercial changes are priced before implementation work starts.']] as [$label, $title, $copy])
                        <article
                            class="{{ $carouselItemClass }} grid min-h-44 content-start gap-3 rounded-lg border border-blue-200/30 bg-white/10 p-5 text-white shadow-none md:p-6"
                        >
                            <span
                                class="text-xs font-black uppercase tracking-normal text-blue-100"
                            >
                                {{ $label }}
                            </span>
                            <h3
                                class="text-3xl font-black leading-none text-white"
                            >
                                {{ $title }}
                            </h3>
                            <p
                                class="text-pretty text-base leading-7 text-blue-50"
                            >
                                {{ $copy }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </section>
        @elseif ($pageName === 'Resources')
            <section
                class="capell-demo-resources-library {{ $splitSectionClass }}"
            >
                <div class="grid gap-5">
                    <p class="{{ $eyebrowClass }}">Resource library</p>
                    <h2 class="{{ $headingClass }}">
                        Resource library for Capell builders
                    </h2>
                    <p class="{{ $introClass }}">
                        Resource index pages need dense but readable cards,
                        filters, article metadata, and implementation
                        references.
                    </p>
                </div>

                <div
                    class="grid gap-6 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-[minmax(0,1fr)_14rem] md:items-end md:p-8"
                >
                    <div>
                        <span class="{{ $labelClass }}">Featured guide</span>
                        <h3
                            class="mt-3 font-[Manrope] text-2xl font-extrabold leading-tight text-slate-950 md:text-4xl"
                        >
                            Scaling Laravel CMS architecture for 1M+ records
                        </h3>
                        <p class="mt-4 text-base leading-7 text-slate-600">
                            A dense implementation note on content modelling,
                            search, cache invalidation, and public rendering at
                            scale.
                        </p>
                    </div>
                    <aside class="border-l-4 border-[#0f766e] bg-teal-50 p-4">
                        <strong
                            class="block font-[Manrope] text-3xl font-extrabold leading-none text-[#0f766e] md:text-4xl"
                        >
                            18 min
                        </strong>
                        <span class="mt-2 block font-bold text-slate-600">
                            Architecture
                        </span>
                    </aside>
                </div>
            </section>

            <section
                class="flex gap-2 overflow-x-auto border-b border-slate-200 py-5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                aria-label="Resource categories"
            >
                @foreach (['All resources', 'Architecture', 'Migration', 'Publishing', 'Theme systems'] as $filter)
                    <a
                        @class([
                            'flex-none rounded-lg border px-4 py-2 text-sm font-extrabold no-underline',
                            'border-[#0f766e] bg-[#0f766e] text-white' => $loop->first,
                            'border-slate-200 bg-white text-slate-950' => ! $loop->first,
                        ])
                        href="/resources"
                        @if ($loop->first) aria-current="page" @endif
                    >
                        {{ $filter }}
                    </a>
                @endforeach
            </section>

            <section
                class="flex snap-x gap-4 overflow-x-auto border-b border-slate-200 py-8 [scrollbar-width:none] md:grid md:grid-cols-4 md:overflow-visible [&::-webkit-scrollbar]:hidden"
            >
                @foreach ([['Architecture', 'Content models, domains, layouts, and package contracts.', '14 resources'], ['Migration', 'Imports, redirects, media moves, and validation evidence.', '9 resources'], ['Publishing', 'Approval flows, preview discipline, cache, and release checks.', '12 resources'], ['Theme systems', 'Blade surfaces, Tailwind composition, and visual QA.', '8 resources']] as [$title, $copy, $count])
                    <article
                        class="{{ $carouselItemClass }} {{ $cardClass }}"
                    >
                        <span class="{{ $labelClass }}">{{ $count }}</span>
                        <h3 class="{{ $cardTitleClass }}">{{ $title }}</h3>
                        <p class="{{ $cardCopyClass }}">{{ $copy }}</p>
                    </article>
                @endforeach
            </section>

            <section
                class="capell-demo-resource-index-section {{ $sectionClass }}"
            >
                <div class="grid gap-5 lg:max-w-3xl">
                    <p class="{{ $eyebrowClass }}">Latest insights</p>
                    <h2 class="{{ $headingClass }}">
                        Implementation references built for scanning
                    </h2>
                </div>

                <div class="capell-demo-resource-index {{ $carouselClass }}">
                    @foreach ([['Migration', 'Designing imports editors can trust', 'Validate source rows, preserve redirects, and keep rejected records explainable.', '9 min'], ['Publishing', 'Approval workflows without admin leakage', 'Keep draft tooling private while public pages stay clean and cacheable.', '7 min'], ['Theme systems', 'Package-owned frontend rendering', 'Build reusable public surfaces without coupling them to Filament screens.', '11 min']] as [$label, $title, $copy, $time])
                        <article
                            class="{{ $carouselItemClass }} {{ $cardClass }}"
                        >
                            <span class="{{ $labelClass }}">
                                {{ $label }}
                            </span>
                            <h3 class="{{ $cardTitleClass }}">
                                {{ $title }}
                            </h3>
                            <p class="{{ $cardCopyClass }}">{{ $copy }}</p>
                            <em
                                class="mt-auto text-sm font-extrabold not-italic text-slate-600"
                            >
                                {{ $time }}
                            </em>
                        </article>
                    @endforeach
                </div>
            </section>

            <section
                class="my-10 grid gap-6 rounded-lg bg-slate-950 p-6 shadow-[0_18px_60px_rgb(15_23_42_/_0.18)] md:my-16 md:grid-cols-[minmax(0,1fr)_18rem] md:items-end md:p-10"
            >
                <div>
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-teal-100"
                    >
                        Implementation toolkit
                    </p>
                    <h2
                        class="mt-3 font-[Manrope] text-3xl font-extrabold leading-tight text-white md:text-4xl"
                    >
                        Build faster with architectural blueprints.
                    </h2>
                    <p class="mt-4 text-base leading-7 text-slate-300">
                        Turn the resource library into a practical operating
                        surface: migration checklists, page model maps, QA
                        plans, and launch evidence in one route.
                    </p>
                </div>
                <a
                    class="inline-flex min-h-12 items-center justify-center rounded-lg bg-[#0f766e] px-5 font-extrabold text-white no-underline hover:bg-[#005c55]"
                    href="/contact"
                >
                    Plan a rollout
                </a>
            </section>
        @elseif ($pageName === 'Home, Buildings and Architecture')
            <article
                class="capell-demo-showcase-page capell-demo-showcase-page--single-post {{ $sectionClass }} max-w-4xl"
            >
                <header class="grid gap-5">
                    <p class="{{ $eyebrowClass }}">Article template</p>
                    <h2 class="{{ $headingClass }}">
                        Home, buildings and architecture
                    </h2>
                    <p class="{{ $introClass }}">
                        A finished article surface should teach the shape of the
                        template while still reading like editorial content:
                        metadata first, body sections next, then related routes.
                    </p>
                </header>

                <div
                    class="grid gap-6 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-[minmax(0,1fr)_14rem] md:p-8"
                >
                    <div class="grid gap-5 text-base leading-8 text-slate-600">
                        <p>
                            Capell article pages keep prose portable while the
                            page block owns the surrounding chrome. Editors
                            write the story; the template supplies hierarchy,
                            metadata, related resources, and safe public
                            rendering.
                        </p>
                        <p>
                            Use this pattern when a long-form page needs to sit
                            beside resource hubs, archives, and topic navigation
                            without inheriting homepage modules or commercial
                            pricing content.
                        </p>
                    </div>
                    <aside
                        class="grid content-start gap-4 border-l-4 border-[#0f766e] bg-teal-50 p-4"
                    >
                        <strong
                            class="font-[Manrope] text-2xl font-extrabold leading-tight text-[#0f766e]"
                        >
                            Article chrome
                        </strong>
                        <span
                            class="text-sm font-bold leading-6 text-slate-700"
                        >
                            Author, read time, body copy, related resources, and
                            clean public rendering.
                        </span>
                    </aside>
                </div>

                <div
                    class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-6 sm:grid-cols-3"
                >
                    @foreach ([['Author', 'Capell editorial'], ['Read time', '8 min'], ['Template', 'Single post']] as [$label, $value])
                        <span>
                            <strong
                                class="block text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
                            >
                                {{ $label }}
                            </strong>
                            {{ $value }}
                        </span>
                    @endforeach
                </div>

                <div
                    class="capell-demo-article-related {{ $compactCarouselClass }}"
                >
                    @foreach ([['Resource hub', 'Connect long-form posts back to guides and architecture notes.'], ['Archive rail', 'Keep chronological discovery available without changing the article body.'], ['Topic tags', 'Let taxonomy create related journeys across the CMS.']] as [$title, $copy])
                        <article
                            class="{{ $carouselItemClass }} {{ $cardClass }}"
                        >
                            <span class="{{ $labelClass }}">
                                Related block
                            </span>
                            <h3 class="{{ $cardTitleClass }}">
                                {{ $title }}
                            </h3>
                            <p class="{{ $cardCopyClass }}">{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>
            </article>
        @elseif ($footer)
            <section
                class="capell-demo-footer-page capell-demo-footer-page--{{ $pageSlug }} {{ $sectionClass }} lg:grid-cols-[minmax(0,0.85fr)_minmax(22rem,1.15fr)] lg:items-start"
            >
                <div class="capell-demo-footer-editorial grid gap-5">
                    <p class="{{ $eyebrowClass }}">{{ $pageName }}</p>
                    <h2 class="{{ $headingClass }}">
                        {{ $footer['title'] }}
                    </h2>
                    <p class="{{ $introClass }}">{{ $footer['copy'] }}</p>
                </div>

                <div
                    class="capell-demo-footer-evidence {{ $compactCarouselClass }}"
                >
                    @foreach ([[$footer['signal'], 'Mapped', 'Content, routes, and ownership are visible.'], [$footer['proof'], 'Verified', 'Public output can be checked before handover.']] as [$label, $title, $copy])
                        <article
                            class="{{ $carouselItemClass }} {{ $cardClass }}"
                        >
                            <span class="{{ $labelClass }}">
                                {{ $label }}
                            </span>
                            <h3
                                class="text-2xl font-black leading-none text-slate-950"
                            >
                                {{ $title }}
                            </h3>
                            <p class="{{ $cardCopyClass }}">{{ $copy }}</p>
                        </article>
                    @endforeach
                </div>

                <div
                    class="capell-demo-footer-variation-strip flex flex-wrap gap-3 lg:col-span-2"
                >
                    @foreach (['Footer route', 'Shared template', 'Local content'] as $label)
                        <span
                            class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-black uppercase tracking-normal text-slate-950"
                        >
                            {{ $label }}
                        </span>
                    @endforeach
                </div>

                <div class="capell-demo-footer-route-band lg:col-span-2">
                    <div class="{{ $compactCarouselClass }}">
                        @foreach ([['01', $footer['signal'], 'The first block names the route purpose so visitors know why this footer page exists.'], ['02', $footer['proof'], 'The second block shows the proof or operating evidence that makes the page feel finished.'], ['03', 'Editor handover', 'The final block explains which copy, links, and evidence an editor can maintain in the CMS.']] as [$step, $title, $copy])
                            <article
                                class="{{ $carouselItemClass }} {{ $cardClass }}"
                            >
                                <span class="{{ $labelClass }}">
                                    {{ $step }}
                                </span>
                                <h3 class="{{ $cardTitleClass }}">
                                    {{ $title }}
                                </h3>
                                <p class="{{ $cardCopyClass }}">
                                    {{ $copy }}
                                </p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @elseif (in_array($pageName, ['Compliance', 'Sustainability'], true))
            <section
                class="capell-demo-location-detail my-10 grid max-w-5xl gap-5 rounded-lg border border-slate-200 bg-white p-6 shadow-lg md:my-16 md:p-8"
            >
                <p class="{{ $eyebrowClass }}">Location detail</p>
                <h2 class="{{ $headingClass }}">
                    {{ $pageName === 'Compliance' ? 'Compliance content for regional obligations' : 'Sustainability content for local initiatives' }}
                </h2>
                <p class="{{ $introClass }}">
                    {{ $pageName === 'Compliance' ? 'Local teams can explain regional obligations, review cadence, policy ownership, and evidence without changing the shared footer template.' : 'Local initiatives, measurements, and proof points stay consistent across the network while remaining editable by regional owners.' }}
                </p>
            </section>
        @endif
    </div>
</x-capell-foundation-theme::block.wrapper>
