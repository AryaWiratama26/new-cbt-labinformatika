import { test, expect } from '../../fixtures/auth.fixture';

test.describe('Security Features', () => {
  test('10.11 Student cannot access admin routes', async ({ studentPage }) => {
    const resp = await studentPage.page.goto('/admin/dashboard');
    expect(resp?.status()).toBe(403);
  });

  test('Admin cannot access student routes', async ({ adminPage }) => {
    const resp = await adminPage.page.goto('/student/dashboard');
    expect(resp?.status()).toBe(403);
  });
});
