import { Page, Locator } from '@playwright/test';

export class AdminClassroomsPage {
  readonly page: Page;
  readonly classroomRows: Locator;

  constructor(page: Page) {
    this.page = page;
    this.classroomRows = page.locator('table tbody tr');
  }

  async goto() {
    await this.page.goto('/admin/classrooms');
  }
}
