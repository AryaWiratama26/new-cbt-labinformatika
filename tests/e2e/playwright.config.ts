import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './specs',
  timeout: 30000,
  retries: 1,
  fullyParallel: false,
  workers: 1,
  globalSetup: './global-setup.ts',

  use: {
    baseURL: 'http://localhost:8000',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'auth',
      testMatch: 'auth.spec.ts',
    },
    {
      name: 'admin',
      testMatch: '**/admin/**/*.spec.ts',
    },
    {
      name: 'student',
      testMatch: '**/student/**/*.spec.ts',
    },
  ],
});
