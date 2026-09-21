<?php

/*
|--------------------------------------------------------------------------
| Registre des parts de contribution des directeurs
|--------------------------------------------------------------------------
|
| Un franc risqué la première année, quand rien n'est acquis, ne vaut pas un franc versé plus tard
| dans une entreprise établie. Le barème ci-dessous convertit cette différence en coefficient.
|
| Le coefficient est figé sur la ligne au moment de l'événement et ne se recalcule jamais à la
| lecture : modifier ce fichier n'a aucun effet rétroactif sur les parts déjà émises.
|
| Les coefficients sont exprimés en points de base — 10 000 valent × 1 — pour rester entiers, à
| l'image des taux de répartition des encaissements.
|
*/

return [
    // Début d'activité de PTR Niger. L'année 1 court donc jusqu'au 31/08/2027 inclus.
    'activity_started_on' => '2026-09-01',

    // Millésime (1 = première année d'activité) → coefficient en points de base.
    'coefficients' => [
        1 => 20_000, // × 2
        2 => 15_000, // × 1,5
        3 => 12_500, // × 1,25
    ],

    // Appliqué à partir de l'année 4 : l'entreprise est établie, un franc y vaut un franc.
    'default_coefficient' => 10_000, // × 1
];
