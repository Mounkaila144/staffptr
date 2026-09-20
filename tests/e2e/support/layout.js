import { expect } from '@playwright/test';

/**
 * Vérifie qu'aucun contenu ne déborde horizontalement de la fenêtre.
 *
 * L'assertion naïve — comparer `scrollWidth` à `innerWidth` — dit qu'il y a un
 * débordement, jamais lequel. C'est peu de chose en local, où l'on rouvre la page ;
 * c'est beaucoup en intégration continue, où le navigateur a disparu et où les
 * polices ne sont pas celles du poste de développement. Ce helper nomme donc
 * l'élément fautif, sa largeur et son texte, pour que l'échec se lise sans rejouer
 * la session.
 */
export async function expectNoHorizontalOverflow(page, context = 'la page') {
    const measure = await page.evaluate(() => {
        const limit = window.innerWidth;
        const offenders = [];

        for (const element of document.querySelectorAll('*')) {
            const rect = element.getBoundingClientRect();

            if (rect.width === 0 && rect.height === 0) {
                continue;
            }

            if (rect.right > limit + 0.5 || rect.width > limit + 0.5) {
                offenders.push({
                    tag: element.tagName.toLowerCase(),
                    classes: (element.getAttribute('class') ?? '').slice(0, 90),
                    width: Math.round(rect.width),
                    right: Math.round(rect.right),
                    text: (element.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 60),
                });
            }
        }

        // Le plus profond d'abord : les ancêtres ne débordent qu'à cause de lui.
        offenders.reverse();

        return { limit, scrollWidth: document.documentElement.scrollWidth, offenders: offenders.slice(0, 5) };
    });

    const details = measure.offenders
        .map((o) => `    <${o.tag} class="${o.classes}"> largeur ${o.width}px, bord droit ${o.right}px — « ${o.text} »`)
        .join('\n');

    expect(
        measure.scrollWidth,
        `${context} déborde horizontalement : scrollWidth ${measure.scrollWidth}px pour une fenêtre de ${measure.limit}px.\n`
        + `  Éléments en cause, du plus profond au plus englobant :\n${details || '    (aucun élément isolé — vérifier une marge ou un débordement de conteneur)'}`,
    ).toBeLessThanOrEqual(measure.limit);
}
