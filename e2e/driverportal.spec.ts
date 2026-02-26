import { test, expect } from '@playwright/test';

test.describe('Driver Portal', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      const style = document.createElement('style');
      style.textContent = `
        *, *::before, *::after {
          animation-duration: 0s !important;
          animation-delay: 0s !important;
          transition-duration: 0s !important;
          transition-delay: 0s !important;
        }
      `;
      document.head.appendChild(style);
    });
  });

  test('login, navigate dashboard/shifts/profile, create and cancel shift', async ({ page }) => {
    // Login
    await page.goto('/en/driverportal');
    await page.waitForLoadState('networkidle');

    await page.getByTestId('driverportal-email').fill('driver@evodrive.lv');
    await page.getByTestId('driverportal-password').fill('password');
    await page.getByTestId('driverportal-login-submit').click();

    // Wait for redirect to dashboard
    await page.waitForURL(/\/driverportal\/dashboard/, { timeout: 10000 });
    await expect(page).toHaveURL(/\/driverportal\/dashboard/);

    await page.getByRole('link', { name: /shifts|maiņas|смены/i }).first().click();
    await page.waitForURL(/\/driverportal\/shifts/);
    await expect(page).toHaveURL(/\/driverportal\/shifts/);

    // Create shift - open modal
    await page.getByTestId('shift-create-btn').click();
    await page.getByTestId('shift-create-modal').waitFor({ state: 'visible', timeout: 5000 });

    // Use date from input min (deterministic: first valid day)
    const dateInput = page.getByTestId('shift-create-date');
    const minDate = await dateInput.getAttribute('min');
    const dateStr = minDate ?? new Date().toISOString().slice(0, 10);

    await dateInput.fill(dateStr);
    await page.locator('#create-station').selectOption({ index: 0 });
    await page.locator('#create-start').selectOption({ value: '08:00' });
    await page.locator('#create-duration').selectOption({ value: '4' });

    // Wait for availability API response
    const checkAvailabilityPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes('check-availability') &&
        resp.request().method() === 'POST' &&
        resp.status() === 200,
      { timeout: 10000 }
    );

    await page.getByTestId('shift-check-availability').click();
    await checkAvailabilityPromise;

    const confirmBtn = page.getByTestId('shift-confirm');
    await expect(confirmBtn).toBeEnabled({ timeout: 5000 });
    await confirmBtn.click();

    // Wait for modal to close (page reloads on success)
    await page.waitForLoadState('networkidle');

    // Profile
    await page.getByRole('link', { name: /profile|profils|профиль/i }).first().click();
    await page.waitForURL(/\/driverportal\/profile/);
    await expect(page).toHaveURL(/\/driverportal\/profile/);

    // Cancel shift - navigate back to shifts
    await page.getByRole('link', { name: /shifts|maiņas|смены/i }).first().click();
    await page.waitForURL(/\/driverportal\/shifts/);

    const cancelBtn = page.getByTestId('shift-cancel-btn').first();
    if (await cancelBtn.isVisible()) {
      await cancelBtn.click();
      await page.getByTestId('shift-cancel-modal').waitFor({ state: 'visible', timeout: 5000 });
      await page.getByTestId('shift-cancel-confirm').click();
    }

    // Logout
    await page.getByRole('button', { name: /logout|iziet|выход/i }).click();
    await page.waitForURL(/\/driverportal$/);
    await expect(page).toHaveURL(/\/driverportal$/);
  });
});
