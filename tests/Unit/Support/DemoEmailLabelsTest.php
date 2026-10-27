<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\DemoKit\Support\KitchenSinkPublicLayoutWidgetPayloadContributor;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Illuminate\View\ComponentAttributeBag;

require_once __DIR__ . '/../../../../theme-foundation/tests/Support/WidgetViewFixture.php';

it('labels the fallback contact email input with the shared glossary label', function (): void {
    $widget = foundationWidgetViewFixture(count: 0);
    $page = new Page(['name' => 'Contact']);
    $page->setRelation('translation', null);
    $page->setRelation('blueprint', null);

    $this->view('capell-demo-kit::components.widget.demo-page-content', [
        'pageRecord' => $page,
        'widget' => $widget,
        'container' => [],
        'containerKey' => 'main',
        'loop' => (object) ['index' => 0],
        'attributes' => new ComponentAttributeBag,
    ])->assertContainsElement('label[for="contact-email"]', ['text' => 'Email address'])
        ->assertContainsElement('input#contact-email', ['name' => 'email', 'type' => 'email']);
});

it('labels the kitchen sink email inputs with the shared glossary label', function (string $sectionKey): void {
    $widget = new Widget([
        'key' => 'kitchen-sink-forms',
        'meta' => ['sections' => [['key' => $sectionKey, 'heading' => 'Form example']]],
    ]);
    $html = resolve(KitchenSinkPublicLayoutWidgetPayloadContributor::class)
        ->html($widget, new Page, new Language, 'main', 1);

    TestResponse::fromBaseResponse(new Response($html))
        ->assertContainsElement('label[for="' . $sectionKey . '-email"]', ['text' => 'Email address'])
        ->assertContainsElement('input#' . $sectionKey . '-email', ['name' => 'email', 'type' => 'email']);
})->with(['form-field-demo', 'full-form']);
