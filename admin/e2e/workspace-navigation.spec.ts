import { test, expect } from '@playwright/test'
test.use({ channel: 'chrome' })

test('platform admin gets platform tools, not tenant operations', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('admin_token', 'test-token'))
  await page.route('**/api/v1/**', async route => {
    const path = new URL(route.request().url()).pathname
    await route.fulfill({ json: { success: true, data: path.endsWith('/admin/me') ? { name: 'Platform Admin', role: 'platform_admin' } : {} } })
  })
  await page.goto('/tenants')
  await expect(page.locator('.sidebar-nav > a')).toHaveCount(4)
  await expect(page.locator('.sidebar-nav')).not.toContainText('Sales')
  await page.setViewportSize({ width: 390, height: 844 })
  await expect(page.locator('.mobile-dock')).toContainText('Tenants')
  await expect(page.locator('.mobile-dock')).not.toContainText('Counter')
})

test('grouped navigation and table panels work on desktop, tablet and phone', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('admin_token', 'test-token'))
  await page.route('**/api/v1/**', async route => {
    const path = new URL(route.request().url()).pathname
    let data: any = {}
    if (path.endsWith('/admin/me')) data = { name: 'Test Hotel', role: 'tenant_admin', email: 'test@example.com' }
    else if (path.endsWith('/admin/settings')) data = { store: { name: 'Test Hotel' }, branding: {}, capabilities: { qr_table_ordering: true, promotions: true, coupons: true, bundles: true, drivers: false } }
    else if (path.endsWith('/admin/tables')) data = [{ id: 1, name: 'Table 1', token: 'a'.repeat(64), enabled: true, session: null, orders: [], bill_total: 0 }]
    else if (path.includes('notifications')) data = { notifications: [], unread_count: 0 }
    await route.fulfill({ json: { success: true, data } })
  })
  for (const width of [1440, 820, 390]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/tables')
    await expect(page.locator('.table-tile')).toBeVisible()
    await expect(page.locator('.section-tabs a.selected')).toHaveText('Tables')
    if (width >= 768) {
      await expect(page.locator('.sidebar-nav > a')).toHaveCount(6)
      await expect(page.locator('.sidebar-link__label', { hasText: 'Sales' })).toBeVisible()
      await expect(page.locator('.mobile-dock')).toBeHidden()
    } else {
      await expect(page.locator('.mobile-dock')).toBeVisible()
      await page.getByRole('button', { name: 'More', exact: true }).click()
      await expect(page.locator('.mobile-tool-grid')).toBeVisible()
      await expect(page.locator('.mobile-tool-grid').getByText('Drivers', { exact: true })).toHaveCount(0)
      await page.getByRole('button', { name: 'Close navigation' }).click()
      await page.getByRole('button', { name: 'More', exact: true }).click()
      await page.keyboard.press('Escape')
      await expect(page.getByRole('button', { name: 'More', exact: true })).toBeFocused()
    }
    await page.locator('.table-tile').click()
    await expect(page.locator('.table-panel')).toBeVisible()
    await expect(page.locator('.table-panel .sticker-preview')).toBeHidden()
    await page.getByRole('button', { name: 'QR & Print', exact: true }).click()
    await expect(page.locator('.table-panel .sticker-preview')).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(page.locator('.table-panel')).toBeHidden()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy()
    await page.locator('.header-actions .page-finder-button').click()
    await page.getByRole('textbox', { name: 'Search pages' }).fill('stock')
    await expect(page.locator('.page-finder nav a')).toHaveCount(1)
    await page.keyboard.press('Escape')
    await page.keyboard.press('Control+k')
    await expect(page.locator('.page-finder')).toBeVisible()
    await page.keyboard.press('Escape')
  }
})

test('table failures are visible inside the panel and filters work', async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('admin_token', 'test-token'))
  await page.route('**/api/v1/**', async route => {
    const path = new URL(route.request().url()).pathname
    if (path.endsWith('/action')) { await route.fulfill({ status: 422, json: { success: false, error: { message: 'Please retry this table action.' } } }); return }
    let data: any = {}
    if (path.endsWith('/admin/me')) data = { name: 'Hotel', role: 'tenant_admin' }
    if (path.endsWith('/admin/settings')) data = { store: { name: 'Hotel', status: 'suspended' }, capabilities: { qr_table_ordering: true } }
    if (path.endsWith('/admin/tables')) data = [
      { id: 1, name: 'Terrace', token: 'a'.repeat(64), enabled: true, session: null, orders: [], bill_total: 0 },
      { id: 2, name: 'Window', token: 'b'.repeat(64), enabled: true, session: { access_code: '123456' }, orders: [{ uuid: 'test', order_number: 'DIN-1', status: 'preparing', total: 10000 }], bill_total: 10000 },
    ]
    if (path.includes('notifications')) data = { notifications: [], unread_count: 0 }
    await route.fulfill({ json: { success: true, data } })
  })
  await page.goto('/tables')
  await expect(page.getByText('Store offline')).toBeVisible()
  await page.getByRole('textbox', { name: 'Search tables' }).fill('terrace')
  await expect(page.locator('.table-tile')).toHaveCount(1)
  await page.locator('.table-tile').click()
  await page.getByRole('button', { name: 'Seat guests · Open visit' }).click()
  await expect(page.locator('.table-panel [role=alert]')).toContainText('Please retry this table action.')
  await page.keyboard.press('Escape')
  await page.getByRole('textbox', { name: 'Search tables' }).fill('')
  await page.getByRole('combobox', { name: 'Table status' }).selectOption('occupied')
  await expect(page.locator('.table-tile')).toHaveCount(1)
  await page.locator('.table-tile').click()
  await expect(page.getByRole('button', { name: 'Payment collected · Close bill' })).toBeDisabled()
  await expect(page.locator('.table-panel [role=alert]')).toHaveCount(0)
  await page.keyboard.press('Escape')
  await page.emulateMedia({ media: 'print' })
  await expect(page.locator('.print-cards')).toBeVisible()
  await expect(page.locator('.compact-table-grid')).toBeHidden()
})
