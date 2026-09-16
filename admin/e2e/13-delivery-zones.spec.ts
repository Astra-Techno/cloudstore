import { test, expect } from '@playwright/test'
import { ensureLoggedIn } from './helpers'

test.describe('Delivery Zones', () => {
  test.beforeEach(async ({ page }) => {
    await ensureLoggedIn(page)
  })

  test('navigates to delivery zones page', async ({ page }) => {
    await page.goto('/delivery-zones')
    await expect(page.locator('h1')).toContainText('Delivery Zones')
  })

  test('shows add zone button', async ({ page }) => {
    await page.goto('/delivery-zones')
    await expect(page.locator('text=Add zone')).toBeVisible()
  })

  test('opens create zone modal', async ({ page }) => {
    await page.goto('/delivery-zones')
    await page.waitForTimeout(1000)
    await page.click('text=Add zone')
    await expect(page.locator('text=New Delivery Zone')).toBeVisible()
    // Modal should have pincode field
    await expect(page.locator('text=Pincodes')).toBeVisible()
  })

  test('can close modal', async ({ page }) => {
    await page.goto('/delivery-zones')
    await page.waitForTimeout(1000)
    await page.click('text=Add zone')
    await expect(page.locator('text=New Delivery Zone')).toBeVisible()
    await page.click('text=Cancel')
    await expect(page.locator('text=New Delivery Zone')).not.toBeVisible()
  })
})
