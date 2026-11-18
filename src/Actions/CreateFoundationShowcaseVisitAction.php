<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\DemoKit\Support\Showcase\FoundationShowcasePageWriter;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Seeds the "Plan your visit" section of the Foundation showcase site: a parent page with
 * two child pages, created before the site navigation so the main menu lists all three.
 * The navigation capture pair reorders and shows this menu.
 *
 * @method static Page|null run(Site $site)
 */
final class CreateFoundationShowcaseVisitAction
{
    use AsFake;
    use AsObject;

    public const string Fixture = 'foundation-showcase-visit';

    public const string Slug = 'plan-your-visit';

    public const string GettingHereFixture = 'foundation-showcase-visit-getting-here';

    public const string TideTimesFixture = 'foundation-showcase-visit-tide-times';

    public function __construct(private readonly FoundationShowcasePageWriter $writer) {}

    public function handle(Site $site): ?Page
    {
        $parent = $this->writer->write(
            $site,
            self::Fixture,
            self::Slug,
            'Plan your visit',
            fn (Language $language): array => $language->code === 'fr' ? [
                'title' => 'Préparer votre visite',
                'summary' => 'Tout ce qu’il faut savoir avant de venir marcher le long de l’estuaire : accès, horaires des marées et sentiers ouverts.',
                'heading' => 'Avant de partir',
                'body' => 'Les sentiers longent des vasières qui changent à chaque marée. Consultez les horaires, prévoyez des chaussures imperméables et gardez vos distances avec les oiseaux au repos.',
            ] : [
                'title' => 'Plan your visit',
                'summary' => 'Everything to know before you walk the estuary: how to get here, tide times and which paths are open this week.',
                'heading' => 'Before you set out',
                'body' => 'The paths follow mudflats that change with every tide. Check the times, bring waterproof boots and give roosting birds plenty of room.',
            ],
        );

        if (! $parent instanceof Page) {
            return null;
        }

        $this->writer->write(
            $site,
            self::GettingHereFixture,
            'getting-here',
            'Getting here',
            fn (Language $language): array => $language->code === 'fr' ? [
                'title' => 'Comment venir',
                'summary' => 'Le parking du phare ouvre à l’aube. Le bus de la côte s’arrête au village, à vingt minutes à pied du premier observatoire.',
                'heading' => 'À pied, à vélo ou en bus',
                'body' => 'Un abri à vélos se trouve à l’entrée du sentier. Les chiens tenus en laisse sont les bienvenus sur la digue, mais pas dans les observatoires.',
            ] : [
                'title' => 'Getting here',
                'summary' => 'The lighthouse car park opens at dawn. The coast bus stops in the village, a twenty-minute walk from the first hide.',
                'heading' => 'On foot, by bike or by bus',
                'body' => 'There is a bike shelter at the start of the path. Dogs on leads are welcome on the sea wall but not inside the hides.',
            ],
            parent: $parent,
        );

        $this->writer->write(
            $site,
            self::TideTimesFixture,
            'tide-times',
            'Tide times',
            fn (Language $language): array => $language->code === 'fr' ? [
                'title' => 'Horaires des marées',
                'summary' => 'Les meilleures observations se font deux heures avant la pleine mer, quand les oiseaux se rapprochent du rivage.',
                'heading' => 'Choisir le bon moment',
                'body' => 'Aux grandes marées, la digue basse est fermée une heure de chaque côté de la pleine mer. Les panneaux à l’entrée indiquent l’itinéraire de remplacement.',
            ] : [
                'title' => 'Tide times',
                'summary' => 'The best watching is two hours before high water, when the birds are pushed closer to the shore.',
                'heading' => 'Pick your moment',
                'body' => 'On spring tides the low sea wall closes for an hour either side of high water. Signs at the entrance show the alternative route.',
            ],
            parent: $parent,
        );

        return $parent;
    }
}
