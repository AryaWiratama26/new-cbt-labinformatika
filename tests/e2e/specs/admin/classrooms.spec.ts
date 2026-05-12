import { test, expect } from '../../fixtures/auth.fixture';

test.describe('Classroom Management', () => {
  test('4.1 Classrooms list renders', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/classrooms');
    await expect(adminPage.page.getByRole('heading', { name: 'Daftar Kelas' })).toBeVisible();
  });

  test('4.2 Create classroom', async ({ adminPage }) => {
    await adminPage.page.goto('/admin/classrooms');
    // Click "Tambah Kelas" button to open modal
    await adminPage.page.getByRole('button', { name: /tambah kelas/i }).click();
    // Fill in the create form (first visible input)
    await adminPage.page.locator('#create-modal input[name="name"]').fill('Test Class');
    await adminPage.page.locator('#create-modal button[type="submit"]').click();
    await expect(adminPage.page.locator('text=berhasil ditambahkan')).toBeVisible({ timeout: 5000 });
  });
});
