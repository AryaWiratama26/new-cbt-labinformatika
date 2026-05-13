import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './specs/student',
  testMatch: 'offline-resilient.spec.ts',
  timeout: 120000,
  retries: 0,
  fullyParallel: false,
  workers: 1,
  globalSetup: './global-setup.ts',

  use: {
    baseURL: 'http://localhost:8000',
    headless: true,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    storageState: './.auth/student.json',
  },
});
