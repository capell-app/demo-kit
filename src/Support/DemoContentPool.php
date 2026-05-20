<?php

declare(strict_types=1);

namespace Capell\DemoKit\Support;

final class DemoContentPool
{
    /**
     * @return array<string, array{name: string, locale: string, code: string, flag: string, color: string, default?: bool}>
     */
    public function languages(): array
    {
        return [
            'en' => ['name' => 'English', 'locale' => 'en_GB', 'code' => 'en', 'flag' => 'gb-eng', 'color' => '#f0f0f0', 'default' => true],
            'fr' => ['name' => 'French', 'locale' => 'fr_FR', 'code' => 'fr', 'flag' => 'fr', 'color' => '#0072bb'],
            'it' => ['name' => 'Italian', 'locale' => 'it_IT', 'code' => 'it', 'flag' => 'it', 'color' => '#008c45'],
            'de' => ['name' => 'German', 'locale' => 'de_DE', 'code' => 'de', 'flag' => 'de', 'color' => '#4d4a4a'],
            'es' => ['name' => 'Spanish', 'locale' => 'es_ES', 'code' => 'es', 'flag' => 'es', 'color' => '#f6b511'],
        ];
    }

    /**
     * @return list<string>
     */
    public function siteNames(): array
    {
        return [
            'Summit Works',
            'Northline Studio',
            'Harbour Digital',
            'Fieldstone Collective',
            'Atlas Manufacturing',
            'Brightwell Clinics',
            'Forge Education',
            'Oakridge Estates',
        ];
    }

    /**
     * @return list<string>
     */
    public function pageNames(): array
    {
        return [
            'About Us',
            'Services',
            'Projects',
            'Insights',
            'Case Studies',
            'Careers',
            'Contact',
            'Locations',
            'Partners',
            'Support',
            'Pricing',
            'Resources',
            'Sustainability',
            'Compliance',
            'Team',
            'News',
            'Events',
            'Customer Stories',
            'Implementation',
            'Training',
            'Consulting',
            'Integrations',
            'Security',
            'Roadmap',
            'Operations',
            'Delivery',
            'Strategy',
            'Research',
            'Procurement',
            'Onboarding',
            'Quality',
            'Governance',
        ];
    }
}
