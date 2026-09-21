export interface NavPage { label: string; path: string; capability?: string }
export interface NavSection { label: string; icon: string; pages: NavPage[] }
export const tenantSections: NavSection[] = [
  { label: 'Overview', icon: 'overview', pages: [{ label: 'Dashboard', path: '/' }, { label: 'Reports', path: '/reports' }] },
  { label: 'Sales', icon: 'orders', pages: [{ label: 'Orders', path: '/orders' }, { label: 'Tables', path: '/tables', capability: 'qr_table_ordering' }, { label: 'Refunds', path: '/refunds' }] },
  { label: 'Counter', icon: 'counter', pages: [{ label: 'Counter', path: '/counter' }] },
  { label: 'Catalog', icon: 'products', pages: [{ label: 'Products', path: '/products' }, { label: 'Categories', path: '/categories' }, { label: 'Stock alerts', path: '/stock-alerts' }] },
  { label: 'Marketing', icon: 'promotions', pages: [{ label: 'Offers', path: '/promotions', capability: 'promotions' }, { label: 'Coupons', path: '/coupons', capability: 'coupons' }, { label: 'Combos', path: '/bundles', capability: 'bundles' }] },
  { label: 'People', icon: 'customers', pages: [{ label: 'Customers', path: '/customers' }, { label: 'Drivers', path: '/drivers', capability: 'drivers' }, { label: 'Driver earnings', path: '/driver-earnings', capability: 'drivers' }, { label: 'Support', path: '/support' }] },
  { label: 'Manage', icon: 'settings', pages: [{ label: 'Store settings', path: '/settings' }, { label: 'Delivery zones', path: '/delivery-zones', capability: 'delivery' }, { label: 'Mobile apps', path: '/mobile-apps' }, { label: 'Audit log', path: '/audit-log' }] },
]
export const platformSections: NavSection[] = [
  { label: 'Dashboard', icon: 'overview', pages: [{ label: 'Dashboard', path: '/' }] },
  { label: 'Tenants', icon: 'customers', pages: [{ label: 'Tenants', path: '/tenants' }] },
  { label: 'Fee ledger', icon: 'orders', pages: [{ label: 'Fee ledger', path: '/marketplace-fees' }] },
  { label: 'Settings', icon: 'settings', pages: [{ label: 'Platform settings', path: '/platform-config' }] },
]
export const matchesPage = (path: string, target: string) => path === target || (target !== '/' && path.startsWith(target + '/'))
