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

test('Story 2.10 AC 1, 4 et 5 — la direction filtre et exporte le journal à 320 px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill('90 23 45 67');
    await page.getByLabel('Mot de passe').fill('Direction-E2E-2026');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await page.getByRole('button', { name: 'Plus' }).click();
    await page.getByRole('link', { name: "Journal d'audit", exact: true }).click();

    await expect(page).toHaveURL(/\/journal-audit$/);
    await expect(page.getByRole('heading', { name: 'Journal d’audit' })).toBeVisible();
    await expect(page.getByLabel('Auteur')).toBeVisible();
    await expect(page.getByLabel('Type d’objet')).toBeVisible();
    await expect(page.getByLabel('Action')).toBeVisible();
    await expect(page.getByText('Consignée').first()).toBeVisible();

    await page.getByLabel('Du', { exact: true }).fill('2099-01-01');
    await page.getByRole('button', { name: 'Filtrer' }).click();
    await expect(page.getByRole('heading', { name: 'Aucune entrée pour ces filtres.' })).toBeVisible();
    await page.getByRole('region', { name: 'Entrées du journal' })
        .getByRole('button', { name: 'Réinitialiser les filtres' })
        .click();

    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Exporter en CSV' }).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/^journal-audit-\d{8}-\d{6}\.csv$/);

    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});
