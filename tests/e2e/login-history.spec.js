import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('AC 4 et 5 — la direction consulte connexions et sessions à 320 px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill('90 23 45 67');
    await page.getByLabel('Mot de passe').fill('Direction-E2E-2026');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await expect(page).toHaveURL(/\/$/);
    await page.getByRole('button', { name: 'Plus' }).click();
    await page.getByRole('link', { name: 'Connexions', exact: true }).click();

    await expect(page).toHaveURL(/\/connexions$/);
    await expect(page.getByRole('heading', { name: 'Historique de connexion' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Tentatives' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Sessions ouvertes' })).toBeVisible();
    await expect(page.getByLabel('Personne')).toBeVisible();
    await expect(page.getByText('Réussie').first()).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});
