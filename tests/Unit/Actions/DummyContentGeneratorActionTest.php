<?php

declare(strict_types=1);

use Capell\DemoKit\Actions\DummyContentGeneratorAction;

it('returns a non-empty html paragraph for supported languages', function (): void {
    $en = DummyContentGeneratorAction::run('en');
    $fr = DummyContentGeneratorAction::run('fr');
    $ar = DummyContentGeneratorAction::run('ar');

    expect($en)->toBeString()->not()->toBe('')
        ->and($en)->toStartWith('<p>')->toEndWith('</p>')
        ->and($fr)->toBeString()->not()->toBe('')
        ->and($fr)->toStartWith('<p>')->toEndWith('</p>')
        ->and($ar)->toBeString()->not()->toBe('')
        ->and($ar)->toStartWith('<p>')->toEndWith('</p>')
        ->and($en)->toContain('<strong>')->and($fr)->toContain('<strong>')
        ->and($ar)->toContain('<strong>');

});

it('falls back to english for unknown languages', function (): void {
    $unknown = DummyContentGeneratorAction::run('xx');

    expect($unknown)->toBeString()->not()->toBe('')
        ->and($unknown)->toStartWith('<p>')->toEndWith('</p>')
        ->and($unknown)->toContain('<strong>');
});
