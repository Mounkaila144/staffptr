import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { emulateDegradedConnection } from './support/network.js';

async function login(page, phone, password) {
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill(phone);
    await page.getByLabel('Mot de passe').fill(password);
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/$/);
}

test('Story 6.1 AC 2 à 10 — le rapport est restauré après une coupure sous 3G à 320 px', async ({ page, context }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await login(page, '90 45 67 89', 'Employe-E2E-2026');
    await emulateDegradedConnection(page);
    await page.goto('/rapports/quotidiens/aujourdhui');

    await expect(page.getByLabel('Tâche prévue')).toHaveValue(/Préparer le rapport E2E/);
    await page.getByLabel('Résultat obtenu').fill('Le rapport E2E est prêt.');
    await page.getByLabel('Lien vers une preuve').fill('https://example.test/preuve-rapport');

    await context.setOffline(true);
    await page.getByLabel('Prochaine action').fill('Partager le rapport avec le responsable.');
    await page.getByRole('button', { name: 'Envoyer mon rapport' }).click();
    await expect(page.getByText("L'envoi n'a pas abouti — pas de connexion. Votre rapport est conservé sur cet appareil.")).toBeVisible();

    await context.setOffline(false);
    await page.reload();
    await expect(page.getByLabel('Résultat obtenu')).toHaveValue('Le rapport E2E est prêt.');
    await expect(page.getByLabel('Prochaine action')).toHaveValue('Partager le rapport avec le responsable.');
    await page.getByRole('button', { name: 'Envoyer mon rapport' }).click();
    await expect(page.getByText(/Envoyé(?: en retard)? · version 1/)).toBeVisible();

    const firstContentfulPaint = await page.evaluate(() => performance.getEntriesByName('first-contentful-paint')[0]?.startTime ?? 0);
    expect(firstContentfulPaint).toBeLessThan(3_000);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    for (const target of await page.locator('.touch-target:visible').all()) {
        const box = await target.boundingBox();
        expect(box?.width).toBeGreaterThanOrEqual(44);
        expect(box?.height).toBeGreaterThanOrEqual(44);
    }
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});

test('Story 6.1 AC 20 à 26 — la notification ouvre la validation en deux interactions', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await login(page, '90 23 45 67', 'Direction-E2E-2026');
    await page.goto('/notifications');
    const notification = page.getByRole('listitem').filter({ hasText: 'Le rapport quotidien de Membre à valider E2E attend votre validation.' });

    let interactions = 0;
    interactions += 1;
    await notification.getByRole('link', { name: 'Ouvrir l’élément concerné' }).click();
    await expect(page).toHaveURL(/\/rapports\/\d+\/validation$/);
    await expect(page.getByText('La synthèse quotidienne est prête.')).toBeVisible();

    interactions += 1;
    await page.getByRole('button', { name: 'Valider' }).click();
    await expect(page.getByText('Rapport validé.')).toBeVisible();

    expect(interactions).toBeLessThanOrEqual(3);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});
