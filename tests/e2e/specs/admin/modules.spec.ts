import { test, expect } from '../../fixtures/auth.fixture';

test.describe('Modules & Questions', () => {
  test('6.1 Course has modules link', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/courses');
    const moduleLink = adminPage.page.locator('a:has-text("Modul")').first();
    await expect(moduleLink).toBeVisible();
  });

  test('6.4 Question bank renders for module', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/courses');
    const moduleLink = adminPage.page.locator('tr:has(td:text("PW")) a:has-text("Modul")');
    await moduleLink.click();
    await adminPage.page.waitForURL(/\/modules/);
    const kelolaLink = adminPage.page.locator('a:has-text("Kelola Soal")').first();
    await expect(kelolaLink).toBeVisible();
    await kelolaLink.click();
    await adminPage.page.waitForURL(/\/questions/);
    await expect(adminPage.page.getByRole('heading', { name: 'Bank Soal' })).toBeVisible();
  });
});
