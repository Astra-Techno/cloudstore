import { test, expect } from '@playwright/test'
import { ensureLoggedIn } from './helpers'

test.describe('Refund Management', () => {
  test.beforeEach(async ({ page }) => {
    await ensureLoggedIn(page)
  })

  test('navigates to refunds page', async ({ page }) => {
    await page.goto('/refunds')
    await expect(page.locator('h1')).toContainText('Refund')
  })

  test('shows orders table or empty state', async ({ page }) => {
    await page.goto('/refunds')
    await page.waitForTimeout(3000)
    const hasTable = await page.locator('table').count()
    const hasEmpty = await page.locator('text=/no orders|empty/i').count()
    expect(hasTable + hasEmpty).toBeGreaterThan(0)
  })
})
