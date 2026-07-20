import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const forbiddenVocabulary = /\b(Supprimer|Pointage|Présence|Performance|Score|Classement|Note|Sanction|Défaillant|Soumettre)\b|Erreur 403/i;

function observeRuntime(page) {
    const browserErrors = [];
    const externalRequests = [];

    page.on('console', (message) => {
        if (message.type() === 'error') browserErrors.push(message.text());
    });
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('request', (request) => {
        const requestUrl = new URL(request.url());
        if (!['127.0.0.1', 'localhost'].includes(requestUrl.hostname)) externalRequests.push(request.url());
    });

    return { browserErrors, externalRequests };
}

test('AC 1, 2, 3, 4 et 10 — la démonstration expose tous les états', async ({ page }) => {
    const observed = observeRuntime(page);
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/__test/interface-demo');

    await expect(page.getByRole('heading', { name: 'États transverses', level: 1 })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Navigation principale sur téléphone' })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Navigation principale', exact: true })).toBeHidden();
    await expect(page.locator('[data-status]')).toHaveCount(10);
    for (const label of ['Brouillon', 'En attente', 'Validé', 'À corriger', 'Bloqué', 'En retard']) {
        await expect(page.getByText(label, { exact: true }).first()).toBeVisible();
    }
    await expect(page.getByText('Tous les rapports sont traités', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Réinitialiser les filtres' })).toBeVisible();
    await expect(page.getByText('Chargement en cours. La connexion semble lente.')).toBeVisible();
    await expect(page.getByText('Cent vingt-cinq mille francs CFA')).toBeVisible();
    expect(await page.locator('body').innerText()).not.toMatch(forbiddenVocabulary);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect(observed.browserErrors).toEqual([]);
    expect(observed.externalRequests).toEqual([]);
});

test('AC 4 — le chargement reste silencieux 300 ms puis signale une connexion lente', async ({ page }) => {
    await page.goto('/__test/interface-demo');
    const timedLoading = page.getByTestId('timed-loading');

    await page.getByRole('button', { name: 'Démarrer un chargement chronométré' }).click();
    expect(await timedLoading.getByRole('status').count()).toBe(0);
    await page.waitForTimeout(350);
    await expect(timedLoading.getByRole('status')).toBeVisible();
    await expect(timedLoading.getByText('La connexion semble lente', { exact: false })).toHaveCount(0);
    await page.waitForTimeout(2800);
    await expect(timedLoading.getByText('Chargement en cours. La connexion semble lente.')).toBeVisible();
});

test('AC 5 — la saisie continue hors connexion et le retour est annoncé trois secondes', async ({ page, context }) => {
    await page.goto('/__test/interface-demo');
    await context.setOffline(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));

    await expect(page.getByTestId('offline-banner')).toBeVisible();
    await page.getByLabel('Travail réalisé').fill('Saisie conservée');
    await page.getByTestId('offline-action').click();
    await expect(page.getByTestId('offline-failure')).toContainText("L'envoi n'a pas abouti — pas de connexion");
    await expect(page.getByLabel('Travail réalisé')).toHaveValue('Saisie conservée');

    await context.setOffline(false);
    await page.evaluate(() => window.dispatchEvent(new Event('online')));
    await expect(page.getByTestId('online-banner')).toHaveText('✓ Connexion rétablie');
    await expect(page.getByTestId('online-banner')).toBeHidden({ timeout: 3500 });
});

test('AC 7 — 320 px, clavier, cibles tactiles, niveaux de gris et axe', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/__test/interface-demo');

    await page.evaluate(() => document.activeElement?.blur());
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Aller au contenu' })).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();

    await page.getByRole('button', { name: 'Vérifier les champs' }).click();
    await expect(page.getByLabel('Travail réalisé')).toBeFocused();
    for (const target of await page.locator('.touch-target:visible').all()) {
        const box = await target.boundingBox();
        expect(box?.width).toBeGreaterThanOrEqual(44);
        expect(box?.height).toBeGreaterThanOrEqual(44);
    }
    const mobileTargets = await page.getByRole('navigation', { name: 'Navigation principale sur téléphone' }).locator('a, button').all();
    const mobileBoxes = await Promise.all(mobileTargets.map((target) => target.boundingBox()));
    for (let index = 1; index < mobileBoxes.length; index += 1) {
        expect(mobileBoxes[index].x - (mobileBoxes[index - 1].x + mobileBoxes[index - 1].width)).toBeGreaterThanOrEqual(8);
    }
    await page.getByRole('button', { name: 'Plus', exact: true }).click();
    await expect(page.locator('#mobile-more-navigation')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#mobile-more-navigation')).toBeHidden();
    const accessibility = await new AxeBuilder({ page }).analyze();
    expect(accessibility.violations).toEqual([]);
    await page.evaluate(() => document.documentElement.classList.add('grayscale-proof'));
    for (const status of await page.locator('[data-status]').all()) {
        await expect(status).not.toBeEmpty();
    }
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

    await page.setViewportSize({ width: 640, height: 700 });
    const session = await page.context().newCDPSession(page);
    await session.send('Emulation.setPageScaleFactor', { pageScaleFactor: 2 });
    await expect(page.getByRole('heading', { name: 'États transverses', level: 1 })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

for (const errorPage of [
    { path: '/__test/errors/403', status: 403, heading: 'Accès refusé' },
    { path: '/page-qui-n-existe-pas', status: 404, heading: 'Page introuvable' },
    { path: '/__test/errors/419', status: 419, heading: 'Votre session a expiré' },
    { path: '/__test/errors/500', status: 500, heading: "L'application rencontre un problème" },
]) {
    test(`AC 9 — la page ${errorPage.status} reste accessible sous la politique de contenu`, async ({ page }) => {
        const browserErrors = [];
        page.on('console', (message) => {
            const expectedStatus = message.text().startsWith('Failed to load resource: the server responded with a status of');
            if (message.type() === 'error' && !expectedStatus) browserErrors.push(message.text());
        });
        page.on('pageerror', (error) => browserErrors.push(error.message));
        await page.setViewportSize({ width: 320, height: 700 });
        const response = await page.goto(errorPage.path);

        expect(response?.status()).toBe(errorPage.status);
        await expect(page.getByRole('heading', { name: errorPage.heading })).toBeVisible();
        expect(await page.locator('body').innerText()).not.toContain(String(errorPage.status));
        expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(browserErrors).toEqual([]);
    });
}
