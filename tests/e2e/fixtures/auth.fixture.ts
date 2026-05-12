import { test as base } from '@playwright/test';
import { LoginPage } from '../pages/login.page';

type AuthFixtures = {
  adminPage: LoginPage;
  studentPage: LoginPage;
};

export const test = base.extend<AuthFixtures>({
  adminPage: async ({ page }, use) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login('admin', 'admin');
    await page.waitForURL(/\/admin\/dashboard/);
    await use(login);
  },

  studentPage: async ({ page }, use) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login('20241001', 'test123');
    await page.waitForURL(/\/student\/dashboard/);
    await use(login);
  },
});

export { expect } from '@playwright/test';
