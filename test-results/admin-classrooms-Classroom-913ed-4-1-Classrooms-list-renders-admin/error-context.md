# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: admin/classrooms.spec.ts >> Classroom Management >> 4.1 Classrooms list renders
- Location: tests/e2e/specs/admin/classrooms.spec.ts:4:3

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('heading', { name: 'Daftar Kelas' })
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 5000ms
  - waiting for getByRole('heading', { name: 'Daftar Kelas' })

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
  1  | import { test, expect } from '../../fixtures/auth.fixture';
  2  | 
  3  | test.describe('Classroom Management', () => {
  4  |   test('4.1 Classrooms list renders', async ({ adminPage }) => {
  5  |     await adminPage.page.goto('/admin/classrooms');
> 6  |     await expect(adminPage.page.getByRole('heading', { name: 'Daftar Kelas' })).toBeVisible();
     |                                                                                 ^ Error: expect(locator).toBeVisible() failed
  7  |   });
  8  | 
  9  |   test('4.2 Create classroom', async ({ adminPage }) => {
  10 |     await adminPage.page.goto('/admin/classrooms');
  11 |     // Click "Tambah Kelas" button to open modal
  12 |     await adminPage.page.getByRole('button', { name: /tambah kelas/i }).click();
  13 |     // Fill in the create form (first visible input)
  14 |     await adminPage.page.locator('#create-modal input[name="name"]').fill('Test Class');
  15 |     await adminPage.page.locator('#create-modal button[type="submit"]').click();
  16 |     await expect(adminPage.page.locator('text=berhasil ditambahkan')).toBeVisible({ timeout: 5000 });
  17 |   });
  18 | });
  19 | 
```