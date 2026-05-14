<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Language;
use Capell\DemoKit\Support\DemoContentPool;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, Language> run(list<string> $languageCodes)
 */
final class CreateDemoLanguagesAction
{
    use AsObject;

    public function __construct(
        private readonly DemoContentPool $contentPool = new DemoContentPool,
    ) {}

    /**
     * @param  list<string>  $languageCodes
     * @return Collection<int, Language>
     */
    public function handle(array $languageCodes): Collection
    {
        foreach ($this->contentPool->languages() as $item) {
            if (! in_array($item['code'], $languageCodes, true)) {
                continue;
            }

            $language = Language::query()->where('code', $item['code'])->first();

            if ($language !== null) {
                $language->update([
                    'name' => $item['name'],
                    'locale' => $item['locale'],
                    'flag' => $item['flag'],
                    'meta' => [
                        'color' => $item['color'],
                    ],
                ]);

                continue;
            }

            Language::query()->create([
                'name' => $item['name'],
                'code' => $item['code'],
                'locale' => $item['locale'],
                'flag' => $item['flag'],
                'default' => Language::query()->count() === 0,
                'meta' => [
                    'color' => $item['color'],
                ],
            ]);
        }

        /** @var Collection<int, Language> $languages */
        $languages = Language::query()
            ->whereIn('code', $languageCodes)
            ->get();

        return $languages;
    }
}
