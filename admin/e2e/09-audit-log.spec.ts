import { test, expect } from '@playwright/test'
import { ensureLoggedIn } from './helpers'

test.describe('Audit Log', () => {
  test.beforeEach(async ({ page }) => {
    await ensureLoggedIn(page)
  })

  test('navigates to audit log page', async ({ page }) => {
    await page.goto('/audit-log')
    await expect(page.locator('h1')).toContainText('Audit Log')
  })

  test('displays audit entries or empty state', async ({ page }) => {
    await page.goto('/audit-log')
    await page.waitForTimeout(2000)
    // Either shows entries or "No audit" empty state
    const hasEntries = await page.locator('table tbody tr, .audit-entry, [class*="audit"]').count()
    const hasEmpty = await page.locator('text=/no audit|no entries|empty/i').count()
    expect(hasEntries + hasEmpty).toBeGreaterThan(0)
  })
})
