import { test, expect } from '@playwright/test'
import { ensureLoggedIn } from './helpers'

test.describe('Stock Alerts', () => {
  test.beforeEach(async ({ page }) => {
    await ensureLoggedIn(page)
  })

  test('navigates to stock alerts page', async ({ page }) => {
    await page.goto('/stock-alerts')
    await expect(page.locator('h1')).toContainText('Stock')
  })

  test('filter buttons are visible', async ({ page }) => {
    await page.goto('/stock-alerts')
    await page.waitForTimeout(1000)
    await expect(page.locator('text=Low Stock')).toBeVisible()
    await expect(page.locator('text=Out of Stock')).toBeVisible()
    await expect(page.locator('text=All Tracked')).toBeVisible()
  })

  test('switching filter works', async ({ page }) => {
    await page.goto('/stock-alerts')
    await page.waitForTimeout(2000)
    await page.click('text=Out of Stock')
    await page.waitForTimeout(500)
    await page.click('text=All Tracked')
    await page.waitForTimeout(500)
    // No crash = success
    await expect(page.locator('h1')).toContainText('Stock')
  })
})
