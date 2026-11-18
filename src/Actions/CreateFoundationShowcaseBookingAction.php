<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Enums\LayoutEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Layout;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\TableName;
use Capell\DemoKit\Support\Showcase\FoundationShowcasePageWriter;
use Capell\FormBuilder\Models\Form;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Enums\WidgetTypeEnum;
use Capell\LayoutBuilder\Models\Widget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Seeds the Foundation showcase booking page: a Form Builder form for a guided field walk,
 * placed under the page copy by a layout that extends the Default layout with one form
 * container. The form capture pair shows the editor building the form and the public page
 * rendering it at phone width. Does nothing when Form Builder is not installed.
 *
 * @method static Page|null run(Site $site)
 */
final class CreateFoundationShowcaseBookingAction
{
    use AsFake;
    use AsObject;

    public const string Fixture = 'foundation-showcase-booking';

    public const string Slug = 'book-a-guided-walk';

    public const string FormHandle = 'field-walk-booking';

    public const string WidgetKey = 'foundation-showcase-booking-form';

    public const string LayoutKey = 'foundation-showcase-booking';

    public const string FormContainer = 'booking-form';

    public function __construct(private readonly FoundationShowcasePageWriter $writer) {}

    public function handle(Site $site): ?Page
    {
        $existing = $this->writer->find($site, self::Fixture);

        if ($existing instanceof Page) {
            return $existing;
        }

        $formModel = $this->formModel();

        if ($formModel === null) {
            return null;
        }

        $layout = $this->bookingLayout();

        if (! $layout instanceof Layout) {
            return null;
        }

        $this->ensureForm($formModel, $site);

        return $this->writer->write(
            $site,
            self::Fixture,
            self::Slug,
            'Book a guided walk',
            fn (Language $language): array => $language->code === 'fr' ? [
                'title' => 'Réserver une sortie guidée',
                'summary' => 'Chaque samedi matin, un bénévole emmène un petit groupe le long de la digue pour observer les oiseaux au reposoir.',
                'heading' => 'Ce qu’il faut savoir',
                'body' => 'Les sorties durent deux heures et partent du parking du phare. Nous prêtons des jumelles. Les groupes sont limités à douze personnes pour ne pas déranger les oiseaux.',
            ] : [
                'title' => 'Book a guided walk',
                'summary' => 'Every Saturday morning a volunteer leads a small group along the sea wall to watch the birds at the roost.',
                'heading' => 'What to expect',
                'body' => 'Walks last two hours and start from the lighthouse car park. We lend binoculars. Groups are kept to twelve people so the birds are not disturbed.',
            ],
            $layout,
        );
    }

    /** @return class-string<Model>|null */
    private function formModel(): ?string
    {
        if (! CapellCore::isPackageAvailable('capell-app/form-builder')) {
            return null;
        }

        if (! Schema::hasTable(TableName::resolve('forms'))) {
            return null;
        }

        return Form::class;
    }

    /** @param class-string<Model> $formModel */
    private function ensureForm(string $formModel, Site $site): void
    {
        $formModel::query()->firstOrCreate(
            [
                'site_id' => $site->getKey(),
                'handle' => self::FormHandle,
            ],
            [
                'name' => 'Guided walk booking',
                'description' => 'Booking requests for the Saturday guided walks.',
                'schema' => [
                    ['key' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                    ['key' => 'email', 'label' => 'Email address', 'type' => 'email', 'required' => true, 'validation_rules' => ['email']],
                    [
                        'key' => 'walk',
                        'label' => 'Which walk?',
                        'type' => 'select',
                        'required' => true,
                        'options' => [
                            'roost-watch' => 'High-tide roost watch',
                            'dawn-chorus' => 'Dawn chorus on the sea wall',
                            'family-rockpools' => 'Family rock-pool walk',
                        ],
                    ],
                    ['key' => 'party_size', 'label' => 'How many people?', 'type' => 'number', 'required' => true],
                    ['key' => 'notes', 'label' => 'Anything we should know?', 'type' => 'textarea', 'required' => false],
                    ['key' => 'company_website', 'label' => 'Company website', 'type' => 'honeypot'],
                ],
                'settings' => [
                    'success_message' => 'Thanks, we will confirm your place by email.',
                    'store_submissions' => true,
                    'collect_ip_address' => false,
                    'collect_user_agent' => false,
                ],
                'is_active' => true,
            ],
        );
    }

    private function bookingLayout(): ?Layout
    {
        $existing = Layout::query()->firstWhere('key', self::LayoutKey);

        if ($existing instanceof Layout) {
            return $existing;
        }

        $default = Layout::query()->firstWhere('key', LayoutEnum::Default);
        $widget = $this->bookingWidget();

        if (! $default instanceof Layout || ! $widget instanceof Widget) {
            return null;
        }

        $containers = $default->getAttribute('containers');
        $containers = is_array($containers) ? $containers : [];
        $containers[self::FormContainer] = [
            'meta' => ['colspan' => 12, 'spacing' => 'lg'],
            'widgets' => [['widget_key' => $widget->key]],
        ];

        return Layout::query()->create([
            'key' => self::LayoutKey,
            'name' => 'Guided walk booking',
            'group' => $default->getAttribute('group'),
            'containers' => $containers,
            'meta' => ['description' => 'The default page layout with a booking form below the page copy.'],
            'default' => false,
            'status' => true,
        ]);
    }

    private function bookingWidget(): ?Widget
    {
        $widgetType = Blueprint::query()
            ->where('type', LayoutTypeEnum::Widget->value)
            ->firstWhere('key', WidgetTypeEnum::Default->value);

        if (! $widgetType instanceof Blueprint) {
            return null;
        }

        return Widget::query()->updateOrCreate(
            ['key' => self::WidgetKey],
            [
                'name' => 'Guided walk booking form',
                'blueprint_id' => $widgetType->getKey(),
                'component' => 'capell-form-builder::widget.form',
                'is_livewire' => true,
                'meta' => [
                    'component' => 'capell-form-builder::widget.form',
                    'form_handle' => self::FormHandle,
                ],
                'status' => true,
            ],
        );
    }
}
