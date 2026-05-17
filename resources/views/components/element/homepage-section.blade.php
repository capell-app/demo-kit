@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'element',
])

<x-capell-foundation-theme::element.wrapper
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :$element
    class="capell-home-section"
>
    @switch($element->key)
        @case('capell-home-hero-command-center')
            <div class="capell-home capell-home-hero">
                <section class="capell-home-hero__copy">
                    <p class="capell-home-kicker">Capell CMS</p>
                    <h1>Composable content infrastructure for Laravel teams</h1>
                    <p>
                        Ship multi-site CMS platforms without template sprawl:
                        typed content, editor-owned layouts, package-owned
                        rendering, static output, and diagnostics in one
                        Laravel-native system.
                    </p>
                    <div class="capell-home-actions">
                        <a class="capell-home-button" href="/resources">
                            Explore the demo
                        </a>
                        <a
                            class="capell-home-button capell-home-button--secondary"
                            href="/pricing"
                        >
                            View pricing
                        </a>
                    </div>
                </section>
                <section
                    class="capell-home-command-board"
                    aria-label="Capell system board"
                >
                    <div class="capell-home-board-row is-active">
                        <span>Page types</span>
                        <strong>Home, Resources, Services</strong>
                        <em>Typed</em>
                    </div>
                    <div class="capell-home-board-row">
                        <span>Packages</span>
                        <strong>Layout Builder, SEO, Search, Publishing</strong>
                        <em>Installed</em>
                    </div>
                    <div class="capell-home-board-row">
                        <span>Workflow</span>
                        <strong>Draft, preview, approve, publish</strong>
                        <em>Traceable</em>
                    </div>
                    <div class="capell-home-board-row">
                        <span>Frontend</span>
                        <strong>Static HTML, Vite assets, cache checks</strong>
                        <em>Ready</em>
                    </div>
                </section>
            </div>

            @break
        @case('capell-home-proof-strip')
            <div
                class="capell-home capell-home-proof-strip"
                aria-label="Demo proof points"
            >
                <div>
                    <strong>38</strong>
                    <span>packages installed</span>
                </div>
                <div>
                    <strong>7</strong>
                    <span>custom homepage elements</span>
                </div>
                <div>
                    <strong>120+</strong>
                    <span>static pages generated</span>
                </div>
                <div>
                    <strong>4</strong>
                    <span>discovery checks</span>
                </div>
            </div>

            @break
        @case('capell-home-demo-showcase')
            <div class="capell-home capell-home-showcase">
                <div class="capell-home-section-head">
                    <p class="capell-home-kicker">What ships in the demo</p>
                    <h2>Custom layouts that prove the CMS can change shape</h2>
                    <p>
                        Each homepage region uses a different composition so the
                        demo feels like a real system, not a repeated stack of
                        generic cards.
                    </p>
                </div>
                <div class="capell-home-showcase-grid">
                    <article class="capell-home-console-panel">
                        <p class="capell-home-kicker">
                            Editorial command center
                        </p>
                        <h3>Operational content, not placeholder blocks</h3>
                        <p>
                            Use element translations, page types, layout
                            containers, and package data to show how an
                            editor-owned surface stays structured.
                        </p>
                    </article>
                    <article class="capell-home-market-grid-preview">
                        <p class="capell-home-kicker">Package marketplace</p>
                        <h3>Extension evidence grid</h3>
                        <div>
                            <span>SEO Suite</span>
                            <span>Search</span>
                            <span>Forms</span>
                            <span>Access Gate</span>
                            <span>Newsletter</span>
                            <span>Insights</span>
                        </div>
                    </article>
                    <article class="capell-home-workflow-panel">
                        <p class="capell-home-kicker">Publishing workflow</p>
                        <h3>Timeline plus checklist</h3>
                        <ol>
                            <li>
                                <strong>Model</strong>
                                <span>Types and elements</span>
                            </li>
                            <li>
                                <strong>Compose</strong>
                                <span>Layout containers</span>
                            </li>
                            <li>
                                <strong>Release</strong>
                                <span>Cache and sitemap</span>
                            </li>
                        </ol>
                    </article>
                </div>
            </div>

            @break
        @case('capell-extension-marketplace-showcase')
            <div
                class="capell-home capell-home-marketplace capell-extension-marketplace-section"
            >
                <div>
                    <p class="capell-home-kicker capell-section-kicker">
                        Marketplace extensions
                    </p>
                    <h2>Extension pages that help teams decide</h2>
                    <p>
                        Extension detail pages show the contract behind each
                        package: install eligibility, licence state, surfaces,
                        dependencies, frontend budget, health status,
                        documentation, feedback controls, and screenshot
                        galleries.
                    </p>
                </div>
                <div
                    class="capell-home-marketplace-grid capell-extension-marketplace-grid"
                >
                    <div>
                        <strong>See the product before installing</strong>
                        <span>
                            Large screenshots make admin pages, frontend
                            components, settings screens, and workflows visible
                            without leaving Capell.
                        </span>
                    </div>
                    <div>
                        <strong>Keep extension boundaries explicit</strong>
                        <span>
                            Surfaces, dependencies, contribution counts, and
                            performance budgets tell developers what the
                            extension adds.
                        </span>
                    </div>
                    <div>
                        <strong>Connect docs to the buying decision</strong>
                        <span>
                            Public and entitled documentation sit beside licence
                            status, access checks, version history, and
                            Marketplace actions.
                        </span>
                    </div>
                </div>
            </div>

            @break
        @case('capell-home-technical-pipeline')
            <div class="capell-home capell-home-pipeline">
                <div class="capell-home-pipeline__intro">
                    <p class="capell-home-kicker">Release path</p>
                    <h2>From admin edits to verified frontend</h2>
                    <p>
                        Capell keeps the editable CMS surface and the generated
                        public output connected through explicit ownership and
                        checks.
                    </p>
                </div>
                <ol>
                    <li>
                        <span>01</span>
                        <strong>Model content</strong>
                        <p>
                            Define typed pages, elements, translations, media,
                            and package fields.
                        </p>
                    </li>
                    <li>
                        <span>02</span>
                        <strong>Compose layout</strong>
                        <p>
                            Place elements into containers that the public theme
                            renders predictably.
                        </p>
                    </li>
                    <li>
                        <span>03</span>
                        <strong>Publish safely</strong>
                        <p>
                            Preview changes, approve releases, warm cache, and
                            generate static HTML.
                        </p>
                    </li>
                    <li>
                        <span>04</span>
                        <strong>Verify output</strong>
                        <p>
                            Run doctor, discovery, sitemap, and runtime asset
                            checks before handover.
                        </p>
                    </li>
                </ol>
            </div>

            @break
        @case('capell-home-route-split')
            <div class="capell-home capell-home-route-split">
                <a href="/resources">
                    <span>Resources hub</span>
                    <strong>Technical guides and launch checklists</strong>
                    <em>Read the CMS playbook</em>
                </a>
                <a href="/pricing">
                    <span>Pricing</span>
                    <strong>Licensing and support for production teams</strong>
                    <em>Plan the rollout</em>
                </a>
                <a href="/contact#scoping">
                    <span>Contact</span>
                    <strong>
                        Architecture, migration, and package support
                    </strong>
                    <em>Start scoping</em>
                </a>
            </div>

            @break
        @case('capell-home-final-cta')
            <div class="capell-home capell-home-final">
                <div>
                    <p class="capell-home-kicker">Demo install</p>
                    <h2>
                        Show a CMS that feels assembled, verified, and ready to
                        extend.
                    </h2>
                    <p>
                        The homepage demonstrates layout shapes, custom element
                        compositions, package boundaries, and public-page
                        discovery paths.
                    </p>
                </div>
                <a class="capell-home-button" href="/contact#scoping">
                    Start implementation scoping
                </a>
            </div>

            @break
    @endswitch
</x-capell-foundation-theme::element.wrapper>
