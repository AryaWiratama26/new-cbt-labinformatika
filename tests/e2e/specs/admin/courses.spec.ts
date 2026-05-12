import { test, expect } from '../../fixtures/auth.fixture';
import { AdminCoursesPage } from '../../pages/admin-courses.page';

test.describe('Course Management', () => {
  test('5.1 Courses list renders', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/courses');
    await expect(adminPage.page.getByRole('heading', { name: 'Mata Kuliah' })).toBeVisible();
  });

  test('5.2 Create course', async ({ adminPage }) => {
    const page = new AdminCoursesPage(adminPage.page);
    await page.goto();
    const unique = `TC${Date.now()}`.slice(-6);
    await page.codeInput.fill(unique);
    await page.nameInput.fill(`Test Course ${unique}`);
    await page.saveButton.click();
    await expect(adminPage.page.locator('text=berhasil ditambahkan')).toBeVisible({ timeout: 5000 });
  });

  test('5.4 Delete course', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/courses');
    const deleteBtns = adminPage.page.locator('button[title="Hapus"]');
    const count = await deleteBtns.count();
    if (count > 0) {
      adminPage.page.once('dialog', dialog => dialog.accept());
      await deleteBtns.first().click();
      await expect(adminPage.page.locator('text=berhasil dihapus')).toBeVisible({ timeout: 5000 });
    }
  });
});
