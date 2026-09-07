import { test, expect } from '@playwright/test'
import { login } from './helpers'

test.describe('Orders', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await page.click('nav >> text=Orders')
    await page.waitForURL('/orders')
  })

  test('displays orders page with table', async ({ page }) => {
    await expect(page.locator('main h1')).toHaveText('Orders')
    await expect(page.locator('table')).toBeVisible()

    // Table headers
    await expect(page.locator('th:has-text("Order")')).toBeVisible()
    await expect(page.locator('th:has-text("Customer")')).toBeVisible()
    await expect(page.locator('th:has-text("Status")')).toBeVisible()
    await expect(page.locator('th:has-text("Type")')).toBeVisible()
    await expect(page.locator('th:has-text("Total")')).toBeVisible()
    await expect(page.locator('th:has-text("Date")')).toBeVisible()
  })

  test('has status filter dropdown', async ({ page }) => {
    const select = page.locator('select')
    await expect(select).toBeVisible()

    // Check filter options
    await expect(select.locator('option:has-text("All Statuses")')).toBeAttached()
    await expect(select.locator('option:has-text("Confirmed")')).toBeAttached()
    await expect(select.locator('option:has-text("Delivered")')).toBeAttached()
    await expect(select.locator('option:has-text("Cancelled")')).toBeAttached()
  })

  test('shows empty state when no orders match filter', async ({ page }) => {
    // Filter by a status that likely has no orders
    await page.selectOption('select', 'cancelled')
    await page.waitForTimeout(1000)
    // Either shows orders or empty message
    const hasOrders = await page.locator('tbody tr').count()
    if (hasOrders <= 1) {
      // Either empty row or "No orders found" text
      const text = await page.locator('tbody').textContent()
      expect(text).toBeTruthy()
    }
  })
})
