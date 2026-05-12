import { test as base } from '@playwright/test';
import { LoginPage } from '../pages/login.page';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const authDir = path.resolve(__dirname, '../.auth');

type AuthFixtures = {
  adminPage: LoginPage;
  studentPage: LoginPage;
};

export const test = base.extend<AuthFixtures>({
  adminPage: async ({ browser }, use) => {
    const context = await browser.newContext({ storageState: path.join(authDir, 'admin.json') });
    const page = await context.newPage();
    const login = new LoginPage(page);
    await use(login);
    await context.close();
  },

  studentPage: async ({ browser }, use) => {
    const context = await browser.newContext({ storageState: path.join(authDir, 'student.json') });
    const page = await context.newPage();
    const login = new LoginPage(page);
    await use(login);
    await context.close();
  },
});

export { expect } from '@playwright/test';
