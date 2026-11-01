import { expect } from '@playwright/test';

/**
 * Ouvre une entrée du menu. Sur téléphone, la plupart des entrées sont rangées sous « Plus » :
 * on ouvre ce panneau quand le lien n'est pas directement visible.
 */
export async function openNavigationLink(page, name) {
    await page.waitForFunction(() => document.querySelector('#app')?.hasAttribute('data-v-app'));
    const direct = page.getByRole('link', { name, exact: true }).filter({ visible: true });
    if (await direct.count() === 0) {
        await page.getByRole('button', { name: 'Plus', exact: true }).click();
    }
    const link = page.getByRole('link', { name, exact: true }).filter({ visible: true }).first();
    await expect(link).toBeVisible();
    await link.click();
}
