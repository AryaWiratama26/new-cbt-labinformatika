import { test, expect } from '../../fixtures/auth.fixture';
import { AdminStudentsPage } from '../../pages/admin-students.page';

test.describe('Student Management', () => {
  test('3.1 Students list renders', async ({ adminPage }) => {
    const page = new AdminStudentsPage(adminPage.page);
    await page.goto();
    await expect(adminPage.page.getByRole('heading', { name: 'Daftar Mahasiswa' })).toBeVisible();
  });

  test('3.4 Create student', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/students/create');
    await adminPage.page.locator('input[name="nim"]').fill('99999999');
    await adminPage.page.locator('input[name="name"]').fill('New Student');
    await adminPage.page.locator('select[name="classroom_id"]').selectOption({ index: 1 });
    await adminPage.page.locator('button[type="submit"]').click();
    await expect(adminPage.page.locator('text=berhasil ditambahkan')).toBeVisible();
  });

  test('3.7 Delete student from index', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/students');
    const deleteBtn = adminPage.page.locator('button[data-action="delete"]').first();
    if (await deleteBtn.isVisible()) {
      await deleteBtn.click();
      await adminPage.page.locator('button:has-text("Ya")').click();
      await expect(adminPage.page.locator('text=berhasil dihapus')).toBeVisible();
    }
  });
});
