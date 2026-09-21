<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ name: string }>()
const paths: Record<string, string> = {
  Overview: 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
  Sales: 'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 7h6 M9 11h6',
  Catalog: 'M4 7h16v14H4z M8 7V5a4 4 0 018 0v2',
  Marketing: 'M3 10v4h4l11 5V5L7 10z M7 14l2 7h3l-2-6',
  People: 'M16 7a4 4 0 11-8 0 4 4 0 018 0 M4 21v-2a8 8 0 0116 0v2',
  Manage: 'M12 3v3 M12 18v3 M3 12h3 M18 12h3 M6 6l2 2 M16 16l2 2 M6 18l2-2 M16 8l2-2 M17 12a5 5 0 11-10 0 5 5 0 0110 0',
  Tables: 'M4 5h16v10H4z M6 15v6 M18 15v6 M8 2v3 M16 2v3',
  Counter: 'M4 3h16v18H4z M7 6h10v4H7z M7 14h1 M12 14h1 M17 14h1 M7 18h1 M12 18h1 M17 18h1',
  Reports: 'M4 3v18h17 M8 16v-5 M13 16V7 M18 16V4',
  Refunds: 'M3 10a8 8 0 111 8 M3 4v6h6 M12 8v8 M15 9h-4a2 2 0 000 4h2a2 2 0 010 4h-4',
  'All apps': 'M3 3h4v4H3z M10 3h4v4h-4z M17 3h4v4h-4z M3 10h4v4H3z M10 10h4v4h-4z M17 10h4v4h-4z M3 17h4v4H3z M10 17h4v4h-4z M17 17h4v4h-4z',
}
const aliases: Record<string, string> = { Dashboard: 'Overview', Orders: 'Sales', Products: 'Catalog', Categories: 'All apps', 'Stock alerts': 'Catalog', Offers: 'Marketing', Coupons: 'Marketing', Combos: 'Catalog', Customers: 'People', Drivers: 'People', Support: 'People', 'Driver earnings': 'Reports', 'Store settings': 'Manage', Settings: 'Manage', 'Platform settings': 'Manage', 'Audit log': 'Sales', 'Delivery zones': 'Tables', 'Mobile apps': 'All apps', Tenants: 'People', 'Fee ledger': 'Reports' }
const path = computed(() => paths[aliases[props.name] || props.name] || paths['All apps'])
</script>
<template><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="path"/></svg></template>
