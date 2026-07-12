<?php

declare(strict_types=1);

namespace Capell\DemoKit\Console\Commands\Concerns;

use Capell\Core\Console\Commands\Concerns\PromptsWithOptionFallback;
use Capell\DemoKit\Support\DemoContentPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\multiselect;

/**
 * @mixin Command
 */
trait HasLanguagesOption
{
    use PromptsWithOptionFallback;

    /**
     * @return string[]
     */
    private function getDemoLanguages(): array
    {
        $languageOption = $this->option('languages');
        if (is_string($languageOption) && $languageOption !== '') {
            return array_values(array_filter(
                array_map(trim(...), explode(',', $languageOption)),
                static fn (string $language): bool => $language !== '',
            ));
        }

        $demoLanguages = collect(resolve(DemoContentPool::class)->languages())
            ->mapWithKeys(fn (array $language, string $key): array => [$key => $language['name']])
            ->all();

        $databaseLanguages = Schema::hasTable('languages')
            ? DB::table('languages')
                ->whereIn('code', array_keys($demoLanguages))
                ->pluck('name', 'code')
                ->toArray()
            : [];

        $this->requireInteractiveOrFail('Example site languages', 'Pass --languages=<comma,separated,codes>.');

        $defaultLanguages = array_keys($databaseLanguages);
        if ($defaultLanguages === []) {
            $firstLanguage = array_key_first($demoLanguages);
            $defaultLanguages = $firstLanguage === null ? [] : [$firstLanguage];
        }

        return array_values(array_map(static fn (mixed $language): string => (string) $language, multiselect(
            label: 'Choose the example site languages?',
            options: $demoLanguages,
            default: $defaultLanguages,
            required: true,
        )));
    }
}
