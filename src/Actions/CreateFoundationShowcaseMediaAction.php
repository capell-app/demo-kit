<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Media;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\DemoKit\Support\Creator\DemoResourceResolver;
use Capell\DemoKit\Support\Showcase\FoundationShowcasePageWriter;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Seeds the Foundation showcase photo story: a page whose lead image carries an editor-set
 * focal point and alt text in every site language. The media capture pair shows the editor
 * setting both and the public page rendering the crop at phone width.
 *
 * @method static Page|null run(Site $site)
 */
final class CreateFoundationShowcaseMediaAction
{
    use AsFake;
    use AsObject;

    public const string Fixture = 'foundation-showcase-media';

    public const string Slug = 'knots-at-the-high-tide-roost';

    public const string Image = 'birds';

    /** Focal point, as a percentage of width and height, on the densest part of the flock. */
    public const array FocalPoint = ['x' => 62, 'y' => 38];

    public function __construct(
        private readonly FoundationShowcasePageWriter $writer,
        private readonly DemoResourceResolver $resources,
    ) {}

    public function handle(Site $site): ?Page
    {
        $page = $this->writer->write(
            $site,
            self::Fixture,
            self::Slug,
            'Knots at the high-tide roost',
            fn (Language $language): array => $language->code === 'fr' ? [
                'title' => 'Les bécasseaux au reposoir de pleine mer',
                'summary' => 'À marée haute, des milliers de bécasseaux maubèches se serrent sur la dernière langue de sable, en attendant que l’eau redescende.',
                'heading' => 'Une photo, prise de loin',
                'body' => 'Cette image a été prise depuis l’observatoire, avec un long téléobjectif. Rester à distance garde le groupe tranquille : un seul envol coûte aux oiseaux une énergie dont ils ont besoin pour la migration.',
            ] : [
                'title' => 'Knots at the high-tide roost',
                'summary' => 'At high water, thousands of knots pack onto the last spit of sand and wait for the tide to fall again.',
                'heading' => 'One photograph, taken from a distance',
                'body' => 'This picture was taken from the hide with a long lens. Keeping back lets the flock settle: every time it takes flight, the birds burn energy they need for migration.',
            ],
        );

        if (! $page instanceof Page) {
            return null;
        }

        $this->attachImage($page, $site);

        return $page;
    }

    private function attachImage(Page $page, Site $site): void
    {
        $collection = MediaCollectionEnum::Image->value;

        if ($page->getFirstMedia($collection) instanceof Media) {
            return;
        }

        try {
            $file = $this->resources->resolve('img') . '/' . self::Image . '.jpg';
        } catch (Throwable) {
            return;
        }

        if (! File::exists($file)) {
            return;
        }

        $page->addMedia($file)
            ->preservingOriginal()
            ->usingName('Knots at the high-tide roost')
            ->toMediaCollection($collection);

        $media = $page->refresh()->getFirstMedia($collection);

        if (! $media instanceof Media) {
            return;
        }

        $media->setFocalPoint(self::FocalPoint['x'], self::FocalPoint['y'])->save();

        foreach ($site->languages as $language) {
            Translation::query()->updateOrCreate(
                [
                    'language_id' => $language->getKey(),
                    'translatable_type' => $media->getMorphClass(),
                    'translatable_id' => $media->getKey(),
                ],
                [
                    'title' => null,
                    'meta' => $this->imageMeta($language),
                ],
            );
        }
    }

    /** @return array{alt: string, credit: string} */
    private function imageMeta(Language $language): array
    {
        return $language->code === 'fr'
            ? ['alt' => 'Une bande serrée de bécasseaux maubèches posée sur un banc de sable à marée haute.', 'credit' => 'Groupe d’observation de l’estuaire']
            : ['alt' => 'A tight flock of knots resting on a sandbank at high tide.', 'credit' => 'Estuary field group'];
    }
}
