import { Page, Locator } from '@playwright/test';

export class AdminStudentsPage {
  readonly page: Page;
  readonly searchInput: Locator;
  readonly tableRows: Locator;

  constructor(page: Page) {
    this.page = page;
    this.searchInput = page.locator('input[placeholder*="Cari"]');
    this.tableRows = page.locator('table tbody tr');
  }

  async goto() {
    await this.page.goto('/admin/students');
  }
}
