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
});
