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
    class="capell-block-homepage-section bg-[#faf8ff] text-[#131b2e]"
>
    @switch($block->key)
        @case('capell-home-hero-command-center')
            <div
                class="grid items-center gap-8 py-12 md:py-20 lg:grid-cols-[minmax(0,1.05fr)_minmax(24rem,0.95fr)]"
            >
                <section class="grid gap-5">
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
                    >
                        Capell CMS
                    </p>
                    <h1
                        class="max-w-[13ch] text-balance font-[Manrope] text-4xl font-extrabold leading-[1.06] tracking-normal text-[#131b2e] md:text-6xl"
                    >
                        Composable content infrastructure for Laravel teams
                    </h1>
                    <p class="max-w-2xl text-lg leading-8 text-slate-600">
                        Ship multi-site CMS platforms without template sprawl:
                        typed content, editor-owned layouts, package-owned
                        rendering, static output, and diagnostics in one
                        Laravel-native system.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <a
                            class="inline-flex min-h-12 items-center justify-center rounded-lg border border-[#0f766e] bg-[#0f766e] px-5 font-extrabold text-white no-underline hover:bg-[#005c55]"
                            href="/resources"
                        >
                            Explore the demo
                        </a>
                        <a
                            class="inline-flex min-h-12 items-center justify-center rounded-lg border border-slate-200 bg-white px-5 font-extrabold text-slate-950 no-underline hover:border-[#0f766e] hover:bg-teal-50"
                            href="/pricing"
                        >
                            View pricing
                        </a>
                    </div>
                </section>
                <section
                    class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-[0_18px_50px_rgb(15_23_42_/_0.08)]"
                    aria-label="Capell system board"
                >
                    <div
                        class="grid gap-1 rounded-lg border border-teal-200 bg-teal-50 p-4 sm:grid-cols-[8rem_minmax(0,1fr)_auto] sm:items-center"
                    >
                        <span
                            class="text-xs font-extrabold uppercase text-[#0f766e]"
                        >
                            Page types
                        </span>
                        <strong class="text-slate-950">
                            Home, Resources, Services
                        </strong>
                        <em class="text-xs font-bold not-italic text-slate-600">
                            Typed
                        </em>
                    </div>
                    <div
                        class="grid gap-1 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-[8rem_minmax(0,1fr)_auto] sm:items-center"
                    >
                        <span
                            class="text-xs font-extrabold uppercase text-[#0f766e]"
                        >
                            Packages
                        </span>
                        <strong class="text-slate-950">
                            Layout Builder, SEO, Search, Publishing
                        </strong>
                        <em class="text-xs font-bold not-italic text-slate-600">
                            Installed
                        </em>
                    </div>
                    <div
                        class="grid gap-1 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-[8rem_minmax(0,1fr)_auto] sm:items-center"
                    >
                        <span
                            class="text-xs font-extrabold uppercase text-[#0f766e]"
                        >
                            Workflow
                        </span>
                        <strong class="text-slate-950">
                            Draft, preview, approve, publish
                        </strong>
                        <em class="text-xs font-bold not-italic text-slate-600">
                            Traceable
                        </em>
                    </div>
                    <div
                        class="grid gap-1 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-[8rem_minmax(0,1fr)_auto] sm:items-center"
                    >
                        <span
                            class="text-xs font-extrabold uppercase text-[#0f766e]"
                        >
                            Frontend
                        </span>
                        <strong class="text-slate-950">
                            Static HTML, Vite assets, cache checks
                        </strong>
                        <em class="text-xs font-bold not-italic text-slate-600">
                            Ready
                        </em>
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
                        class="block font-[Manrope] text-4xl font-extrabold leading-none text-[#0f766e]"
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
                        class="block font-[Manrope] text-4xl font-extrabold leading-none text-[#0f766e]"
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
                        class="block font-[Manrope] text-4xl font-extrabold leading-none text-[#0f766e]"
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
                        class="block font-[Manrope] text-4xl font-extrabold leading-none text-[#0f766e]"
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
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
                    class="flex snap-x gap-4 overflow-x-auto pb-3 [scrollbar-width:none] md:grid md:grid-cols-3 md:overflow-visible md:pb-0 [&::-webkit-scrollbar]:hidden"
                >
                    <article
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                    >
                        <p
                            class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
                            class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
                                class="rounded-md bg-teal-50 px-3 py-1 text-sm font-bold text-[#005c55]"
                            >
                                SEO Suite
                            </span>
                            <span
                                class="rounded-md bg-teal-50 px-3 py-1 text-sm font-bold text-[#005c55]"
                            >
                                Search
                            </span>
                            <span
                                class="rounded-md bg-teal-50 px-3 py-1 text-sm font-bold text-[#005c55]"
                            >
                                Forms
                            </span>
                            <span
                                class="rounded-md bg-teal-50 px-3 py-1 text-sm font-bold text-[#005c55]"
                            >
                                Access Gate
                            </span>
                            <span
                                class="rounded-md bg-teal-50 px-3 py-1 text-sm font-bold text-[#005c55]"
                            >
                                Newsletter
                            </span>
                            <span
                                class="rounded-md bg-teal-50 px-3 py-1 text-sm font-bold text-[#005c55]"
                            >
                                Insights
                            </span>
                        </div>
                    </article>
                    <article
                        class="min-w-full snap-start rounded-lg border border-slate-200 bg-white p-5 md:min-w-0 md:p-6"
                    >
                        <p
                            class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
        @case('capell-extension-marketplace-showcase')
            <div
                class="grid gap-6 py-10 md:py-14 lg:grid-cols-[0.82fr_1.18fr] lg:items-start"
            >
                <div>
                    <p
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
                        <span class="text-sm font-black text-[#0f766e]">
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
                        <span class="text-sm font-black text-[#0f766e]">
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
                        <span class="text-sm font-black text-[#0f766e]">
                            03
                        </span>
                        <strong>Publish safely</strong>
                        <p class="text-sm leading-6 text-slate-600">
                            Preview changes, approve releases, warm cache, and
                            generate static HTML.
                        </p>
                    </li>
                    <li class="grid min-w-full snap-start gap-2 p-5 md:min-w-0">
                        <span class="text-sm font-black text-[#0f766e]">
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
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-[#0f766e]"
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
                        class="text-xs font-extrabold uppercase tracking-[0.08em] text-teal-100"
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
                    class="inline-flex min-h-12 items-center justify-center rounded-lg border border-[#0f766e] bg-[#0f766e] px-5 font-extrabold text-white no-underline hover:bg-[#005c55]"
                    href="/contact#scoping"
                >
                    Start implementation scoping
                </a>
            </div>

            @break
    @endswitch
</x-capell-foundation-theme::block.wrapper>
