import { test, expect } from '../../fixtures/auth.fixture';

test.describe('Student Exam Flow', () => {
  test('9.2 Exam show page renders', async ({ studentPage }) => {
    await studentPage.page.goto('/student/dashboard');
    const examLink = studentPage.page.locator('a[href*="/student/exams/"]').first();
    if (await examLink.isVisible()) {
      await examLink.click();
      await expect(studentPage.page.locator('text=Mulai')).toBeVisible();
    }
  });
});
