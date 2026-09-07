import { type Page } from '@playwright/test'

export const TEST_ADMIN = {
  email: 'jeyam-mutton@cloudstore.com',
  password: 'Admin@123',
}

export async function login(page: Page) {
  await page.goto('/login')
  await page.fill('input[type="email"]', TEST_ADMIN.email)
  await page.fill('input[type="password"]', TEST_ADMIN.password)
  await page.click('button[type="submit"]')
  // Wait for redirect to dashboard
  await page.waitForURL('/', { timeout: 10000 })
}

export async function ensureLoggedIn(page: Page) {
  // Check if already on an authenticated page
  const url = page.url()
  if (url.includes('/login')) {
    await login(page)
  } else {
    await page.goto('/')
    // If redirected to login, perform login
    await page.waitForTimeout(500)
    if (page.url().includes('/login')) {
      await login(page)
    }
  }
}

export function formatPrice(paise: number): string {
  return '₹' + (paise / 100).toFixed(2)
}
