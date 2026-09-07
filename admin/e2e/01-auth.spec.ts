import { test, expect } from '@playwright/test'
import { TEST_ADMIN, login } from './helpers'

test.describe('Authentication', () => {
  test('shows login page', async ({ page }) => {
    await page.goto('/login')
    await expect(page.locator('h1')).toHaveText('CloudStore Admin')
    await expect(page.locator('input[type="email"]')).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
    // Tenant ID field should NOT exist
    await expect(page.locator('input[type="number"]')).not.toBeVisible()
  })

  test('rejects invalid credentials', async ({ page }) => {
    await page.goto('/login')
    await page.fill('input[type="email"]', 'wrong@email.com')
    await page.fill('input[type="password"]', 'wrongpassword')
    await page.click('button[type="submit"]')
    // Should show error and stay on login
    await expect(page.locator('.bg-red-50')).toBeVisible({ timeout: 10000 })
    expect(page.url()).toContain('/login')
  })

  test('logs in with valid credentials', async ({ page }) => {
    await login(page)
    // Should be on dashboard
    await expect(page.locator('main h1')).toHaveText('Dashboard')
  })

  test('redirects unauthenticated users to login', async ({ page }) => {
    // Clear any stored token
    await page.goto('/login')
    await page.evaluate(() => localStorage.removeItem('admin_token'))
    await page.goto('/orders')
    await page.waitForTimeout(500)
    expect(page.url()).toContain('/login')
  })

  test('logs out successfully', async ({ page }) => {
    await login(page)
    await expect(page.locator('main h1')).toHaveText('Dashboard')
    // Click sign out
    await page.click('text=Sign Out')
    await page.waitForURL(/\/login/, { timeout: 5000 })
    expect(page.url()).toContain('/login')
  })
})
