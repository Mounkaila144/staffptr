import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { emulateDegradedConnection } from './support/network.js';

test('AC 72, 95 et 97 — encaissement vers parts et réserve au franc près sous 3G à 320 px', async ({ page }) => {
    test.setTimeout(90_000);
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto('/connexion');
    await page.getByLabel('Numéro de téléphone').fill('90 67 89 01');
    await page.getByLabel('Mot de passe').fill('Finance-E2E-2026');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/\/$/, { timeout: 15_000 });

    await page.goto('/finances/encaissements');
    await emulateDegradedConnection(page);
    await page.getByRole('button', { name: 'Enregistrer un encaissement' }).click();
    await page.getByLabel('Client').selectOption({ label: 'Client Finance E2E' });
    await page.getByRole('combobox', { name: 'Contrat', exact: true }).selectOption({ label: 'CTR-E2E-FINANCE — Contrat finance E2E' });
    await page.getByLabel('Facture facultative').selectOption({ label: 'FAC-E2E-0001' });
    await page.getByLabel('Compte crédité').selectOption({ label: 'Caisse principale E2E' });
    await page.getByLabel('Montant encaissé en XOF').fill('1000000');
    await page.getByRole('button', { name: 'Valider et attribuer le reçu' }).click();
    await expect(page.getByText('1 000 000 FCFA').first()).toBeVisible();

    await page.goto('/finances/parts');
    await expect(page.getByText('100 000 FCFA').first()).toBeVisible();
    await expect(page.getByText('600 000 FCFA').first()).toBeVisible();
    await expect(page.getByText('300 000 FCFA').first()).toBeVisible();
    await page.goto('/finances/reserve');
    await expect(page.getByText('200 000 FCFA').first()).toBeVisible();

    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
});
