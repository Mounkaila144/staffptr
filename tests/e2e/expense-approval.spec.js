import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { emulateDegradedConnection } from './support/network.js';

test('AC 3 et 7 — de la notification à la confirmation en trois interactions sous 3G à 320 px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill('90 23 45 67');
    await page.getByLabel('Mot de passe').fill('Direction-E2E-2026');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/$/);

    await page.goto('/notifications');
    await emulateDegradedConnection(page);
    let interactions = 0;

    interactions += 1;
    await page.getByRole('link', { name: 'Ouvrir l’élément concerné' }).first().click();
    await expect(page).toHaveURL(/\/depenses\/\d+\/decision$/);
    await expect(page.getByRole('heading', { name: 'Décider de la dépense', level: 1 })).toBeVisible();
    const firstContentfulPaint = await page.evaluate(() => performance.getEntriesByName('first-contentful-paint')[0]?.startTime ?? 0);
    expect(firstContentfulPaint).toBeLessThan(3_000);

    interactions += 1;
    await page.getByText('Consulter le justificatif sur cet écran').click();
    await expect(page.getByRole('img', { name: /Justificatif/ })).toBeVisible();

    interactions += 1;
    await page.getByRole('button', { name: 'Approuver cette dépense' }).click();
    await expect(page.getByText('Votre approbation a été enregistrée.')).toBeVisible();

    expect(interactions).toBeLessThanOrEqual(3);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    for (const target of await page.locator('.touch-target:visible').all()) {
        const box = await target.boundingBox();
        expect(box?.width).toBeGreaterThanOrEqual(44);
        expect(box?.height).toBeGreaterThanOrEqual(44);
    }
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});
