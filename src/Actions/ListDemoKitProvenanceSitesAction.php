<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Site;
use Capell\DemoKit\Support\DemoKitPermissions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class ListDemoKitProvenanceSitesAction
{
    use AsFake;
    use AsObject;

    /** @return Collection<int, Site> */
    public function handle(?Model $actor = null): Collection
    {
        DemoKitPermissions::authorize($actor instanceof Authenticatable ? $actor : null);

        return Site::query()->get()->filter(
            static fn (Site $site): bool => HasDemoSiteProvenanceAction::run($site),
        )->values();
    }
}
