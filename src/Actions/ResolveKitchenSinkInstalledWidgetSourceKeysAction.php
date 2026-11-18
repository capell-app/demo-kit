<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\LayoutBuilder\Models\Widget;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Finds one installed widget for each component the fixed Kitchen Sink sources do not cover,
 * so widgets registered by themes and optional packages also appear on the stress page.
 *
 * @method static list<string> run(list<string> $coveredSourceKeys)
 */
final class ResolveKitchenSinkInstalledWidgetSourceKeysAction
{
    use AsFake;
    use AsObject;

    private const string GeneratedWidgetKeyPrefix = 'kitchen-sink-';

    /**
     * @param  list<string>  $coveredSourceKeys
     * @return list<string>
     */
    public function handle(array $coveredSourceKeys): array
    {
        $installedWidgets = Widget::query()
            ->where('status', true)
            ->whereNotNull('key')
            ->whereNotNull('component')
            ->orderBy('key')
            ->get(['key', 'component']);

        $coveredComponents = $installedWidgets
            ->whereIn('key', $coveredSourceKeys)
            ->pluck('component')
            ->all();

        return array_values($installedWidgets
            ->reject(static fn (Widget $widget): bool => str_starts_with((string) $widget->key, self::GeneratedWidgetKeyPrefix))
            ->reject(static fn (Widget $widget): bool => in_array($widget->component, $coveredComponents, true))
            ->unique('component')
            ->map(static fn (Widget $widget): string => (string) $widget->key)
            ->all());
    }
}
