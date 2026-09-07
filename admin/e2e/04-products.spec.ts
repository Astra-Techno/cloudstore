import { test, expect } from '@playwright/test'
import { login } from './helpers'

test.describe('Products', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await page.click('nav >> text=Products')
    await page.waitForURL('/products')
  })

  test('displays products list', async ({ page }) => {
    await expect(page.locator('main h1')).toHaveText('Products')
    await expect(page.locator('table')).toBeVisible()
    // Jeyam Mutton has seeded products
    await expect(page.locator('td:has-text("Mutton Curry Cut")')).toBeVisible()
  })

  test('shows product table headers', async ({ page }) => {
    await expect(page.locator('th:has-text("Name")')).toBeVisible()
    await expect(page.locator('th:has-text("Category")')).toBeVisible()
    await expect(page.locator('th:has-text("Pricing")')).toBeVisible()
    await expect(page.locator('th:has-text("Price")')).toBeVisible()
    await expect(page.locator('th:has-text("Status")')).toBeVisible()
  })

  test('opens create product modal', async ({ page }) => {
    await page.click('text=+ Add Product')
    await expect(page.locator('h2:has-text("New Product")')).toBeVisible()

    // Should have form fields
    await expect(page.locator('label:has-text("Name")')).toBeVisible()
    await expect(page.locator('label:has-text("Category")')).toBeVisible()
    await expect(page.locator('label:has-text("Base Price")')).toBeVisible()
    await expect(page.locator('label:has-text("Pricing Mode")')).toBeVisible()

    // Cancel should close modal
    await page.click('button:has-text("Cancel")')
    await expect(page.locator('h2:has-text("New Product")')).not.toBeVisible()
  })

  test('creates a new product', async ({ page }) => {
    const productName = `Test Product ${Date.now()}`
    await page.click('text=+ Add Product')
    await expect(page.locator('h2:has-text("New Product")')).toBeVisible()

    await page.locator('input[type="text"]').fill(productName)
    // Wait for categories to load, then select first
    const categorySelect = page.locator('select').first()
    await expect(categorySelect.locator('option')).not.toHaveCount(0, { timeout: 5000 })
    await categorySelect.selectOption({ index: 0 })
    // Set price
    await page.locator('input[type="number"]').first().fill('250')
    await page.click('button:has-text("Create Product")')

    // Modal should close and product should appear
    await expect(page.locator('h2:has-text("New Product")')).not.toBeVisible({ timeout: 10000 })
    await expect(page.locator(`td:has-text("${productName}")`)).toBeVisible()
  })
})
