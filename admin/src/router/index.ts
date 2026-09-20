import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/table/:token', name: 'table-menu', component: () => import('@/views/TableMenuView.vue') },
    { path: '/tables', name: 'tables', component: () => import('@/views/TablesView.vue'), meta: { requiresAuth: true } },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
    },
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/counter',
      name: 'counter',
      component: () => import('@/views/CounterView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/orders',
      name: 'orders',
      component: () => import('@/views/OrdersView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/orders/:uuid',
      name: 'order-detail',
      component: () => import('@/views/OrderDetailView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/categories',
      name: 'categories',
      component: () => import('@/views/CategoriesView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/products',
      name: 'products',
      component: () => import('@/views/ProductsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/products/:uuid',
      name: 'product-edit',
      component: () => import('@/views/ProductEditView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/customers',
      name: 'customers',
      component: () => import('@/views/CustomersView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/customers/:uuid',
      name: 'customer-detail',
      component: () => import('@/views/CustomerDetailView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/drivers',
      name: 'drivers',
      component: () => import('@/views/DriversView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/delivery-zones',
      name: 'delivery-zones',
      component: () => import('@/views/DeliveryZonesView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/support',
      name: 'support',
      component: () => import('@/views/SupportView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/coupons',
      name: 'coupons',
      component: () => import('@/views/CouponsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/promotions',
      name: 'promotions',
      component: () => import('@/views/PromotionsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/bundles',
      name: 'bundles',
      component: () => import('@/views/BundlesView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/reports',
      name: 'reports',
      component: () => import('@/views/ReportsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/audit-log',
      name: 'audit-log',
      component: () => import('@/views/AuditLogView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/refunds',
      name: 'refunds',
      component: () => import('@/views/RefundsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/stock-alerts',
      name: 'stock-alerts',
      component: () => import('@/views/StockAlertsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/driver-earnings',
      name: 'driver-earnings',
      component: () => import('@/views/DriverEarningsView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/tenants',
      name: 'tenants',
      component: () => import('@/views/TenantsView.vue'),
      meta: { requiresAuth: true, platformOnly: true },
    },
    {
      path: '/marketplace-fees',
      name: 'marketplace-fees',
      component: () => import('@/views/MarketplaceFeesView.vue'),
      meta: { requiresAuth: true, platformOnly: true },
    },
    {
      path: '/platform-config',
      name: 'platform-config',
      component: () => import('@/views/PlatformConfigView.vue'),
      meta: { requiresAuth: true, platformOnly: true },
    },
    {
      path: '/mobile-apps',
      name: 'mobile-apps',
      component: () => import('@/views/MobileAppsView.vue'),
      meta: { requiresAuth: true, tenantOnly: true },
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('@/views/SettingsView.vue'),
      // Store settings belong to tenant operators. Platform operators use the
      // dedicated configuration screen, where global/tenant credential values
      // are encrypted and never exposed back to the browser.
      meta: { requiresAuth: true, tenantOnly: true },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }
  if (to.meta.platformOnly && auth.user?.role !== 'platform_admin') {
    return { name: 'dashboard' }
  }
  if (to.meta.tenantOnly && auth.user?.role === 'platform_admin') {
    return { name: 'platform-config' }
  }
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
