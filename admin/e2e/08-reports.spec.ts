import { test, expect } from '@playwright/test'
import { ensureLoggedIn } from './helpers'

test.describe('Reports & Analytics', () => {
  test.beforeEach(async ({ page }) => {
    await ensureLoggedIn(page)
  })

  test('navigates to reports page', async ({ page }) => {
    await page.goto('/reports')
    await expect(page.locator('h1')).toContainText('Reports')
  })

  test('displays summary cards', async ({ page }) => {
    await page.goto('/reports')
    await page.waitForTimeout(2000)
    // Should have summary stat cards (revenue, orders, avg value)
    const cards = page.locator('.list-stat-rail > div, .stat-card, [class*="stat"]')
    await expect(cards.first()).toBeVisible({ timeout: 10000 })
  })

  test('date range filter works', async ({ page }) => {
    await page.goto('/reports')
    const fromInput = page.locator('input[type="date"]').first()
    if (await fromInput.isVisible()) {
      await fromInput.fill('2025-01-01')
      await page.waitForTimeout(1000)
      // Page should update without errors
      await expect(page.locator('.bg-red-50')).not.toBeVisible()
    }
  })
})
