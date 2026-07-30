<x-filament-panels::page>
    <div
        @if ($generationRunId !== null && in_array($generationStatus, ['queued', 'running'], true))
            wire:poll.2s="refreshGenerationRun"
        @endif
    >
        @if ($generationStatus !== null)
            <x-filament::section compact>
                <div class="space-y-1 text-sm">
                    <p class="font-medium text-gray-950 dark:text-white">
                        {{ __('capell-demo-kit::page.generation_status.' . $generationStatus) }}
                    </p>

                    @if ($generationError !== null)
                        <p class="text-danger-600 dark:text-danger-400">{{ $generationError }}</p>
                    @endif
                </div>
            </x-filament::section>
        @endif
    </div>

    <x-filament::section>
        <x-slot name="heading">
            {{ __('capell-demo-kit::page.section_heading') }}
        </x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('capell-demo-kit::page.section_description') }}</p>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
