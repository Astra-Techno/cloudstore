import { test, expect } from '@playwright/test'
import { login } from './helpers'

test.describe('Categories', () => {
  test.beforeEach(async ({ page }) => {
    await login(page)
    await page.click('nav >> text=Categories')
    await page.waitForURL('/categories')
  })

  test('displays categories list', async ({ page }) => {
    await expect(page.locator('main h1')).toHaveText('Categories')
    // Should have the table with seeded categories
    await expect(page.locator('table')).toBeVisible()
    // Jeyam Mutton has "Mutton" and "Chicken" categories
    await expect(page.locator('td:has-text("Mutton")').first()).toBeVisible()
    await expect(page.locator('td:has-text("Chicken")').first()).toBeVisible()
  })

  test('creates a new category', async ({ page }) => {
    const categoryName = `Seafood ${Date.now()}`
    await page.click('text=+ Add Category')
    // Modal should appear
    await expect(page.locator('h2:has-text("New Category")')).toBeVisible()

    await page.fill('input[type="text"]', categoryName)
    await page.click('button:has-text("Save")')

    // Modal should close and new category should appear
    await expect(page.locator('h2:has-text("New Category")')).not.toBeVisible({ timeout: 10000 })
    await expect(page.locator(`td:has-text("${categoryName}")`)).toBeVisible()
  })

  test('edits a category', async ({ page }) => {
    // Click edit on the first category
    const editButton = page.locator('text=Edit').first()
    await editButton.click()

    // Modal should appear with "Edit Category"
    await expect(page.locator('h2:has-text("Edit Category")')).toBeVisible()

    // Change status to inactive
    await page.selectOption('select', 'inactive')
    await page.click('button:has-text("Save")')

    // Modal should close
    await expect(page.locator('h2:has-text("Edit Category")')).not.toBeVisible({ timeout: 5000 })
  })

  test('shows Add Category button', async ({ page }) => {
    await expect(page.locator('button:has-text("+ Add Category")')).toBeVisible()
  })
})
