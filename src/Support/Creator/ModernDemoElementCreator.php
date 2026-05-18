<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support\Creator;

use Capell\Core\Models\Site;
use Capell\LayoutBuilder\Enums\ElementComponentEnum;
use Capell\LayoutBuilder\Enums\ElementTypeEnum;
use Capell\LayoutBuilder\Enums\LayoutTypeEnum;
use Capell\LayoutBuilder\Models\Element;
use Capell\LayoutBuilder\Support\Creator\TypeCreator;

abstract class ModernDemoElementCreator extends StandardDemoElementCreator
{
    public function createModernFeatureListElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-feature-list'], [
            'name' => 'Modern Feature List',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApFeatureList,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Built for teams who need CMS control and engineering discipline',
                    'content' => '<p>Capell keeps the public frontend fast while giving editors, developers, and release owners clear ownership of the same content surface.</p>',
                ],
            );
        }

        $element->assets()->delete();

        $features = [
            ['icon' => 'heroicon-o-rocket-launch', 'title' => 'Static-first public pages', 'description' => 'Serve generated HTML and keep render-time cache work from making the frontend feel brittle.'],
            ['icon' => 'heroicon-o-lock-closed', 'title' => 'Admin-safe editing', 'description' => 'Filament resources control the content without exposing authoring metadata in public output.'],
            ['icon' => 'heroicon-o-globe-alt', 'title' => 'Multi-site and multi-language', 'description' => 'One install can support multiple domains, trees, languages, and layouts.'],
            ['icon' => 'heroicon-o-puzzle-piece', 'title' => 'Package-owned runtime', 'description' => 'Every package owns the frontend assets it needs and doctor verifies those builds exist.'],
            ['icon' => 'heroicon-o-code-bracket-square', 'title' => 'Laravel-native extension points', 'description' => 'Actions, DTOs, render hooks, schema extenders, and package manifests keep integrations maintainable.'],
            ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Install health reporting', 'description' => 'A fresh demo ends with explicit checks for homepage, elements, assets, users, and generated CSS.'],
        ];

        foreach ($features as $feature) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $feature['title']], [
                'meta' => ['icon' => $feature['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $feature['title'], 'content' => sprintf('<p>%s</p>', $feature['description'])],
                );
            }

            $element->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $element;
    }

    public function createModernTeamMembersElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-team-members'], [
            'name' => 'Modern Team Members',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApTeamMembers,
                'columns' => 3,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['title' => 'Our Team'],
            );
        }

        if ($element->assets()->exists()) {
            return $element;
        }

        $members = [
            [
                'icon' => '👩‍💼',
                'name' => 'Alex Morgan',
                'position' => 'Product Lead',
                'bio' => 'Creative designer with 5+ years building user-centred digital products.',
                'tags' => ['Design', 'Leadership'],
                'social' => ['twitter' => 'https://twitter.com', 'linkedin' => 'https://linkedin.com'],
            ],
            [
                'icon' => '👨‍🔬',
                'name' => 'Emma Davis',
                'position' => 'Engineering Manager',
                'bio' => 'Full-stack developer and systems architect with a passion for clean APIs.',
                'tags' => ['Engineering', 'Architecture'],
                'social' => ['github' => 'https://github.com', 'linkedin' => 'https://linkedin.com'],
            ],
            [
                'icon' => '🧑‍💼',
                'name' => 'James Wilson',
                'position' => 'CEO & Co-founder',
                'bio' => 'Serial entrepreneur and technology visionary driving our strategic direction.',
                'tags' => ['Strategy', 'Leadership'],
                'social' => ['twitter' => 'https://twitter.com', 'linkedin' => 'https://linkedin.com'],
            ],
        ];

        foreach ($members as $member) {
            $section = $this->contentModel::query()->firstOrCreate(['name' => $member['name']], [
                'meta' => [
                    'icon' => $member['icon'],
                    'position' => $member['position'],
                    'tags' => $member['tags'],
                    'social' => $member['social'],
                ],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $member['name'], 'content' => sprintf('<p>%s</p>', $member['bio'])],
                );
            }

            $element->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $element;
    }

    public function createModernPricingTableElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-pricing-table'], [
            'name' => 'Modern Pricing Table',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApPricingTable,
                'currency' => '$',
                'billing_options' => 'both',
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                ['title' => 'Simple, Transparent Pricing'],
            );
        }

        if ($element->assets()->exists()) {
            return $element;
        }

        $plans = [
            [
                'name' => 'Starter',
                'description' => 'For individuals and small projects',
                'price' => '29',
                'price_annual' => '290',
                'featured' => false,
                'cta_label' => 'Get Started',
                'cta_url' => '#',
                'features' => ['Up to 5 pages', '1 site', 'Email support', 'Basic elements'],
            ],
            [
                'name' => 'Professional',
                'description' => 'For growing teams and businesses',
                'price' => '79',
                'price_annual' => '790',
                'featured' => true,
                'cta_label' => 'Start Free Trial',
                'cta_url' => '#',
                'features' => ['Unlimited pages', '5 sites', 'Priority support', 'All elements', 'Multi-language'],
            ],
            [
                'name' => 'Enterprise',
                'description' => 'For large-scale deployments',
                'price' => 'Custom',
                'price_annual' => 'Custom',
                'featured' => false,
                'cta_label' => 'Contact Sales',
                'cta_url' => '#',
                'features' => ['Unlimited everything', 'Dedicated support', 'Custom integrations', 'SLA guarantee'],
            ],
        ];

        foreach ($plans as $plan) {
            $section = $this->contentModel::query()->firstOrCreate(['name' => $plan['name']], [
                'meta' => [
                    'price' => $plan['price'],
                    'price_annual' => $plan['price_annual'],
                    'featured' => $plan['featured'],
                    'cta_label' => $plan['cta_label'],
                    'cta_url' => $plan['cta_url'],
                    'features' => $plan['features'],
                ],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $plan['name'], 'content' => sprintf('<p>%s</p>', $plan['description'])],
                );
            }

            $element->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $element;
    }

    public function createModernTestimonialsElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-testimonials'], [
            'name' => 'Modern Testimonials',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApTestimonials,
                'columns' => 2,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'What a release-ready Capell site should prove',
                    'content' => '<p>The default demo should make the CMS story obvious from the first load: editable content, fast frontend, package runtime, and admin traceability.</p>',
                ],
            );
        }

        $element->assets()->delete();

        $testimonials = [
            ['icon' => 'heroicon-o-user-circle', 'author' => 'Content editor', 'position' => 'Homepage owner', 'quote' => 'I can change the hero, cards, media, and CTA from admin records without waiting on a template deployment.'],
            ['icon' => 'heroicon-o-command-line', 'author' => 'Laravel developer', 'position' => 'Package builder', 'quote' => 'The package boundaries are clear: runtime assets, schema, render hooks, and demo fixtures stay with the package that owns them.'],
            ['icon' => 'heroicon-o-shield-check', 'author' => 'Release lead', 'position' => 'Install verifier', 'quote' => 'The installer tells me whether the homepage, assets, demo content, and frontend CSS are ready before I hand the site over.'],
        ];

        foreach ($testimonials as $testimonial) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $testimonial['author']], [
                'meta' => [
                    'icon' => $testimonial['icon'],
                    'position' => $testimonial['position'],
                ],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $testimonial['author'], 'content' => sprintf('<p>%s</p>', $testimonial['quote'])],
                );
            }

            $element->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $element;
    }

    public function createModernFaqElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-faq'], [
            'name' => 'Modern FAQ Section',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApFaqSection,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Questions this demo answers',
                    'content' => '<p>These are the checks a serious CMS demo needs to make obvious before release.</p>',
                ],
            );
        }

        $element->assets()->delete();

        $faqs = [
            ['category' => 'Editing', 'question' => 'Can every visible homepage section be edited in admin?', 'answer' => 'Yes. The hero, cards, feature list, gallery, testimonials, FAQ, and CTA are backed by element translations, element meta, assets, and media records.'],
            ['category' => 'Frontend', 'question' => 'Does the public theme own its runtime styling and JavaScript?', 'answer' => 'Yes. Foundation registers and publishes its own frontend build assets instead of relying on another package runtime.'],
            ['category' => 'Install', 'question' => 'How do I know the demo installed correctly?', 'answer' => 'Run capell:doctor --install-summary. It checks tables, packages, homepage data, elements, runtime assets, generated CSS, and admin access.'],
            ['category' => 'Architecture', 'question' => 'Is this just a landing page?', 'answer' => 'No. The default demo is a working CMS surface that demonstrates Capell page records, layout containers, elements, media, and package renderers.'],
        ];

        foreach ($faqs as $faq) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $faq['question']], [
                'meta' => ['category' => $faq['category']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $faq['question'], 'content' => sprintf('<p>%s</p>', $faq['answer'])],
                );
            }

            $element->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $element;
    }

    public function createModernStatsSectionElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-stats'], [
            'name' => 'Modern Stats Section',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApStatsSection,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'Proof points for a healthier release',
                    'content' => '<p>The default demo now checks the signals that matter before a Capell site is handed over.</p>',
                ],
            );
        }

        $element->assets()->delete();

        $stats = [
            ['icon' => 'heroicon-o-squares-2x2', 'label' => 'Homepage elements', 'value' => '10'],
            ['icon' => 'heroicon-o-photo', 'label' => 'Demo media records', 'value' => '8+'],
            ['icon' => 'heroicon-o-bolt', 'label' => 'Runtime asset checks', 'value' => '2'],
            ['icon' => 'heroicon-o-check-badge', 'label' => 'Doctor summary', 'value' => 'Pass'],
        ];

        foreach ($stats as $stat) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $stat['label']], [
                'meta' => ['icon' => $stat['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $stat['label'], 'content' => sprintf('<p>%s</p>', $stat['value'])],
                );
            }

            $element->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $element;
    }

    public function createModernAlternatingContentElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-alternating-content'], [
            'name' => 'Modern Alternating Content',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApAlternatingContent,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'From model to public page',
                    'content' => '<p>Capell keeps the frontend impressive because every layer has an owner and a verification path.</p>',
                ],
            );
        }

        $element->assets()->delete();

        $steps = [
            ['icon' => 'heroicon-o-circle-stack', 'position' => 'left', 'title' => 'Model the content', 'description' => 'Define page types, elements, translations, and media so content stays structured instead of trapped in templates.'],
            ['icon' => 'heroicon-o-rectangle-group', 'position' => 'right', 'title' => 'Compose the layout', 'description' => 'Place package-owned elements into layout containers and keep every visible section editable from the admin.'],
            ['icon' => 'heroicon-o-paper-airplane', 'position' => 'left', 'title' => 'Publish and verify', 'description' => 'Generate frontend resources, warm static output, and let doctor report missing homepage, asset, or fixture problems.'],
        ];

        foreach ($steps as $step) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $step['title']], [
                'meta' => ['icon' => $step['icon'], 'position' => $step['position']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $step['title'], 'content' => sprintf('<p>%s</p>', $step['description'])],
                );
            }

            $element->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $element;
    }

    public function createModernProcessStepsElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-process-steps'], [
            'name' => 'Modern Process Steps',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApProcessSteps,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'The publishing path Capell demonstrates',
                    'content' => '<p>The demo homepage should show a real CMS workflow, not a pile of disconnected sample elements.</p>',
                ],
            );
        }

        $element->assets()->delete();

        $steps = [
            ['icon' => 'heroicon-o-cog-6-tooth', 'title' => 'Install packages', 'description' => 'Core, frontend, Foundation theme, navigation, search, and content packages register their own setup and runtime surfaces.'],
            ['icon' => 'heroicon-o-swatch', 'title' => 'Seed the showcase', 'description' => 'Demo fixtures create Capell-specific elements, sections, media, and translations in the right homepage order.'],
            ['icon' => 'heroicon-o-arrow-path', 'title' => 'Rebuild resources', 'description' => 'Tailwind input, published runtime manifests, and static frontend resources are generated after package demo steps.'],
            ['icon' => 'heroicon-o-clipboard-document-check', 'title' => 'Run doctor', 'description' => 'The installer ends with a health summary that catches broken homepage, runtime, and fixture states immediately.'],
        ];

        foreach ($steps as $step) {
            $section = $this->contentModel::query()->updateOrCreate(['name' => $step['title']], [
                'meta' => ['icon' => $step['icon']],
            ]);

            foreach (Site::getDefault()?->languages ?? [] as $language) {
                $this->translationsFor($section)->updateOrCreate(
                    ['language_id' => $language->id],
                    ['title' => $step['title'], 'content' => sprintf('<p>%s</p>', $step['description'])],
                );
            }

            $element->assets()->firstOrCreate([
                'asset_id' => $section->id,
                'asset_type' => resolve($this->contentModel)->getMorphClass(),
            ]);
        }

        return $element;
    }

    public function createModernImageGalleryElement(): Element
    {
        $elementType = $this->typeModel::query()->where('type', LayoutTypeEnum::Element)
            ->firstWhere('key', ElementTypeEnum::Assets);

        if ($elementType === null) {
            $elementType = resolve(TypeCreator::class)->assetsElementType();
        }

        $element = $this->elementModel::query()->firstOrCreate(['key' => 'modern-image-gallery'], [
            'name' => 'Modern Image Gallery',
            'blueprint_id' => $elementType->id,
            'meta' => [
                'component' => ElementComponentEnum::ApImageGallery,
                'columns' => 3,
                'margin' => ['lg'],
            ],
        ]);

        foreach (Site::getDefault()?->languages ?? [] as $language) {
            $element->translations()->updateOrCreate(
                ['language_id' => $language->id],
                [
                    'title' => 'A curated media surface, still CMS-owned',
                    'content' => '<p>The gallery proves that images are not just decorative assets in the theme. They are media records that can be replaced, reordered, and rendered consistently.</p>',
                ],
            );
        }

        if ($element->assets()->exists()) {
            return $element;
        }

        for ($i = 1; $i <= 6; $i++) {
            $this->createElementMedia($element);
        }

        return $element;
    }
}
