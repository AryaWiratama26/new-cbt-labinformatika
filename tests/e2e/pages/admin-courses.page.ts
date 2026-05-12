import { Page, Locator } from '@playwright/test';

export class AdminCoursesPage {
  readonly page: Page;
  readonly courseRows: Locator;
  readonly codeInput: Locator;
  readonly nameInput: Locator;
  readonly saveButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.courseRows = page.locator('table tbody tr');
    this.codeInput = page.locator('input[placeholder*="IF101"]');
    this.nameInput = page.locator('input[placeholder*="Basis Data"]');
    this.saveButton = page.locator('button:has-text("Simpan")');
  }

  async goto() {
    await this.page.goto('/admin/courses');
  }
}
