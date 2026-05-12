import { test, expect } from '../../fixtures/auth.fixture';

test.describe('Student Dashboard', () => {
  test('9.1 Student dashboard shows available exams', async ({ studentPage }) => {
    await expect(studentPage.page.locator('text=Ujian')).toBeVisible();
  });
});
