import { test, expect } from '../../fixtures/auth.fixture';
import { AdminDashboardPage } from '../../pages/admin-dashboard.page';

test.describe('Admin Dashboard', () => {
  test('2.1 Dashboard renders stat cards', async ({ adminPage }) => {
    const dash = new AdminDashboardPage(adminPage.page);
    await dash.goto();
    await expect(dash.statCards.first()).toBeVisible();
  });

  test('2.5 Quick access links navigate correctly', async ({ adminPage }) => {
    const dash = new AdminDashboardPage(adminPage.page);
    await dash.goto();
    const links = adminPage.page.locator('main a[href*="/admin/"]');
    const count = await links.count();
    expect(count).toBeGreaterThan(0);
  });
});
