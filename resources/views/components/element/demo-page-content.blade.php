@php
    use Illuminate\Support\Str;
@endphp

@props([
    'container',
    'containerKey',
    'containerWidth' => null,
    'loop',
    'pageRecord' => null,
    'element',
    'elementData' => [],
])

@php
    $pageName = (string) ($pageRecord?->name ?? '');
    $pageSlug = Str::slug($pageName);
    $content = $pageRecord?->translation?->content;
@endphp

<x-capell-foundation-theme::element.wrapper
    :$container
    :$containerKey
    :$containerWidth
    :index="$loop->index"
    :$element
    class="capell-demo-page-content"
    tag="article"
>
    @if ($content)
        <x-capell::content
            :content="$content"
            :content-type="$pageRecord?->type?->content_structure"
            :title="null"
        />
    @endif

    <div class="capell-demo-page capell-demo-page--{{ $pageSlug }}">
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
                            a hero element.
                        </p>
                    </details>
                    <details>
                        <summary>Where does the designed markup live?</summary>
                        <p>
                            The demo page-content element owns the Blade
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
                <section class="capell-demo-showcase-grid">
                    <article>
                        <span>01</span>
                        <h2>Model content</h2>
                        <p>
                            Store the durable page story as simple CMS content.
                        </p>
                    </article>
                    <article>
                        <span>02</span>
                        <h2>Render in Blade</h2>
                        <p>
                            Keep the designed public surface inside
                            package-owned views.
                        </p>
                    </article>
                    <article>
                        <span>03</span>
                        <h2>Verify output</h2>
                        <p>
                            Use Capell layouts and elements to connect admin
                            records to frontend rendering.
                        </p>
                    </article>
                </section>
        @endswitch
    </div>
</x-capell-foundation-theme::element.wrapper>
