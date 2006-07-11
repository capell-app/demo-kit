<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Facades\CapellCore;
use Capell\DemoKit\Providers\DemoKitServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

final class InsertExampleSiteDataAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException((string) __('capell-demo-kit::actions.example_site_data_environment_blocked'));
        }

        $package = CapellCore::getPackage(DemoKitServiceProvider::$packageName);
        $demoCommand = $package->getDemoCommand();

        if ($demoCommand === null) {
            throw new RuntimeException((string) __('capell-demo-kit::actions.example_site_data_command_missing'));
        }

        $exitCode = Artisan::call($demoCommand, $this->commandParams($data));

        if ($exitCode !== 0) {
            throw new RuntimeException((string) __('capell-demo-kit::actions.example_site_data_installation_failed'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function commandParams(array $data): array
    {
        $params = [
            '--force' => true,
        ];

        foreach ([
            'url' => 'url',
            'user' => 'user',
            'languages' => 'languages',
            'sites' => 'sites',
            'seed' => 'seed',
            'site_count' => 'site-count',
            'pages' => 'page-count',
        ] as $param => $option) {
            if (! array_key_exists($param, $data)) {
                continue;
            }

            if ($data[$param] === null) {
                continue;
            }

            if ($data[$param] === '') {
                continue;
            }

            if ($data[$param] === []) {
                continue;
            }

            $params['--' . $option] = $data[$param];
        }

        return $params;
    }
}
