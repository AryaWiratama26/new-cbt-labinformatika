import { Page, Locator } from '@playwright/test';

export class StudentDashboardPage {
  readonly page: Page;
  readonly examCards: Locator;

  constructor(page: Page) {
    this.page = page;
    this.examCards = page.locator('[class*="exam"]');
  }

  async goto() {
    await this.page.goto('/student/dashboard');
  }
}
