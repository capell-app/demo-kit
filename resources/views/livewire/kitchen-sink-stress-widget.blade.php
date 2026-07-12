<section
    id="kitchen-sink-livewire-stress"
    class="capell-kitchen-sink-livewire grid gap-5 border-y border-slate-200 bg-white px-5 py-8 text-slate-950 md:px-8 dark:border-white/10 dark:bg-slate-950 dark:text-white"
>
    <div class="grid gap-3">
        <p
            class="text-xs font-extrabold tracking-[0.12em] text-teal-700 uppercase dark:text-teal-300"
        >
            {{ __('capell-demo-kit::page.kitchen_sink_stress.eyebrow') }}
        </p>
        <h2
            class="text-2xl leading-tight font-extrabold tracking-normal md:text-3xl"
        >
            {{ __('capell-demo-kit::page.kitchen_sink_stress.heading') }}
        </h2>
        <p
            class="max-w-3xl text-base leading-7 text-slate-700 dark:text-slate-300"
        >
            {{ __('capell-demo-kit::page.kitchen_sink_stress.description') }}
        </p>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <article
            class="rounded-lg border border-slate-200 p-4 dark:border-white/10"
        >
            <span class="text-sm font-bold text-slate-500 dark:text-slate-400">
                {{ __('capell-demo-kit::page.kitchen_sink_stress.payload_size') }}
            </span>
            <strong class="block text-3xl">{{ $referenceLength }}</strong>
        </article>
        <article
            class="rounded-lg border border-slate-200 p-4 dark:border-white/10"
        >
            <span class="text-sm font-bold text-slate-500 dark:text-slate-400">
                {{ __('capell-demo-kit::page.kitchen_sink_stress.interactions') }}
            </span>
            <strong class="block text-3xl">{{ $interactionCount }}</strong>
        </article>
        <article
            class="rounded-lg border border-slate-200 p-4 dark:border-white/10"
        >
            <span class="text-sm font-bold text-slate-500 dark:text-slate-400">
                {{ __('capell-demo-kit::page.kitchen_sink_stress.mode') }}
            </span>
            <strong class="block text-3xl">
                {{ __('capell-demo-kit::page.kitchen_sink_stress.dynamic') }}
            </strong>
        </article>
    </div>

    <button
        type="button"
        wire:click="increment"
        wire:loading.attr="disabled"
        class="inline-flex min-h-11 w-fit items-center justify-center rounded-lg bg-slate-950 px-5 text-sm font-extrabold text-white hover:bg-teal-700 disabled:cursor-wait disabled:opacity-70 dark:bg-white dark:text-slate-950"
    >
        <span wire:loading.remove>
            {{ __('capell-demo-kit::page.kitchen_sink_stress.trigger_update') }}
        </span>
        <span wire:loading>
            {{ __('capell-demo-kit::page.kitchen_sink_stress.updating') }}
        </span>
    </button>
</section>
