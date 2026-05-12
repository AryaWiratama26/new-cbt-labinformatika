import { test, expect } from '@playwright/test';
import { LoginPage } from '../pages/login.page';

test.describe('Authentication', () => {
  test('1.1 Login page renders correctly', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await expect(login.usernameInput).toBeVisible();
    await expect(login.passwordInput).toBeVisible();
    await expect(login.submitButton).toBeVisible();
    await expect(page.locator('text=Masuk ke Akun Anda')).toBeVisible();
  });

  test('1.2 Admin login success and redirect', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login('admin', 'admin');
    await expect(page).toHaveURL(/\/admin\/dashboard/);
  });

  test('1.3 Student login success and redirect', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login('20241001', 'test123');
    await expect(page).toHaveURL(/\/student\/dashboard/);
  });

  test('1.4 Login failure shows error', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login('admin', 'wrongpassword');
    await expect(page.locator('text=credentials do not match')).toBeVisible();
    await expect(page).toHaveURL(/\/login/);
  });

  test('1.6 Logout redirects to login', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login('admin', 'admin');
    await page.waitForURL(/\/admin\/dashboard/);
    await page.locator('form[action*="logout"] button').click();
    await expect(page).toHaveURL(/\/login/);
  });

  test('1.7 Authenticated user redirected from /login', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login('admin', 'admin');
    await page.waitForURL(/\/admin\/dashboard/);
    await page.goto('/login');
    await expect(page).toHaveURL(/\/admin\/dashboard/);
  });
});
