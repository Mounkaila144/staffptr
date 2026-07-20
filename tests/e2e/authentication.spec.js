import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { emulateDegradedConnection } from './support/network.js';

test.describe.configure({ mode: 'serial' });

test('AC 4 et 7 — connexion puis changement de mot de passe imposé à 320 px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await emulateDegradedConnection(page);

    await page.goto('/connexion');
    await expect(page.getByRole('heading', { name: 'Connexion' })).toBeVisible();
    const firstContentfulPaint = await page.evaluate(() => performance.getEntriesByName('first-contentful-paint')[0]?.startTime);
    expect(firstContentfulPaint).toBeLessThan(3_000);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    await page.waitForFunction(() => document.querySelector('#app')?.hasAttribute('data-v-app'));

    await page.getByLabel('Numéro de téléphone').fill('90 12 34 56');
    await page.getByLabel('Mot de passe').fill('Temporaire-E2E-2026');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await expect(page).toHaveURL(/\/mot-de-passe\/modifier$/);
    await expect(page.getByRole('heading', { name: 'Choisissez un nouveau mot de passe' })).toBeVisible();
    await page.getByRole('textbox', { name: 'Nouveau mot de passe obligatoire', exact: true }).fill('Nouveau-Mot-De-Passe-2026');
    await page.getByRole('textbox', { name: 'Confirmer le mot de passe obligatoire', exact: true }).fill('Nouveau-Mot-De-Passe-2026');
    await page.getByRole('button', { name: 'Enregistrer le mot de passe' }).click();

    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('heading', { name: 'Bienvenue dans PTR Staff' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('AC 5 — une erreur de connexion reste sous le numéro et reçoit le focus', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill('90 99 99 99');
    await page.getByLabel('Mot de passe').fill('Mot-de-passe-erroné');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await expect(page.getByText('Numéro ou mot de passe incorrect.')).toBeVisible();
    await expect(page.getByLabel('Numéro de téléphone')).toBeFocused();
});
