export interface ApiResponse<T = unknown> {
  success: boolean
  data?: T
  meta?: PaginatedMeta
  error?: {
    code: string
    message: string
    fields?: Record<string, string[]>
  }
}

export interface PaginatedMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface Admin {
  id: string
  name: string
  email: string
  role: string
  tenant_id: number | null
}

export interface Order {
  id: number
  uuid: string
  order_number: string
  tenant_id: number
  customer_id: number
  customer_name?: string
  customer_phone?: string
  status: string
  order_type: string
  subtotal: number
  delivery_fee: number
  tax_amount: number
  discount_amount: number
  total: number
  payment_method: string
  payment_status: string
  notes: string | null
  address_snapshot: string
  created_at: string
  updated_at: string
}

export interface OrderItem {
  id: number
  product_snapshot: string
  variant_snapshot: string | null
  addons_snapshot: string | null
  quantity: number
  unit_price: number
  addons_price: number
  line_total: number
  notes: string | null
}

export interface StatusHistory {
  from_status: string | null
  to_status: string
  actor_type: string | null
  notes: string | null
  created_at: string
}

export interface Category {
  id: number
  uuid: string
  name: string
  slug: string
  description?: string
  image_url?: string | null
  sort_order?: number
  status: string
  product_count?: number
}

export interface Product {
  id: number
  uuid: string
  name: string
  slug: string
  description?: string
  short_description?: string
  base_price: number
  sale_price: number | null
  pricing_mode: string
  unit: string
  status: string
  stock_mode: string
  stock_quantity: number | null
  min_quantity?: number
  max_quantity?: number
  preparation_time_minutes?: number | null
  is_featured?: number
  sort_order?: number
  category_id?: number
  category_name?: string
  category_slug?: string
  variants?: ProductVariant[]
  addon_groups?: AddonGroup[]
  images?: ProductImage[]
}

export interface ProductImage {
  id: number
  uuid: string
  product_id: number
  url: string
  alt_text: string | null
  sort_order: number
  is_primary: number
}

export interface ProductVariant {
  id: number
  uuid: string
  product_id: number
  name: string
  sku: string | null
  price: number
  compare_price: number | null
  weight_grams: number | null
  stock_mode: string
  stock_quantity: number | null
  sort_order: number
  status: string
}

export interface AddonGroup {
  id: number
  uuid: string
  tenant_id: number
  name: string
  is_required: number
  min_selections: number
  max_selections: number
  sort_order: number
  status: string
  items: AddonItem[]
}

export interface AddonItem {
  id: number
  uuid: string
  group_id: number
  name: string
  price: number
  sort_order: number
  status: string
}

export interface DashboardStats {
  today_orders: number
  today_revenue: number
  active_orders: Record<string, number>
  total_customers: number
  avg_order_value?: number
  weekly_revenue?: { date: string; orders: number; revenue: number }[]
  top_products?: { product_name: string; total_qty: number; total_revenue: number }[]
  low_stock?: { uuid: string; name: string; stock_quantity: number }[]
}

export interface Driver {
  id: number
  uuid: string
  name: string
  phone: string
  vehicle_type: string | null
  vehicle_number: string | null
  availability: string
}

export interface DeliveryZone {
  id: number
  uuid: string
  tenant_id: number
  name: string
  min_distance_km: number
  max_distance_km: number
  fee: number
  min_order_free_delivery: number | null
  status: string
  sort_order: number
}

export interface Customer {
  id: number
  uuid: string
  name: string | null
  phone: string | null
  email: string | null
  status: string
  last_login_at: string | null
  created_at: string
  order_count?: number
  total_spent?: number
}

export interface StoreSettings {
  store: {
    name: string
    slug: string
    business_type: string
    status: string
  }
  branding: {
    primary_color?: string
    logo_url?: string | null
    tagline?: string | null
  } | null
  business_hours: Record<string, { open: boolean; start: string; end: string }>
  preparation_time_default: number
  min_order_amount: number
  tax_rate: number
  delivery_enabled: boolean
  pickup_enabled: boolean
  payment_methods: string[]
}
