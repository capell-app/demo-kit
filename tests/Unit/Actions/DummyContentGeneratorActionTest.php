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
        ->and($ar)->toContain('<strong>')
        ->and(demoKitFirstSentence($fr))->not->toBe(demoKitFirstSentence($en))
        ->and(strip_tags($ar))->toMatch('/\p{Arabic}/u');

});

it('falls back to english for unknown languages', function (): void {
    $english = DummyContentGeneratorAction::run('en');
    $unknownHtml = DummyContentGeneratorAction::run('xx');

    $englishFirstSentence = demoKitFirstSentence($english);
    $unknownFirstSentence = demoKitFirstSentence($unknownHtml);

    expect($unknownHtml)->toBeString()->not()->toBe('')
        ->and($unknownHtml)->toStartWith('<p>')->toEndWith('</p>')
        ->and($unknownHtml)->toContain('<strong>')
        ->and($englishFirstSentence)->not->toBeFalse()
        ->and($unknownFirstSentence)->toBe($englishFirstSentence);
});

function demoKitFirstSentence(string $html): string|false
{
    return strtok(strip_tags($html), '.');
}
