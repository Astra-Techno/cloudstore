import { test, expect } from '@playwright/test'
import { ensureLoggedIn } from './helpers'

test.describe('Driver Earnings', () => {
  test.beforeEach(async ({ page }) => {
    await ensureLoggedIn(page)
  })

  test('navigates to driver earnings page', async ({ page }) => {
    await page.goto('/driver-earnings')
    await expect(page.locator('h1')).toContainText('Driver')
  })

  test('displays driver list or empty state', async ({ page }) => {
    await page.goto('/driver-earnings')
    await page.waitForTimeout(2000)
    const hasDrivers = await page.locator('[class*="rounded-xl"], [class*="driver"]').count()
    const hasEmpty = await page.locator('text=/no driver|empty/i').count()
    expect(hasDrivers + hasEmpty).toBeGreaterThan(0)
  })
})
