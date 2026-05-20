<?php

declare(strict_types=1);

namespace Capell\DemoKit\Actions;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Simple dummy content generator moved out of config so callers can request
 * language-specific demo content programmatically. Returns a short HTML
 * paragraph made of a random number of sentences for the requested language.
 *
 * @method static string run(string $languageCode = 'en')
 */
class DummyContentGeneratorAction
{
    use AsAction;

    public function handle(string $languageCode = 'en'): string
    {
        $samples = [
            'en' => 'Capell demo content is written to exercise real publishing surfaces: reusable sections, structured summaries, layout blocks, and editorial workflows. It gives teams practical copy that can be reviewed without relying on placeholder filler.',
            'fr' => 'Le contenu de démonstration Capell présente des surfaces de publication réelles: sections réutilisables, résumés structurés, éléments de mise en page et flux éditoriaux. Il donne aux équipes un texte utile à examiner sans texte de remplissage.',
            'de' => 'Capell-Demoinhalte zeigen reale Veröffentlichungsbereiche: wiederverwendbare Abschnitte, strukturierte Zusammenfassungen, Layout-Blocke und redaktionelle Abläufe. Teams erhalten prüfbare Inhalte ohne Platzhaltertext.',
            'it' => 'I contenuti demo di Capell mostrano superfici editoriali reali: sezioni riutilizzabili, riepiloghi strutturati, blocki di layout e flussi di lavoro redazionali. Offrono testo utile da verificare senza contenuti segnaposto.',
            'es' => 'El contenido demo de Capell muestra superficies de publicación reales: secciones reutilizables, resúmenes estructurados, blockos de diseño y flujos editoriales. Ofrece texto práctico para revisar sin contenido de relleno.',
        ];

        $base = $samples[$languageCode] ?? $samples['en'];

        // Work with plain text sentences so we can return a variable-length
        // paragraph without breaking HTML.
        $plain = trim(strip_tags($base));

        $sentences = preg_split('/(?<=[.!?])\s+/', $plain, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($sentences) || $sentences === []) {
            return '<p>' . $plain . '</p>';
        }

        // Language-specific max sentences (keeps returned content varied per language)
        $maxMap = [
            'en' => 6,
            'fr' => 5,
            'de' => 5,
            'it' => 5,
            'es' => 5,
        ];

        $max = $maxMap[$languageCode] ?? 4;
        $max = min($max, count($sentences));

        $count = mt_rand(1, max(1, $max));

        $selected = array_slice($sentences, 0, $count);

        $content = implode(' ', $selected);

        // Simple multilingual-safe approach:
        // - Extract word tokens using a Unicode-aware regex
        // - Pick one reasonable token at random and wrap its first occurrence
        // This keeps the logic simple, language-agnostic, and does not affect
        // sentence selection because wrapping happens after selection.
        if (preg_match_all('/\p{L}[\p{L}\p{Mn}\p{Pd}\'’]*/u', $content, $matches) && isset($matches[0]) && filled($matches[0])) {
            $tokens = $matches[0];

            // Prefer tokens longer than one character where possible
            $candidates = array_values(array_filter($tokens, static fn (string $w): bool => mb_strlen($w, 'UTF-8') > 1));

            $pool = $candidates !== [] ? $candidates : $tokens;

            $pickIndex = mt_rand(0, count($pool) - 1);
            $word = $pool[$pickIndex];

            $pattern = '/' . preg_quote($word, '/') . '/iu';

            $content = preg_replace_callback($pattern, static fn (array $m): string => '<strong>' . $m[0] . '</strong>', $content, 1) ?? $content;
        }

        return '<p>' . $content . '</p>';
    }
}
