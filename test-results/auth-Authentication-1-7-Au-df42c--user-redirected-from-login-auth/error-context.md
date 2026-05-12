# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: auth.spec.ts >> Authentication >> 1.7 Authenticated user redirected from /login
- Location: tests/e2e/specs/auth.spec.ts:42:3

# Error details

```
Error: expect(page).toHaveURL(expected) failed

Expected pattern: /\/admin\/dashboard/
Received string:  "http://localhost:8000/login"
Timeout: 5000ms

Call log:
  - Expect "toHaveURL" with timeout 5000ms
    14 × unexpected value "http://localhost:8000/login"

```

```yaml
- main:
  - img "Logo"
  - img "Logo"
  - paragraph: Universitas Pelita Bangsa
  - heading "Computer Based Test." [level=1]
  - paragraph: Selamat datang di platform ujian praktikum online. Silakan masuk menggunakan NIM atau kredensial yang telah diberikan.
  - heading "Masuk ke Akun Anda" [level=2]
  - text: Username / NIM 
  - textbox "Username / NIM":
    - /placeholder: Masukkan NIM Anda
  - text: Password 
  - textbox "Password":
    - /placeholder: ••••••••
  - button "Masuk Sekarang "
- contentinfo: © 2026 Laboratorium Informatika Universitas Pelita Bangsa.
```

# Test source

```ts
  1  | import { test, expect } from '../fixtures/auth.fixture';
  2  | import { LoginPage } from '../pages/login.page';
  3  | 
  4  | test.describe('Authentication', () => {
  5  |   test('1.1 Login page renders correctly', async ({ page }) => {
  6  |     const login = new LoginPage(page);
  7  |     await login.goto();
  8  |     await expect(login.usernameInput).toBeVisible();
  9  |     await expect(login.passwordInput).toBeVisible();
  10 |     await expect(login.submitButton).toBeVisible();
  11 |     await expect(page.locator('text=Masuk ke Akun Anda')).toBeVisible();
  12 |   });
  13 | 
  14 |   test('1.2 Admin login success and redirect', async ({ page }) => {
  15 |     const login = new LoginPage(page);
  16 |     await login.goto();
  17 |     await login.login('admin', 'admin');
  18 |     await expect(page).toHaveURL(/\/admin\/dashboard/);
  19 |   });
  20 | 
  21 |   test('1.3 Student login success and redirect', async ({ page }) => {
  22 |     const login = new LoginPage(page);
  23 |     await login.goto();
  24 |     await login.login('20241001', 'test123');
  25 |     await expect(page).toHaveURL(/\/student\/dashboard/);
  26 |   });
  27 | 
  28 |   test('1.4 Login failure shows error', async ({ page }) => {
  29 |     const login = new LoginPage(page);
  30 |     await login.goto();
  31 |     await login.login('admin', 'wrongpassword');
  32 |     await expect(page.locator('text=credentials do not match')).toBeVisible();
  33 |     await expect(page).toHaveURL(/\/login/);
  34 |   });
  35 | 
  36 |   test('1.6 Logout redirects to login', async ({ adminPage }) => {
  37 |     await adminPage.page.goto('/admin/dashboard');
  38 |     await adminPage.page.locator('form[action*="logout"] button').click();
  39 |     await expect(adminPage.page).toHaveURL(/\/login/);
  40 |   });
  41 | 
  42 |   test('1.7 Authenticated user redirected from /login', async ({ adminPage }) => {
  43 |     await adminPage.page.goto('/admin/dashboard');
  44 |     await adminPage.page.goto('/login');
> 45 |     await expect(adminPage.page).toHaveURL(/\/admin\/dashboard/);
     |                                  ^ Error: expect(page).toHaveURL(expected) failed
  46 |   });
  47 | });
  48 | 
```