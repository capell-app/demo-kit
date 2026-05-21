<?php

declare(strict_types=1);

use Capell\DemoKit\Health\DemoKitHealthCheck;

it('declares the supported Capell API version', function (): void {
    expect(DemoKitHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
});
