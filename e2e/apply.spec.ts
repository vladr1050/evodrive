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

    // Step 3: ATD and experience
    await page.locator('button[data-atd="yes"]').click();
    await page.locator('button[data-exp="5-10"]').click();

    const nextBtn3 = page.getByTestId('apply-next-3');
    await nextBtn3.waitFor({ state: 'visible' });
    await expect(nextBtn3).toBeEnabled({ timeout: 5000 });
    await nextBtn3.click();

    // Step 4: Name and area
    await page.getByTestId('apply-name').fill('E2E Test User');
    await page.getByTestId('apply-area').fill('Riga');

    const nextBtn4 = page.getByTestId('apply-next-4');
    await nextBtn4.waitFor({ state: 'visible' });
    await expect(nextBtn4).toBeEnabled({ timeout: 5000 });
    await nextBtn4.click();

    // Step 5: Submit
    const submitBtn = page.getByTestId('apply-submit');
    await submitBtn.waitFor({ state: 'visible' });
    await submitBtn.click();

    // Explicit wait for redirect to thanks
    await page.waitForURL(/\/en\/thanks/, { timeout: 10000 });
    await expect(page).toHaveURL(/\/en\/thanks/);
    await expect(page.locator('body')).toContainText(/21234567|\+371.*21234567/);
  });
});
