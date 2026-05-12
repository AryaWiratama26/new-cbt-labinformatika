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
    // Click last delete button (oldest course is safer to delete)
    const deleteBtns = adminPage.page.locator('table tbody tr button:has-text("")');
    const count = await deleteBtns.count();
    if (count > 0) {
      await deleteBtns.last().click();
      await expect(adminPage.page.locator('text=berhasil dihapus')).toBeVisible({ timeout: 5000 });
    }
  });
});
