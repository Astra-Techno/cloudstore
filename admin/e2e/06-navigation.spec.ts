import { test, expect } from '@playwright/test'
import { login } from './helpers'

test.describe('Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
  })

  test('navigates to all pages via sidebar', async ({ page }) => {
    // Dashboard
    await page.click('nav >> text=Dashboard')
    await expect(page.locator('main h1')).toHaveText('Dashboard')

    // Orders
    await page.click('nav >> text=Orders')
    await page.waitForURL('/orders')
    await expect(page.locator('main h1')).toHaveText('Orders')

    // Categories
    await page.click('nav >> text=Categories')
    await page.waitForURL('/categories')
    await expect(page.locator('main h1')).toHaveText('Categories')

    // Products
    await page.click('nav >> text=Products')
    await page.waitForURL('/products')
    await expect(page.locator('main h1')).toHaveText('Products')

    // Back to Dashboard
    await page.click('nav >> text=Dashboard')
    await page.waitForURL('/')
    await expect(page.locator('main h1')).toHaveText('Dashboard')
  })

  test('highlights active nav item', async ({ page }) => {
    // On dashboard, Dashboard link should be active (blue)
    const dashLink = page.locator('nav >> text=Dashboard')
    await expect(dashLink).toHaveClass(/bg-blue-50/)

    // Navigate to orders
    await page.click('nav >> text=Orders')
    await page.waitForURL('/orders')
    const ordersLink = page.locator('nav >> text=Orders')
    await expect(ordersLink).toHaveClass(/bg-blue-50/)
  })

  test('shows admin email in sidebar', async ({ page }) => {
    await expect(page.locator('text=jeyam-mutton@cloudstore.com')).toBeVisible()
  })
})
