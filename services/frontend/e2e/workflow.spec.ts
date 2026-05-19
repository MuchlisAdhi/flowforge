import { test, expect } from '@playwright/test'

test.describe('Workflow E2E', () => {
  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto('/login')
    await page.fill('input[type="email"]', 'admin@flowforge.local')
    await page.fill('input[type="password"]', 'password')
    await page.click('button[type="submit"]')
    await page.waitForURL('/')
  })

  test('login and see dashboard', async ({ page }) => {
    await expect(page.locator('h1')).toContainText('Dashboard')
  })

  test('create workflow and trigger', async ({ page }) => {
    // Navigate to create workflow
    await page.click('text=Workflows')
    await page.waitForURL('/workflows')
    await page.click('text=New Workflow')
    await page.waitForURL('/workflows/new')

    // Fill form
    await page.fill('input[placeholder="My Workflow"]', 'E2E Test Workflow')
    await page.fill('textarea[placeholder*="What does"]', 'Created by E2E test')

    // Submit
    await page.click('text=Create Workflow')

    // Should redirect to workflow detail
    await expect(page.locator('h1')).toContainText('E2E Test Workflow')

    // Trigger the workflow
    await page.click('text=Trigger')

    // Should redirect to run detail
    await expect(page.locator('h1')).toContainText('Run Detail')
    await expect(page.locator('text=pending')).toBeVisible()
  })

  test('viewer cannot create workflow', async ({ page }) => {
    // Logout and login as viewer
    await page.goto('/login')
    await page.fill('input[type="email"]', 'viewer@flowforge.local')
    await page.fill('input[type="password"]', 'password')
    await page.click('button[type="submit"]')
    await page.waitForURL('/')

    // Navigate to workflows
    await page.click('text=Workflows')
    await page.waitForURL('/workflows')

    // Should not see "New Workflow" button
    await expect(page.locator('text=New Workflow')).not.toBeVisible()
  })
})
