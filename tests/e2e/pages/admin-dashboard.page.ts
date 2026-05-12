import { Page, Locator } from '@playwright/test';

export class AdminDashboardPage {
  readonly page: Page;
  readonly statCards: Locator;
  readonly navLinks: Locator;

  constructor(page: Page) {
    this.page = page;
    this.statCards = page.locator('main a[href*="/admin/"]');
    this.navLinks = page.locator('nav a');
  }

  async goto() {
    await this.page.goto('/admin/dashboard');
  }
}
