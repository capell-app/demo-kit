<section
    class="capell-demo-resource-index-section grid gap-6 border-b border-slate-200/70 py-10 md:gap-8 md:py-16 dark:border-white/10"
>
    @if ($filters !== [])
        <nav
            class="flex flex-wrap gap-2 border-b border-slate-200 pb-5"
            aria-label="Resource categories"
        >
            @foreach ($filters as $filter)
                <button
                    type="button"
                    wire:click="selectFilter('{{ $filter }}')"
                    @class([
                        'rounded-lg border px-4 py-2 text-sm font-extrabold transition',
                        'border-[#0f766e] bg-[#0f766e] text-white' => $activeFilter === $filter,
                        'border-slate-200 bg-white text-slate-950 hover:border-[#0f766e]' => $activeFilter !== $filter,
                    ])
                    @if ($activeFilter === $filter) aria-current="page" @endif
                >
                    {{ $filter }}
                </button>
            @endforeach
        </nav>
    @endif

    <div
        class="[&::-webkit-scrollbar]:hidden flex snap-x [scrollbar-width:none] gap-4 overflow-x-auto pb-3 md:grid md:[grid-template-columns:repeat(auto-fit,minmax(min(100%,18rem),1fr))] md:overflow-visible md:pb-0"
    >
        @forelse ($resources as $item)
            <article
                class="grid min-h-44 min-w-full snap-start content-start gap-3 rounded-lg border border-slate-200 bg-white p-5 shadow-none transition duration-200 hover:border-teal-200 hover:shadow-[0_16px_40px_rgb(15_23_42_/_0.08)] md:min-w-0 md:p-6 dark:border-white/10 dark:bg-slate-900/80 dark:hover:border-teal-300/40"
            >
                <span
                    class="text-xs font-extrabold tracking-[0.08em] text-[#0f766e] uppercase"
                >
                    {{ $item['label'] }}
                </span>
                <h3
                    class="text-xl leading-tight font-extrabold tracking-normal text-slate-950 dark:text-white"
                >
                    {{ $item['title'] }}
                </h3>
                <p class="text-base leading-7 text-pretty text-slate-600 dark:text-slate-300">{{ $item['copy'] }}</p>
            </article>
        @empty
            <p class="rounded-lg border border-slate-200 bg-white p-5 text-base font-bold text-slate-600">No resources found.</p>
        @endforelse
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        {{ $resources->links() }}

        @if (($cta['label'] ?? '') !== '' && ($cta['href'] ?? '') !== '')
            <a
                class="inline-flex min-h-12 items-center justify-center rounded-lg border border-slate-200 bg-slate-950 px-5 font-extrabold text-white no-underline hover:bg-[#0f766e]"
                href="{{ $cta['href'] }}"
            >
                {{ $cta['label'] }}
            </a>
        @endif
    </div>
</section>
