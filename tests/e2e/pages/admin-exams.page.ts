import { Page, Locator } from '@playwright/test';

export class AdminExamsPage {
  readonly page: Page;
  readonly examRows: Locator;

  constructor(page: Page) {
    this.page = page;
    this.examRows = page.locator('table tbody tr');
  }

  async goto() {
    await this.page.goto('/admin/exams');
  }
}
