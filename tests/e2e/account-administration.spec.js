import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

test('AC 2, 6 et 7 — création et cartes de comptes restent utilisables à 320 px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill('90 23 45 67');
    await page.getByLabel('Mot de passe').fill('Direction-E2E-2026');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await page.getByRole('button', { name: 'Plus' }).click();
    await page.getByRole('link', { name: 'Comptes et rôles', exact: true }).click();
    await expect(page).toHaveURL(/\/comptes$/);
    await expect(page.getByRole('heading', { name: 'Administration des comptes' })).toBeVisible();
    await expect(page.getByText('Les dépenses ne sont pas encore approuvables', { exact: false })).toBeVisible();

    const creation = page.getByRole('heading', { name: 'Créer un compte' }).locator('xpath=ancestor::section');
    await creation.getByLabel('Nom complet').fill('Seconde Direction E2E');
    await creation.getByLabel('Numéro de téléphone').fill('90 34 56 78');
    await creation.getByLabel('Direction').check();
    await creation.getByRole('button', { name: 'Créer et afficher le mot de passe' }).click();

    await expect(page.getByRole('heading', { name: 'Mot de passe temporaire — affiché une seule fois' })).toBeVisible();
    const credential = page.locator('dd.font-mono');
    await expect(credential).toHaveText(/^[a-f0-9]{32}$/);
    await expect(page.locator('article')).not.toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    for (const target of await page.locator('.touch-target:visible').all()) {
        const box = await target.boundingBox();
        expect(box?.width).toBeGreaterThanOrEqual(44);
        expect(box?.height).toBeGreaterThanOrEqual(44);
    }

    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});
