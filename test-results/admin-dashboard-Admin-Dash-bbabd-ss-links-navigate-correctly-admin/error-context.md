# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: admin/dashboard.spec.ts >> Admin Dashboard >> 2.5 Quick access links navigate correctly
- Location: tests/e2e/specs/admin/dashboard.spec.ts:11:3

# Error details

```
Test timeout of 30000ms exceeded while setting up "adminPage".
```

```
Error: page.waitForURL: Test timeout of 30000ms exceeded.
=========================== logs ===========================
waiting for navigation until "load"
============================================================
```

# Page snapshot

```yaml
- main [ref=e2]:
  - generic [ref=e4]:
    - heading "429" [level=1] [ref=e5]
    - generic [ref=e6]: Too Many Requests
```

# Test source

```ts
  1  | import { test as base } from '@playwright/test';
  2  | import { LoginPage } from '../pages/login.page';
  3  | 
  4  | type AuthFixtures = {
  5  |   adminPage: LoginPage;
  6  |   studentPage: LoginPage;
  7  | };
  8  | 
  9  | export const test = base.extend<AuthFixtures>({
  10 |   adminPage: async ({ page }, use) => {
  11 |     const login = new LoginPage(page);
  12 |     await login.goto();
  13 |     await login.login('admin', 'admin');
> 14 |     await page.waitForURL(/\/admin\/dashboard/);
     |                ^ Error: page.waitForURL: Test timeout of 30000ms exceeded.
  15 |     await use(login);
  16 |   },
  17 | 
  18 |   studentPage: async ({ page }, use) => {
  19 |     const login = new LoginPage(page);
  20 |     await login.goto();
  21 |     await login.login('20241001', 'test123');
  22 |     await page.waitForURL(/\/student\/dashboard/);
  23 |     await use(login);
  24 |   },
  25 | });
  26 | 
  27 | export { expect } from '@playwright/test';
  28 | 
```