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
    $pageName = (string) ($pageRecord?->name ?? '');
    $pageSlug = Str::slug($pageName);
    $content = $pageRecord?->translation?->content;
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
        'Services' => [
            'eyebrow' => 'Implementation services',
            'title' => 'Implementation services for complex Capell rollouts',
            'intro' => 'Content modelling, migration paths, layout architecture, package boundaries, and launch verification stay connected in one delivery path.',
            'items' => [
                ['label' => 'Audit', 'title' => 'Content model review', 'copy' => 'Map pages, assets, routes, redirects, and ownership before implementation starts.'],
                ['label' => 'Build', 'title' => 'Layout architecture', 'copy' => 'Create reusable blocks that editors can compose without breaking public output.'],
                ['label' => 'Launch', 'title' => 'Release checks', 'copy' => 'Verify cache, navigation, search, SEO, and anonymous page safety before handover.'],
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
    $showcase = $showcaseContent[$pageName] ?? null;
@endphp

<x-capell-foundation-theme::block.wrapper
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :$block
    class="capell-block-demo-page-content capell-demo-page-content"
    tag="article"
>
    @if ($content)
        <x-capell::content
            :content="$content"
            :content-type="$pageRecord?->type?->content_structure"
            :title="null"
        />
    @endif

    <div
        @class([
            'capell-demo-page px-[6%] xl:container',
            'capell-demo-page--' . $pageSlug,
            'capell-demo-showcase-page capell-demo-showcase-page--' . $pageSlug => $showcase !== null,
        ])
    >
        @switch($pageName)
            @case('Contact')
                <section
                    class="capell-demo-contact-details"
                    aria-label="Contact details"
                >
                    <h2>Contact details</h2>
                    <dl>
                        <div>
                            <dt>Email</dt>
                            <dd>
                                <a href="mailto:hello@capell.app">
                                    hello@capell.app
                                </a>
                            </dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd>
                                <a href="tel:+442045712840">+44 20 4571 2840</a>
                            </dd>
                        </div>
                        <div>
                            <dt>Response</dt>
                            <dd>Within 2 business days</dd>
                        </div>
                    </dl>
                </section>

                @break
            @case('Pricing')
                <section
                    class="capell-pricing-plan-cards"
                    aria-label="Pricing plans"
                >
                    <article>
                        <span>Developer</span>
                        <strong>GBP 0</strong>
                        <p>For local evaluation and proof of concept work.</p>
                        <a href="/contact#developer">Get started</a>
                    </article>
                    <article class="is-featured">
                        <em>Popular</em>
                        <span>Agency</span>
                        <strong>GBP 99</strong>
                        <p>For production projects with commercial support.</p>
                        <a href="/contact#agency">Start trial</a>
                    </article>
                    <article>
                        <span>Enterprise</span>
                        <strong>Custom</strong>
                        <p>For governed estates and dedicated support paths.</p>
                        <a href="/contact#enterprise">Contact sales</a>
                    </article>
                </section>

                @break
            @case('Resources')
                <section class="capell-demo-resource-index">
                    <article>
                        <span>Migration</span>
                        <h3>Designing imports editors can trust</h3>
                        <p>
                            Validate source rows, preserve redirects, and keep
                            rejected records explainable.
                        </p>
                        <em>9 min</em>
                    </article>
                    <article>
                        <span>Publishing</span>
                        <h3>Approval workflows without admin leakage</h3>
                        <p>
                            Keep draft tooling private while public pages stay
                            clean and cacheable.
                        </p>
                        <em>7 min</em>
                    </article>
                    <article>
                        <span>Theme systems</span>
                        <h3>Package-owned frontend rendering</h3>
                        <p>
                            Build reusable public surfaces without coupling them
                            to Filament screens.
                        </p>
                        <em>11 min</em>
                    </article>
                </section>

                @break
            @case('FAQ')
                <section class="capell-demo-faq-list">
                    <details>
                        <summary>Can a page skip the hero entirely?</summary>
                        <p>
                            Yes. Pages can render directly into support,
                            article, pricing, or project layouts without needing
                            a hero block.
                        </p>
                    </details>
                    <details>
                        <summary>Where does the designed markup live?</summary>
                        <p>
                            The demo page-content block owns the Blade
                            presentation. The database stores portable content
                            only.
                        </p>
                    </details>
                    <details>
                        <summary>Can editors still update the copy?</summary>
                        <p>
                            Yes. The saved page content renders before the
                            template-specific proof modules.
                        </p>
                    </details>
                </section>

                @break
            @default
                @if ($showcase)
                    <section
                        class="grid gap-8 py-12 lg:grid-cols-[0.85fr_1.15fr] lg:py-16"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600"
                            >
                                {{ $showcase['eyebrow'] }}
                            </p>
                            <h2
                                class="mt-4 max-w-2xl text-balance text-3xl font-semibold leading-tight text-slate-950 md:text-5xl"
                            >
                                {{ $showcase['title'] }}
                            </h2>
                            <p
                                class="mt-5 max-w-2xl text-base leading-8 text-slate-600"
                            >
                                {{ $showcase['intro'] }}
                            </p>
                        </div>

                        <div
                            class="capell-demo-showcase-grid grid gap-4 md:grid-cols-3"
                        >
                            @foreach ($showcase['items'] as $item)
                                <article
                                    class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm"
                                >
                                    <span
                                        class="text-sm font-semibold uppercase tracking-[0.14em] text-blue-600"
                                    >
                                        {{ $item['label'] }}
                                    </span>
                                    <h3
                                        class="mt-4 text-xl font-semibold text-slate-950"
                                    >
                                        {{ $item['title'] }}
                                    </h3>
                                    <p
                                        class="mt-3 text-base leading-7 text-slate-600"
                                    >
                                        {{ $item['copy'] }}
                                    </p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
        @endswitch
    </div>
</x-capell-foundation-theme::block.wrapper>
