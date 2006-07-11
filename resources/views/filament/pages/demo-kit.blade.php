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

                    @if ($generationStatus === 'completed' && is_array($generationReview))
                        <p>{{ __('capell-demo-kit::page.completed_fingerprint', ['fingerprint' => $generationReviewFingerprint]) }}</p>
                        <ul class="list-disc pl-5">
                            @foreach ($generationReview['created_content'] ?? [] as $content)
                                <li>
                                    @if (($content['url'] ?? '') !== '')
                                        <a
                                            class="underline"
                                            href="{{ $content['url'] }}"
                                            >{{ $content['name'] }}</a
                                        >
                                    @else
                                        {{ $content['name'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if (is_array($generationReview) && $generationStatus === null)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('capell-demo-kit::page.review_heading') }}
                </x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('capell-demo-kit::page.review_description') }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    @foreach (['sites' => 'sites', 'languages' => 'languages', 'pages' => 'pages', 'media' => 'media'] as $key => $label)
                        <div>
                            <dt class="text-gray-500">
                                {{ __('capell-demo-kit::page.count_' . $label) }}
                            </dt>
                            <dd class="text-lg font-semibold">
                                {{ $generationReview['counts'][$key] ?? 0 }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
                @if (($generationReview['collisions'] ?? []) !== [])
                    <div class="mt-4 text-sm">
                        <p class="font-medium">{{ __('capell-demo-kit::page.collisions_heading') }}</p>
                        <ul class="list-disc pl-5">
                            @foreach ($generationReview['collisions'] as $collision)
                                <li>
                                    {{ $collision['name'] }} — {{ __('capell-demo-kit::page.collision_' . $collision['outcome']) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="mt-4 break-all font-mono text-xs">{{ __('capell-demo-kit::page.fingerprint', ['fingerprint' => $generationReviewFingerprint]) }}</p>
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
