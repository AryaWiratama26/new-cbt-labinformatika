import { Page, Locator } from '@playwright/test';

export class AdminModulesPage {
  readonly page: Page;
  readonly questionCards: Locator;

  constructor(page: Page) {
    this.page = page;
    this.questionCards = page.locator('[class*="question"]');
  }

  async goto(courseId: number) {
    await this.page.goto(`/admin/courses/${courseId}/modules`);
  }
}
