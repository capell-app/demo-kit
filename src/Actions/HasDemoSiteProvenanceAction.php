<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Site;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class HasDemoSiteProvenanceAction
{
    use AsFake;
    use AsObject;

    public function handle(Site $site): bool
    {
        return data_get($site->meta, 'demo_kit.provisioned') === true;
    }
}
