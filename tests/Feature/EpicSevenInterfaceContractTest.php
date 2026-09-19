<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Task 10 — contraintes qui pèsent sur chaque page de l'Epic 7 (AC 6, 8, 13, 25, 28 à 33, 37, 45).
 *
 * Les pages sont vérifiées sur leur source : c'est le seul moyen d'opposer en CI des règles qui,
 * autrement, ne se constatent qu'à l'œil sur un téléphone.
 */
class EpicSevenInterfaceContractTest extends TestCase
{
    /** Les neuf pages livrées par la story 7.1. */
    private const PAGES = [
        'WeeklyReviews/Index',
        'WeeklyReviews/Show',
        'ImprovementPlans/Index',
        'ImprovementPlans/Show',
        'Internships/Index',
        'Internships/Show',
        'Internships/Intakes/Index',
        'Internships/Intakes/Show',
        'Internships/Tutors/Index',
        'Internships/Slots/Index',
    ];

    /**
     * NFR3 : aucune ressource tierce chargée à l'exécution, aucune bibliothèque de composants.
     */
    public function test_no_page_loads_a_third_party_resource(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->source($page);

            foreach (['https://', 'http://', 'cdn.', 'googleapis', '@import url('] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$page} charge une ressource externe.");
            }
        }
    }

    /**
     * SOC-09 : utilisable à 320 px, sans défilement horizontal. Les conteneurs sont en `min-w-0`
     * et aucune largeur fixe n'est posée.
     */
    public function test_no_page_can_scroll_horizontally_at_320_pixels(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->source($page);

            $this->assertStringContainsString('min-w-0', $source, "{$page} doit contraindre ses conteneurs.");

            foreach (['overflow-x-scroll', 'w-screen', 'min-w-max', 'whitespace-nowrap'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$page} peut déborder horizontalement.");
            }
        }
    }

    /**
     * SOC-09 : les cibles tactiles font au moins 44 × 44 px, portées par l'utilitaire
     * `touch-target` du système de design interne.
     */
    public function test_interactive_elements_use_the_touch_target_utility(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->source($page);

            // Une page sans élément interactif propre n'a rien à prouver ici.
            if (! str_contains($source, '<button') && ! str_contains($source, '<select') && ! str_contains($source, 'type="date"') && ! str_contains($source, 'type="time"')) {
                continue;
            }

            $this->assertStringContainsString('touch-target', $source, "{$page} doit dimensionner ses cibles tactiles.");
        }
    }

    /**
     * SOC-10 : chaque champ porte un libellé associé, et les erreurs sont annoncées.
     */
    public function test_every_field_carries_an_associated_label(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->source($page);

            if (! str_contains($source, '<textarea') && ! str_contains($source, '<input') && ! str_contains($source, '<select')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/<label[^>]*\sfor="/',
                $source,
                "{$page} doit associer ses libellés à leur champ.",
            );
        }
    }

    /**
     * SOC-10 : aucune information n'est portée par la seule couleur. Toute classe de teinte
     * d'état s'accompagne d'un libellé ou d'un glyphe.
     */
    public function test_no_information_is_carried_by_colour_alone(): void
    {
        // L'écran de charge des tuteurs est le seul à teinter une carte selon un état.
        $source = $this->source('Internships/Tutors/Index');

        $this->assertStringContainsString('bg-warning-soft', $source);
        $this->assertStringContainsString('limit_label', $source, 'La saturation doit être écrite, pas seulement teintée.');
        $this->assertStringContainsString('aria-hidden="true"', $source, 'Le glyphe doit être décoratif et doublé de texte.');
    }

    /**
     * AC 6 et 45 : aucune page ne trie ni ne classe les personnes selon une performance.
     */
    public function test_no_page_ranks_people(): void
    {
        foreach (self::PAGES as $page) {
            // On juge ce que la personne voit, pas les commentaires du code — lesquels parlent
            // précisément de l'absence de classement.
            $source = mb_strtolower($this->visibleSource($page));

            foreach (['classement', 'rang', 'ranking', 'score', 'palmarès', 'meilleur', 'top 3', 'podium'] as $forbidden) {
                $this->assertStringNotContainsString($forbidden, $source, "{$page} ne doit présenter aucun classement.");
            }
        }
    }

    /**
     * SOC-09 : budget de poids. Le premier chargement reste sous 300 Ko et une navigation
     * suivante sous 80 Ko, mesurés compressés sur les fichiers réellement produits.
     *
     * Le test ne s'exécute que lorsque le build existe — `public/build` n'est pas versionné, mais
     * la CI construit avant d'exécuter la suite.
     */
    public function test_the_built_assets_respect_the_weight_budget(): void
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            $this->markTestSkipped('Build absent : exécuter `npm run build` avant de mesurer les budgets.');
        }

        $firstLoad = 0;

        foreach (glob(public_path('build/assets/app-*')) ?: [] as $shared) {
            $firstLoad += strlen((string) gzencode((string) file_get_contents($shared), 9));
        }

        $pageChunks = [];

        foreach (glob(public_path('build/assets/*.js')) ?: [] as $chunk) {
            if (str_contains(basename($chunk), 'app-')) {
                continue;
            }

            $pageChunks[basename($chunk)] = strlen((string) gzencode((string) file_get_contents($chunk), 9));
        }

        $heaviestChunk = $pageChunks === [] ? 0 : max($pageChunks);

        $this->assertLessThan(
            300 * 1024,
            $firstLoad + $heaviestChunk,
            'Le premier chargement dépasse le budget de 300 Ko.',
        );
        $this->assertLessThan(
            80 * 1024,
            $heaviestChunk,
            'Une navigation suivante dépasse le budget de 80 Ko.',
        );
    }

    /**
     * Source débarrassée des commentaires HTML et JavaScript.
     */
    private function visibleSource(string $page): string
    {
        $source = (string) preg_replace('/<!--.*?-->/s', '', $this->source($page));

        return (string) preg_replace('#^\s*//.*$#m', '', $source);
    }

    /**
     * SOC-06 : les listes exposent un état vide explicite, distinct du vide par filtre.
     */
    public function test_every_list_page_offers_an_explicit_empty_state(): void
    {
        foreach (['WeeklyReviews/Index', 'ImprovementPlans/Index', 'Internships/Index', 'Internships/Intakes/Index', 'Internships/Tutors/Index'] as $page) {
            $source = $this->source($page);

            $this->assertStringContainsString('EmptyState', $source, "{$page} doit exposer un état vide.");
            $this->assertStringContainsString('reason=', $source, "{$page} doit expliquer pourquoi la liste est vide.");
        }
    }

    /**
     * SOC-07 : les boutons occupés conservent leur libellé et leur largeur — le composant
     * `AppButton` s'en charge, jamais un remplacement de texte.
     */
    public function test_busy_buttons_keep_their_label(): void
    {
        foreach (self::PAGES as $page) {
            $source = $this->source($page);

            if (! str_contains($source, 'processing')) {
                continue;
            }

            $this->assertStringContainsString(':disabled="', $source, "{$page} doit désactiver sans changer le libellé.");
            $this->assertStringNotContainsString('Envoi…', $source);
            $this->assertStringNotContainsString('Chargement…', $source);
        }
    }

    /**
     * SOC-08 : l'erreur est affichée sous le champ concerné, avec une annonce d'assistance.
     */
    public function test_errors_are_announced_under_their_field(): void
    {
        foreach (['WeeklyReviews/Show', 'ImprovementPlans/Show', 'Internships/Show', 'Internships/Intakes/Show', 'Internships/Slots/Index'] as $page) {
            $source = $this->source($page);

            $this->assertStringContainsString('role="alert"', $source, "{$page} doit annoncer ses erreurs.");
        }
    }

    private function source(string $page): string
    {
        return (string) file_get_contents(resource_path("js/Pages/Accountability/{$page}.vue"));
    }
}
