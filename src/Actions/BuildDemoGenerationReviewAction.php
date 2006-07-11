<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Site;
use Capell\DemoKit\Data\DemoGenerationPlanData;
use Capell\DemoKit\Data\DemoGenerationReviewData;
use Capell\DemoKit\Data\DemoPagePlanData;
use Capell\DemoKit\Data\DemoSiteGenerationPlanData;
use Capell\DemoKit\Support\DemoKitPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildDemoGenerationReviewAction
{
    use AsFake;
    use AsObject;

    private const int DEFAULT_REVIEW_SEED = 3080308;

    /**
     * @param  array<string, mixed>  $options
     */
    public function handle(array $options = [], ?Model $actor = null): DemoGenerationReviewData
    {
        $this->assertAllowed($actor);
        $normalized = $this->normalizeOptions($options);
        $planOptions = $normalized;
        unset($planOptions['url']);
        /** @var array{sites?: list<string>, site_count?: int, pages?: int, languages?: list<string>, seed?: int|null} $planOptions */
        $plan = BuildDemoGenerationPlanAction::run($planOptions);
        $sites = array_map(
            static fn (DemoSiteGenerationPlanData $site): string => $site->name,
            $plan->sites,
        );
        $existingSites = Site::query()->whereIn('name', $sites)->get();
        $collisions = [];

        foreach ($sites as $siteName) {
            $site = $existingSites->firstWhere('name', $siteName);

            if (! $site instanceof Site) {
                continue;
            }

            $provenanceOwned = HasDemoSiteProvenanceAction::run($site);
            $collisions[] = [
                'name' => $siteName,
                'site_id' => is_int($site->getKey()) ? $site->getKey() : null,
                'provenance_owned' => $provenanceOwned,
                'outcome' => $provenanceOwned ? 'reuse' : 'blocked',
            ];
        }

        $fingerprint = hash('sha256', json_encode([
            'options' => $normalized,
            'plan' => $plan->fingerprint(),
            'collisions' => $collisions,
        ], JSON_THROW_ON_ERROR));

        return new DemoGenerationReviewData(
            plan: $plan,
            options: $normalized,
            fingerprint: $fingerprint,
            siteCount: count($plan->sites),
            languageCount: array_sum(array_map(
                static fn (DemoSiteGenerationPlanData $site): int => count($site->languageCodes),
                $plan->sites,
            )),
            pageCount: array_sum(array_map(
                static fn (DemoSiteGenerationPlanData $site): int => $site->pageCount(),
                $plan->sites,
            )),
            mediaCount: $this->mediaCount($plan),
            collisions: $collisions,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{url?: string, sites?: list<string>, languages?: list<string>, site_count?: int, pages?: int, seed?: int}
     */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach (['url', 'seed'] as $key) {
            if (! array_key_exists($key, $options) || $options[$key] === null || $options[$key] === '') {
                continue;
            }

            if ($key === 'seed' && is_numeric($options[$key])) {
                $normalized[$key] = (int) $options[$key];
            } elseif ($key === 'url' && is_scalar($options[$key])) {
                $normalized[$key] = trim((string) $options[$key]);
            }
        }

        foreach (['sites', 'languages'] as $key) {
            if (! array_key_exists($key, $options)) {
                continue;
            }

            $values = is_array($options[$key])
                ? $options[$key]
                : (is_scalar($options[$key]) ? explode(',', (string) $options[$key]) : []);
            $values = array_values(array_unique(array_filter(
                array_map(
                    static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
                    $values,
                ),
                static fn (string $value): bool => $value !== '',
            )));

            if ($values !== []) {
                $normalized[$key] = $values;
            }
        }

        foreach (['site_count', 'pages'] as $key) {
            if (! array_key_exists($key, $options) || ! is_numeric($options[$key])) {
                continue;
            }

            $value = (int) $options[$key];
            if ($value > 0) {
                $normalized[$key] = $value;
            }
        }

        // A review must be repeatable while it is open. The CLI deliberately
        // randomises a null seed, so the admin workflow supplies a stable
        // profile seed unless the operator explicitly chose one.
        $configuredSeed = config('capell-demo-kit.seed');
        $normalized['seed'] ??= is_numeric($configuredSeed)
            ? (int) $configuredSeed
            : self::DEFAULT_REVIEW_SEED;

        $ordered = [];
        foreach (['url', 'sites', 'languages', 'site_count', 'pages', 'seed'] as $key) {
            if (array_key_exists($key, $normalized)) {
                $ordered[$key] = $normalized[$key];
            }
        }

        return $ordered;
    }

    private function mediaCount(DemoGenerationPlanData $plan): int
    {
        return array_sum(array_map(
            fn (DemoSiteGenerationPlanData $site): int => array_sum(array_map($this->pageMediaCount(...), $site->pages)),
            $plan->sites,
        ));
    }

    private function pageMediaCount(DemoPagePlanData $page): int
    {
        return $page->mediaCount + array_sum(array_map($this->pageMediaCount(...), $page->children));
    }

    private function assertAllowed(?Model $actor): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages([
                'generation' => __('capell-demo-kit::actions.example_site_data_environment_blocked'),
            ]);
        }

        DemoKitPermissions::authorize($actor instanceof Authenticatable ? $actor : null);
    }
}
