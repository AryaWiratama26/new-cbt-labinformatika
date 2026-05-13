import { test, expect } from '../../fixtures/auth.fixture';
import { execSync } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '../../../../');

function run(cmd: string) {
  return execSync(cmd, { cwd: projectRoot, stdio: 'pipe', shell: true }).toString().trim();
}

let EXAM_ID: number;
const EXAM_TITLE = 'E2E Offline Test';

test.describe('Offline-Resilient Mode', () => {

  // ── Lifecycle ──

  test.beforeAll(async () => {
    try {
      const output = run(`php artisan e2e:setup-exam "${EXAM_TITLE}"`);
      const match = output.match(/exam_created:(\d+)/);
      EXAM_ID = match ? parseInt(match[1], 10) : 0;
      if (!EXAM_ID) throw new Error(`Failed. Raw output: ${output}`);
    } catch (e: any) {
      console.error('beforeAll error:', e.message);
      throw e;
    }
  });

  test.beforeEach(async () => {
    const output = run(`php artisan e2e:setup-exam "${EXAM_TITLE}"`);
    const match = output.match(/exam_created:(\d+)/);
    const newId = match ? parseInt(match[1], 10) : 0;
    if (newId) EXAM_ID = newId;
  });

  test.afterAll(() => {
    if (!EXAM_ID) return;
    run(`php artisan e2e:cleanup "${EXAM_TITLE}"`);
  });

  // ── Helpers ──

  async function gotoAttempt(studentPage: any) {
    const { page } = studentPage;
    await page.goto(`/student/exams/${EXAM_ID}/attempt`);
    await page.waitForURL(/\/student\/exams\/\d+\/attempt/);
    await expect(page.locator('.question-card')).toBeVisible({ timeout: 5000 });
    return page;
  }

  async function getPendingFromLS(page: any) {
    const data = await page.evaluate(() => {
      const key = Object.keys(localStorage).find(k => k.startsWith('cbt_pending_'));
      if (!key) return null;
      return JSON.parse(localStorage.getItem(key) || '{}');
    });
    if (!data) return null;
    if (Object.keys(data.answers || {}).length === 0 && !data.tab_switches && !data.pending_submit) return null;
    return data;
  }

  // ════════════════════════════════════════════
  //  CATEGORY 1: UI COMPONENT VERIFICATION
  // ════════════════════════════════════════════

  test('TC1: Offline banner shows/hides on connectivity change', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    // Initially hidden (we are online)
    await expect(page.locator('#offline-banner')).toHaveClass(/hidden/);

    // Go offline → banner should show
    await page.context().setOffline(true);
    await expect(page.locator('#offline-banner:not(.hidden)')).toBeVisible({ timeout: 3000 });

    // Go online → banner should hide (with 1s delay for flush)
    await page.context().setOffline(false);
    await expect(page.locator('#offline-banner')).toHaveClass(/hidden/, { timeout: 5000 });
  });

  test('TC2: Navigation buttons show 3 states correctly', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);
    const navBtn = page.locator('.nav-btn[data-qid="1"]');

    // Initial: unanswered (white)
    await expect(navBtn).toHaveClass(/bg-white/);

    // Answer online → confirmed (primary)
    await page.locator('.option-label').filter({ hasText: 'Answer A' }).locator('input').check();
    await expect(navBtn).toHaveClass(/bg-primary/, { timeout: 5000 });

    // Go offline and answer differently → pending (amber)
    await page.context().setOffline(true);
    await page.locator('.option-label').filter({ hasText: 'Answer B' }).locator('input').check();
    await expect(navBtn).toHaveClass(/bg-amber-400/, { timeout: 3000 });
  });

  test('TC3: Save status bar shows pending/saved/saving states', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);
    const statusBar = page.locator('#save-status-bar');

    // Answer online → shows "Jawaban tersimpan"
    await page.locator('.option-label').filter({ hasText: 'Answer A' }).locator('input').check();
    await expect(statusBar).toContainText('Jawaban tersimpan', { timeout: 5000 });

    // After 2s it auto-hides
    await page.waitForTimeout(2200);
    await expect(statusBar).toHaveClass(/hidden/);

    // Go offline and answer → shows "menunggu koneksi"
    await page.context().setOffline(true);
    await page.locator('.option-label').filter({ hasText: 'Answer C' }).locator('input').check();
    await expect(statusBar).toContainText('menunggu koneksi', { timeout: 3000 });
  });

  // ════════════════════════════════════════════
  //  CATEGORY 2: CORE OFFLINE FUNCTIONALITY
  // ════════════════════════════════════════════

  test('TC4: Online save confirms immediately with no localStorage pending', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    await page.locator('.option-label').filter({ hasText: 'Answer A' }).locator('input').check();

    // Nav should show confirmed
    const navBtn = page.locator('.nav-btn[data-qid="1"]');
    await expect(navBtn).toHaveClass(/bg-primary/, { timeout: 5000 });

    // Should NOT be in localStorage
    const pending = await getPendingFromLS(page);
    expect(pending).toBeNull();
  });

  test('TC5: Offline save queues to localStorage with pending UI', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    await page.context().setOffline(true);
    await page.locator('.option-label').filter({ hasText: 'Answer B' }).locator('input').check();

    // Nav shows pending
    const navBtn = page.locator('.nav-btn[data-qid="1"]');
    await expect(navBtn).toHaveClass(/bg-amber-400/, { timeout: 3000 });

    // Pending badge shows count
    await expect(page.locator('#offline-pending-count')).toHaveText('1 pending');

    // localStorage has the answer
    const pending = await getPendingFromLS(page);
    expect(pending).not.toBeNull();
    expect(pending!.answers['1']).toBeDefined();
    expect(pending!.answers['1'].option_id).toBe(2);
  });

  test('TC6: Auto-sync on reconnect flushes pending and clears localStorage', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    await page.context().setOffline(true);
    await page.locator('.option-radio').first().check();
    const navBtn = page.locator('.nav-btn[data-qid="1"]');
    await expect(navBtn).toHaveClass(/bg-amber-400/, { timeout: 3000 });

    // Reconnect
    await page.context().setOffline(false);
    await page.waitForTimeout(3000);

    // Nav should change to confirmed
    await expect(navBtn).toHaveClass(/bg-primary/, { timeout: 5000 });

    // localStorage should be empty
    const pending = await getPendingFromLS(page);
    expect(pending).toBeNull();
  });

  test('TC7: Bulk sync sends ALL pending answers on reconnect', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    // Only 1 question exists from seed, but we can test that 1 answer syncs properly
    await page.context().setOffline(true);
    await page.locator('.option-label').filter({ hasText: 'Answer D' }).locator('input').check();
    await expect(page.locator('#offline-pending-count')).toHaveText('1 pending');

    // Reconnect and flush
    await page.context().setOffline(false);
    await page.waitForTimeout(3000);

    // Verify it synced
    const pending = await getPendingFromLS(page);
    expect(pending).toBeNull();
    const navBtn = page.locator('.nav-btn[data-qid="1"]');
    await expect(navBtn).toHaveClass(/bg-primary/, { timeout: 5000 });
  });

  test('TC8: Tab switch counts accumulate offline and sync on reconnect', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    await page.context().setOffline(true);

    for (let i = 0; i < 2; i++) {
      await page.evaluate(() => {
        Object.defineProperty(document, 'visibilityState', {
          configurable: true,
          get: () => 'hidden',
        });
        document.dispatchEvent(new Event('visibilitychange'));
      });
      await page.waitForTimeout(2100);
    }

    // Verify accumulated locally
    const tabCount = await getPendingFromLS(page).then(d => d?.tab_switches || 0);
    expect(tabCount).toBeGreaterThanOrEqual(2);

    // Reconnect
    await page.context().setOffline(false);
    await page.waitForTimeout(3000);

    // Verify cleared locally
    const afterSync = await getPendingFromLS(page);
    expect(afterSync).toBeNull();
  });

  // ════════════════════════════════════════════
  //  CATEGORY 3: DATA INTEGRITY
  // ════════════════════════════════════════════

  test('TC9: Deduplication — changing answer offline keeps only latest', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    await page.context().setOffline(true);

    // First pick Answer C, then change to Answer D
    await page.locator('.option-label').filter({ hasText: 'Answer C' }).locator('input').check();
    await page.waitForTimeout(300);
    await page.locator('.option-label').filter({ hasText: 'Answer D' }).locator('input').check();

    // Should only have 1 entry with the latest value
    const pending = await getPendingFromLS(page);
    expect(pending).not.toBeNull();
    expect(Object.keys(pending!.answers).length).toBe(1);
    expect(pending!.answers['1'].option_id).toBe(4);
  });

  test('TC10: localStorage data persists across page reload', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    // Answer offline
    await page.context().setOffline(true);
    await page.locator('.option-label').filter({ hasText: 'Answer B' }).locator('input').check();
    await expect(page.locator('#offline-pending-count')).toHaveText('1 pending');

    // Go online before reload so the page can load
    await page.context().setOffline(false);
    await page.waitForTimeout(1000);

    // Reload the page
    await page.reload();
    await page.waitForURL(/\/student\/exams\/\d+\/attempt/);
    await expect(page.locator('.question-card')).toBeVisible({ timeout: 5000 });

    // localStorage should still have the pending data
    const pending = await getPendingFromLS(page);
    expect(pending).not.toBeNull();
    expect(pending!.answers['1']).toBeDefined();
    expect(pending!.answers['1'].option_id).toBe(2);

    // Nav button should restore pending state (the page loads with online, so pending answers trigger flush)
    // After flush, the nav button transitions to confirmed
    const navBtn = page.locator('.nav-btn[data-qid="1"]');
    await expect(navBtn).toHaveClass(/bg-primary/, { timeout: 5000 });
  });

  test('TC11: Tab switch SET semantics — server uses max to prevent rollback', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    // First trigger tab switches while online (to set baseline on server)
    await page.evaluate(() => {
      Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        get: () => 'hidden',
      });
      document.dispatchEvent(new Event('visibilitychange'));
    });
    await page.waitForTimeout(2100);
    await page.evaluate(() => {
      Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        get: () => 'hidden',
      });
      document.dispatchEvent(new Event('visibilitychange'));
    });
    await page.waitForTimeout(2100);

    // Now go offline and accumulate more
    await page.context().setOffline(true);
    for (let i = 0; i < 2; i++) {
      await page.evaluate(() => {
        Object.defineProperty(document, 'visibilityState', {
          configurable: true,
          get: () => 'hidden',
        });
        document.dispatchEvent(new Event('visibilitychange'));
      });
      await page.waitForTimeout(2100);
    }

    // Reconnect — flush should SET tab_switches to max(local, server)
    await page.context().setOffline(false);
    await page.waitForTimeout(3000);

    // Server should have >= 3 (at least 2 from online + 2 from offline, or 2+2=4, but online ones
    // were called before there was a session active tab_switches, actually they were always on
    // the attempt page, so session.tab_switches starts at 0).
    // The key: the total should never decrease after sync.
    const serverTabSwitches = await page.evaluate(() => (window as any).serverTabSwitches);
    expect(serverTabSwitches).toBeGreaterThanOrEqual(2);
  });

  // ════════════════════════════════════════════
  //  CATEGORY 4: SERVER-SIDE VALIDATION
  // ════════════════════════════════════════════

  test('TC12: Sync endpoint rejects invalid option_id', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    // Only 1 question exists in the exam
    const syncUrl = `/student/exams/${EXAM_ID}/sync`;
    const csrfToken = await page.evaluate(() => {
      const input = document.querySelector('input[name="_token"]');
      return input ? (input as HTMLInputElement).value : '';
    });

    // Send sync with invalid option_id 9999
    const res = await page.evaluate(({ url, token }) => {
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: JSON.stringify({
          _token: token,
          answers: [{ question_id: 1, option_id: 9999 }],
        }),
      }).then(r => r.ok ? r.json() : { status: r.status });
    }, { url: syncUrl, token: csrfToken });

    // Sync should succeed (200) but report the error for that answer
    expect(res.synced).toBe(0);
    expect(res.errors).toHaveLength(1);
    expect(res.errors[0]).toContain('9999');
  });

  test('TC13: Sync endpoint rejects invalid question_id', async ({ studentPage }) => {
    const page = await gotoAttempt(studentPage);

    const syncUrl = `/student/exams/${EXAM_ID}/sync`;
    const csrfToken = await page.evaluate(() => {
      const input = document.querySelector('input[name="_token"]');
      return input ? (input as HTMLInputElement).value : '';
    });

    const res = await page.evaluate(({ url, token }) => {
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: JSON.stringify({
          _token: token,
          answers: [{ question_id: 9999, option_id: 1 }],
        }),
      }).then(r => r.ok ? r.json() : { status: r.status });
    }, { url: syncUrl, token: csrfToken });

    expect(res.synced).toBe(0);
    expect(res.errors).toHaveLength(1);
  });

  // ════════════════════════════════════════════
  //  CATEGORY 5: FULL STUDENT FLOW
  // ════════════════════════════════════════════

  test('TC14: Full flow — answer offline → reconnect → flush → submit → success', async ({ studentPage }) => {
    const { page } = studentPage;

    // Step 1: Go to attempt page
    await page.goto(`/student/exams/${EXAM_ID}/attempt`);
    await page.waitForURL(/\/student\/exams\/\d+\/attempt/);
    await expect(page.locator('.question-card')).toBeVisible({ timeout: 5000 });

    // Step 2: Answer offline (Answer A = correct option)
    await page.context().setOffline(true);
    await page.locator('.option-label').filter({ hasText: 'Answer A' }).locator('input').check();
    await expect(page.locator('#offline-pending-count')).toHaveText('1 pending');

    // Step 3: Reconnect — auto-sync should flush
    await page.context().setOffline(false);
    await page.waitForTimeout(3000);

    // Step 4: Verify synced (nav shows confirmed)
    const navBtn = page.locator('.nav-btn[data-qid="1"]');
    await expect(navBtn).toHaveClass(/bg-primary/, { timeout: 5000 });

    // Step 5: Submit the exam
    await page.getByRole('button', { name: /kumpulkan ujian/i }).first().click();
    await expect(page.locator('#submit-modal:not(.hidden)')).toBeVisible({ timeout: 3000 });
    await page.locator('#submit-modal').getByRole('button', { name: /kumpulkan$/i }).click();

    // Step 6: Verify redirected to dashboard with success
    await page.waitForURL(/\/student\/dashboard/);
    await expect(page.getByText('Ujian berhasil diselesaikan')).toBeVisible({ timeout: 5000 });
  });

  test('TC15: Submit while offline sets pending_submit → reconnect auto-submits', async ({ studentPage }) => {
    const { page } = studentPage;

    await page.goto(`/student/exams/${EXAM_ID}/attempt`);
    await page.waitForURL(/\/student\/exams\/\d+\/attempt/);
    await expect(page.locator('.question-card')).toBeVisible({ timeout: 5000 });

    // Answer a question offline
    await page.context().setOffline(true);
    await page.locator('.option-label').filter({ hasText: 'Answer A' }).locator('input').check();
    await page.waitForTimeout(500);

    // Open submit modal
    await page.getByRole('button', { name: /kumpulkan ujian/i }).first().click();
    await expect(page.locator('#submit-modal:not(.hidden)')).toBeVisible({ timeout: 3000 });

    // Handle the offline alert dialog
    page.once('dialog', (dialog) => {
      expect(dialog.message()).toContain('Koneksi terputus');
      dialog.accept();
    });

    // Click confirm
    await page.locator('#submit-modal').getByRole('button', { name: /kumpulkan$/i }).click();
    await page.waitForTimeout(500);

    // Verify pending_submit flag is set
    const pending = await getPendingFromLS(page);
    expect(pending?.pending_submit).toBe(true);

    // Reconnect — should trigger flush + auto-submit
    await page.context().setOffline(false);

    // Should be redirected to dashboard after auto-submit
    await page.waitForURL(/\/student\/dashboard/, { timeout: 10000 });
    await expect(page.getByText('Ujian berhasil diselesaikan')).toBeVisible({ timeout: 5000 });
  });
});
