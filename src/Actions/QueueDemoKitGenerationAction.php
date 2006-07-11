<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\DemoKit\Data\DemoGenerationReviewData;
use Capell\DemoKit\Jobs\RunDemoKitGenerationJob;
use Capell\DemoKit\Models\DemoKitGenerationRun;
use Capell\DemoKit\Support\DemoKitPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

final class QueueDemoKitGenerationAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, mixed>|DemoGenerationReviewData  $review
     */
    public function handle(array|DemoGenerationReviewData $review, ?Model $actor = null, ?string $reviewFingerprint = null, bool $confirmed = false): DemoKitGenerationRun
    {
        if (! $confirmed) {
            throw ValidationException::withMessages([
                'generation' => __('capell-demo-kit::actions.queue_confirmation_required'),
            ]);
        }

        DemoKitPermissions::authorize($actor instanceof Authenticatable ? $actor : null);

        if ($review instanceof DemoGenerationReviewData) {
            $reviewFingerprint ??= $review->fingerprint;
            $parameters = $review->options;
        } else {
            $parameters = $review;
        }

        if (! is_string($reviewFingerprint) || $reviewFingerprint === '') {
            throw ValidationException::withMessages([
                'generation' => __('capell-demo-kit::actions.review_required'),
            ]);
        }

        $freshReview = BuildDemoGenerationReviewAction::run($parameters, $actor);
        if (! hash_equals($freshReview->fingerprint, $reviewFingerprint)) {
            throw ValidationException::withMessages([
                'generation' => __('capell-demo-kit::actions.review_changed'),
            ]);
        }

        if ($freshReview->hasBlockingCollisions()) {
            throw ValidationException::withMessages([
                'generation' => __('capell-demo-kit::actions.generation_collision'),
            ]);
        }

        $generationRun = Cache::lock('capell-demo-kit:generation-queue', 10)->block(5, function () use ($freshReview, $actor): DemoKitGenerationRun {
            ReclaimStalledDemoKitGenerationRunsAction::run();

            $activeRun = DemoKitGenerationRun::query()
                ->whereIn('status', [DemoKitGenerationRun::STATUS_QUEUED, DemoKitGenerationRun::STATUS_RUNNING])
                ->latest('id')
                ->first();

            if ($activeRun instanceof DemoKitGenerationRun) {
                if ($activeRun->fingerprint === $freshReview->fingerprint) {
                    return $activeRun;
                }

                throw ValidationException::withMessages([
                    'generation' => __('capell-demo-kit::actions.generation_in_progress'),
                ]);
            }

            $run = DemoKitGenerationRun::query()->create([
                'status' => DemoKitGenerationRun::STATUS_QUEUED,
                'parameters' => $freshReview->options,
                'fingerprint' => $freshReview->fingerprint,
                'review' => $freshReview->toArray(),
                'created_content' => [],
                'requested_by_type' => $actor?->getMorphClass(),
                'requested_by_id' => $actor?->getKey(),
            ]);

            $runId = $run->getKey();
            throw_unless(is_int($runId), RuntimeException::class, 'Demo Kit generation run must have an integer key.');

            dispatch(new RunDemoKitGenerationJob($runId));

            return $run;
        });

        throw_unless($generationRun instanceof DemoKitGenerationRun, RuntimeException::class, 'Demo Kit generation could not be queued.');

        return $generationRun;
    }
}
