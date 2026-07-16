<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Site;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class MarkDemoSiteProvenanceAction
{
    use AsFake;
    use AsObject;

    public function handle(Site $site): Site
    {
        $meta = is_array($site->meta) ? $site->meta : [];
        $demoKitMeta = data_get($meta, 'demo_kit');
        $demoKitMeta = is_array($demoKitMeta) ? $demoKitMeta : [];

        data_set($meta, 'demo_kit', [
            ...$demoKitMeta,
            'provisioned' => true,
            'provisioned_at' => now()->toAtomString(),
        ]);

        $site->forceFill(['meta' => $meta])->save();

        return $site;
    }
}
