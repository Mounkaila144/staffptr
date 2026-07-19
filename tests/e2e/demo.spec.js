import { expect, test } from '@playwright/test';

test('la page de démonstration fournit un rendu utile sans erreur navigateur', async ({ page }) => {
    const browserErrors = [];
    const externalRequests = [];

    page.on('console', (message) => {
        if (message.type() === 'error') {
            browserErrors.push(message.text());
        }
    });
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('request', (request) => {
        const requestUrl = new URL(request.url());

        if (!['127.0.0.1', 'localhost'].includes(requestUrl.hostname)) {
            externalRequests.push(request.url());
        }
    });

    await page.goto('/');

    await expect(page.getByRole('heading', { name: 'Fondation applicative opérationnelle' })).toBeVisible();
    await expect(page.getByRole('link', { name: "Vérifier l’état du service" })).toBeVisible();
    expect(browserErrors).toEqual([]);
    expect(externalRequests).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

for (const errorPage of [
    { path: '/__test/errors/403', status: 403, heading: 'Accès refusé' },
    { path: '/page-qui-n-existe-pas', status: 404, heading: 'Page introuvable' },
    { path: '/__test/errors/419', status: 419, heading: 'Votre session a expiré' },
    {
        path: '/__test/errors/500',
        status: 500,
        heading: "L'application rencontre un problème",
    },
]) {
    test(`la page ${errorPage.status} reste utile sous la politique de contenu`, async ({ page }) => {
        const browserErrors = [];

        page.on('console', (message) => {
            const isExpectedHttpStatus = message.text().startsWith('Failed to load resource: the server responded with a status of');

            if (message.type() === 'error' && !isExpectedHttpStatus) {
                browserErrors.push(message.text());
            }
        });
        page.on('pageerror', (error) => browserErrors.push(error.message));
        await page.setViewportSize({ width: 320, height: 700 });

        const response = await page.goto(errorPage.path);

        expect(response?.status()).toBe(errorPage.status);
        await expect(page.getByRole('heading', { name: errorPage.heading })).toBeVisible();
        await expect(page.getByRole('link', { name: /accueil/i })).toBeVisible();
        expect(await page.locator('body').innerText()).not.toContain(String(errorPage.status));
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
        expect(browserErrors).toEqual([]);
    });
}
