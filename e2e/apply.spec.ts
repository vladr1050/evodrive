import { test, expect } from '@playwright/test';

test.describe('Apply wizard', () => {
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

  test('fills wizard, submits, and redirects to thanks with phone visible', async ({ page }) => {
    await page.goto('/en/apply');
    await page.waitForLoadState('networkidle');

    // Step 1: Phone
    const phoneInput = page.getByTestId('apply-phone');
    await phoneInput.waitFor({ state: 'visible' });
    await phoneInput.fill('21234567');

    const nextBtn = page.getByTestId('apply-next');
    await nextBtn.waitFor({ state: 'visible' });
    await expect(nextBtn).toBeEnabled({ timeout: 5000 });
    await nextBtn.click();

    // Step 2: Intent - select work
    await page.getByTestId('apply-intent-work').click();

    // Step 3: ATD, card number, experience
    await page.locator('button[data-atd="yes"]').click();
    await page.getByTestId('apply-atd-number').fill('ATD999888');
    await page.locator('button[data-exp="5-10"]').click();

    const nextQual = page.getByTestId('apply-next-qual');
    await nextQual.waitFor({ state: 'visible' });
    await expect(nextQual).toBeEnabled({ timeout: 5000 });
    await nextQual.click();

    // Step 4: Latvian B1
    await page.locator('button[data-latvian="yes"]').click();
    const nextLatvian = page.getByTestId('apply-next-latvian');
    await expect(nextLatvian).toBeEnabled({ timeout: 5000 });
    await nextLatvian.click();

    // Step 5: Shifts
    await page.locator('button[data-shift="mixed"]').click();
    const nextShifts = page.getByTestId('apply-next-shifts');
    await expect(nextShifts).toBeEnabled({ timeout: 5000 });
    await nextShifts.click();

    // Step 6: Name, email, area
    await page.getByTestId('apply-name').fill('E2E Test User');
    await page.getByTestId('apply-email').fill('e2e@example.com');
    await page.getByTestId('apply-area').fill('Riga');

    const nextDetails = page.getByTestId('apply-next-details');
    await nextDetails.waitFor({ state: 'visible' });
    await expect(nextDetails).toBeEnabled({ timeout: 5000 });
    await nextDetails.click();

    // Step 7: Submit
    const submitBtn = page.getByTestId('apply-submit');
    await submitBtn.waitFor({ state: 'visible' });
    await submitBtn.click();

    await page.waitForURL(/\/en\/thanks/, { timeout: 10000 });
    await expect(page).toHaveURL(/\/en\/thanks/);
    await expect(page.locator('body')).toContainText(/21234567|\+371.*21234567/);
  });
});
