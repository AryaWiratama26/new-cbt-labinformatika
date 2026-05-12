import { test, expect } from '../../fixtures/auth.fixture';
import { AdminExamsPage } from '../../pages/admin-exams.page';

test.describe('Exam Management', () => {
  test('7.1 Exams list renders', async ({ adminPage }) => {
    const page = new AdminExamsPage(adminPage.page);
    await page.goto();
    await expect(adminPage.page.getByRole('heading', { name: /ujian/i })).toBeVisible();
  });

  test('8.4 PDF export button exists on results', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/exams');
    const examLinks = adminPage.page.locator('a[href*="/admin/exams/"]');
    const count = await examLinks.count();
    // Only test if there's at least one exam
    test.skip(count < 3, 'No exams to test');
    const href = await examLinks.nth(2).getAttribute('href');
    await adminPage.page.goto(href!);
    await adminPage.page.waitForURL(/\/admin\/exams\/\d+$/);
    const resultLink = adminPage.page.locator('a[href*="/results"]');
    if (await resultLink.isVisible()) {
      await resultLink.click();
      await adminPage.page.waitForURL(/\/results/);
      await expect(adminPage.page.getByRole('link', { name: /pdf/i })).toBeVisible();
    }
  });

  test('7.7 Exam edit form loads', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/exams');
    const editLinks = adminPage.page.locator('a[href*="/edit"]');
    const count = await editLinks.count();
    test.skip(count === 0, 'No exams to edit');
    await editLinks.first().click();
    await adminPage.page.waitForURL(/\/edit/);
    await expect(adminPage.page.getByRole('heading', { name: /edit/i })).toBeVisible();
  });
});
