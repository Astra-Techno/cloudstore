import { test, expect } from '@playwright/test'
import { login } from './helpers'

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('displays dashboard with stats cards', async ({ page }) => {
    await expect(page.locator('main h1')).toHaveText('Dashboard')

    // Should show 4 stat cards
    await expect(page.locator("text=Today's Orders")).toBeVisible()
    await expect(page.locator("text=Today's Revenue")).toBeVisible()
    await expect(page.locator('text=Active Orders')).toBeVisible()
    await expect(page.locator('text=Total Customers')).toBeVisible()
  })

  test('sidebar navigation is visible', async ({ page }) => {
    await expect(page.locator('aside h1:has-text("CloudStore")')).toBeVisible()
    await expect(page.locator('nav >> text=Dashboard')).toBeVisible()
    await expect(page.locator('nav >> text=Orders')).toBeVisible()
    await expect(page.locator('nav >> text=Categories')).toBeVisible()
    await expect(page.locator('nav >> text=Products')).toBeVisible()
  })

  test('notification bell is visible', async ({ page }) => {
    // Bell icon should be in the header
    await expect(page.locator('.notification-bell')).toBeVisible()
  })
})
