import { chromium } from '@playwright/test';
import { seedTestData } from './fixtures/seed';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

export default async function globalSetup() {
  seedTestData();

  const authDir = path.resolve(__dirname, '.auth');
  fs.mkdirSync(authDir, { recursive: true });

  const browser = await chromium.launch();

  // Admin login and save storage state
  const BASE = 'http://localhost:8000';
  const adminContext = await browser.newContext();
  const adminPage = await adminContext.newPage();
  await adminPage.goto(BASE + '/login');
  await adminPage.getByRole('textbox', { name: /nim/i }).fill('admin');
  await adminPage.getByRole('textbox', { name: /password/i }).fill('admin');
  await adminPage.locator('button[type="submit"]').click();
  await adminPage.waitForURL(/\/admin\/dashboard/);
  await adminContext.storageState({ path: path.join(authDir, 'admin.json') });
  await adminContext.close();

  // Student login and save storage state
  const studentContext = await browser.newContext();
  const studentPage = await studentContext.newPage();
  await studentPage.goto(BASE + '/login');
  await studentPage.getByRole('textbox', { name: /nim/i }).fill('20241001');
  await studentPage.getByRole('textbox', { name: /password/i }).fill('test123');
  await studentPage.locator('button[type="submit"]').click();
  await studentPage.waitForURL(/\/student\/dashboard/);
  await studentContext.storageState({ path: path.join(authDir, 'student.json') });
  await studentContext.close();

  await browser.close();
}
