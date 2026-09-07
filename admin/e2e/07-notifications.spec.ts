import { test, expect } from '@playwright/test'
import { login } from './helpers'

test.describe('Notifications', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('notification bell is in header', async ({ page }) => {
    const bell = page.locator('.notification-bell')
    await expect(bell).toBeVisible()
    // Bell should have an SVG icon
    await expect(bell.locator('svg')).toBeVisible()
  })

  test('clicking bell opens dropdown', async ({ page }) => {
    await page.click('.notification-bell button')
    // Dropdown should appear
    await expect(page.locator('h3:has-text("Notifications")')).toBeVisible()
  })

  test('dropdown shows empty state or notifications', async ({ page }) => {
    await page.click('.notification-bell button')
    await page.waitForTimeout(1000)

    // Either "No notifications yet" or notification items
    const dropdown = page.locator('.notification-bell >> .absolute')
    await expect(dropdown).toBeVisible()
    const text = await dropdown.textContent()
    expect(text).toBeTruthy()
  })

  test('clicking outside closes dropdown', async ({ page }) => {
    await page.click('.notification-bell button')
    await expect(page.locator('h3:has-text("Notifications")')).toBeVisible()

    // Click outside (on the main content area)
    await page.click('h1')
    await expect(page.locator('h3:has-text("Notifications")')).not.toBeVisible()
  })
})
