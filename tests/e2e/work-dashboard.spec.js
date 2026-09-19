import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { emulateDegradedConnection } from './support/network.js';

test('Story 5.1 AC 43 à 49 — le travail personnel reste priorisé et lisible sous 3G à 320 px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await emulateDegradedConnection(page);
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill('90 23 45 67');
    await page.getByLabel('Mot de passe').fill('Direction-E2E-2026');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await expect(page.getByRole('heading', { name: 'En attente de mon approbation' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Mes objectifs du mois' })).toBeVisible();
    // Un même intitulé se répète d'un bloc à l'autre — « Prochaines échéances » reprend les
    // objectifs — donc la vérification se fait dans le bloc qu'elle vise, pas sur la page entière.
    await expect(page.getByLabel('Mes objectifs du mois').getByText('Finaliser le socle du travail')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Mes tâches du jour' })).toBeVisible();
    await expect(page.getByLabel('Mes tâches du jour').getByText('Relire les priorités du jour')).toBeVisible();

    const firstContentfulPaint = await page.evaluate(() => performance.getEntriesByName('first-contentful-paint')[0]?.startTime);
    expect(firstContentfulPaint).toBeLessThan(3_000);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

test('Story 5.1 AC 7, 20 et 26 — la preuve attendue et les trois vues restent accessibles', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill('90 23 45 67');
    await page.getByLabel('Mot de passe').fill('Direction-E2E-2026');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page.getByRole('heading', { name: 'En attente de mon approbation' })).toBeVisible();
    await page.goto('/objectifs');

    await expect(page.getByLabel(/Preuve attendue/)).toBeVisible();
    await expect(page.getByRole('link', { name: 'Calendrier' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Synthèse mensuelle' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});
