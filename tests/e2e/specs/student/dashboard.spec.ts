import { test, expect } from '../../fixtures/auth.fixture';

test.describe('Student Dashboard', () => {
  test('9.1 Student dashboard shows available exams', async ({ studentPage }) => {
    await studentPage.page.goto('/student/dashboard');
    await expect(studentPage.page.locator('h3:has-text("Jadwal Ujian")')).toBeVisible();
  });
});
