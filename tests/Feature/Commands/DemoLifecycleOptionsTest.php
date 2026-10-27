<?php

declare(strict_types=1);

use Capell\DemoKit\Console\Commands\AdminDemoCommand;
use Capell\DemoKit\Console\Commands\DemoCommand;
use Capell\DemoKit\Console\Commands\FullDemoCommand;
use Symfony\Component\Console\Input\ArgvInput;

it('parses lifecycle option values through each shipped Demo Kit command signature', function (bool $equalsSyntax): void {
    $options = [
        'url' => 'https://reviewed-foundation.test',
        'sites' => 'Existing Foundation,Disposable Foundation',
        'languages' => 'en,fr',
    ];

    foreach ([new DemoCommand, new AdminDemoCommand, new FullDemoCommand] as $command) {
        $arguments = ['artisan'];
        foreach ($options as $name => $value) {
            array_push($arguments, ...($equalsSyntax ? ['--' . $name . '=' . $value] : ['--' . $name, $value]));
        }

        $input = new ArgvInput($arguments, $command->getDefinition());
        expect($input->getOptions())->toMatchArray($options);
    }
})->with(['equals syntax' => true, 'separate values' => false]);
