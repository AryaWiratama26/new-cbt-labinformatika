import { Page, Locator } from '@playwright/test';

export class StudentExamPage {
  readonly page: Page;
  readonly startButton: Locator;
  readonly submitButton: Locator;
  readonly confirmSubmitButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.startButton = page.locator('button:has-text("Mulai")');
    this.submitButton = page.locator('button:has-text("Kumpulkan")');
    this.confirmSubmitButton = page.locator('button:has-text("Ya")');
  }

  async goto(examId: number) {
    await this.page.goto(`/student/exams/${examId}`);
  }

  async startExam() {
    await this.startButton.click();
    await this.page.waitForURL(/\/student\/exams\/\d+\/attempt/);
  }
}
