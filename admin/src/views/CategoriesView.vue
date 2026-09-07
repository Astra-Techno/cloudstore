<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { catalogApi } from '@/api/catalog'
import type { Category } from '@/types'

const categories = ref<Category[]>([])
const loading = ref(true)
const showForm = ref(false)
const editing = ref<Category | null>(null)
const saving = ref(false)
const error = ref('')
const searchQuery = ref('')

const form = ref({ name: '', description: '', image_url: '', status: 'active', sort_order: 0 })
const deleteConfirm = ref<Category | null>(null)
const deleting = ref(false)

async function loadCategories() {
  loading.value = true
  try {
    const { data } = await catalogApi.listCategories()
    if (data.success) {
      categories.value = data.data || []
    }
  } catch (e) {
    console.error('Failed to load categories', e)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', description: '', image_url: '', status: 'active', sort_order: 0 }
  error.value = ''
  showForm.value = true
}

function openEdit(cat: Category) {
  editing.value = cat
  form.value = {
    name: cat.name,
    description: cat.description || '',
    image_url: cat.image_url || '',
    status: cat.status,
    sort_order: cat.sort_order || 0,
  }
  error.value = ''
  showForm.value = true
}

async function saveCategory() {
  saving.value = true
  error.value = ''

  try {
    if (editing.value) {
      const { data } = await catalogApi.updateCategory(editing.value.uuid, {
        name: form.value.name,
        description: form.value.description || undefined,
        image_url: form.value.image_url || undefined,
        status: form.value.status,
        sort_order: form.value.sort_order,
      } as Partial<Category>)
      if (!data.success) {
        error.value = data.error?.message || 'Failed to update'
        return
      }
    } else {
      const { data } = await catalogApi.createCategory({
        name: form.value.name,
        description: form.value.description,
        image_url: form.value.image_url || undefined,
        status: form.value.status,
      })
      if (!data.success) {
        error.value = data.error?.message || 'Failed to create'
        return
      }
    }
    showForm.value = false
    await loadCategories()
  } catch (e) {
    error.value = 'An error occurred'
  } finally {
    saving.value = false
  }
}

async function deleteCategory() {
  if (!deleteConfirm.value) return
  deleting.value = true
  try {
    const { data } = await catalogApi.deleteCategory(deleteConfirm.value.uuid)
    if (!data.success) {
      error.value = data.error?.message || 'Failed to delete'
      return
    }
    deleteConfirm.value = null
    await loadCategories()
  } catch (e: any) {
    error.value = e.response?.data?.error?.message || 'Failed to delete category'
  } finally {
    deleting.value = false
  }
}

function matchesSearch(category: Category): boolean {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return true
  return [category.name, category.slug].some(value => value.toLowerCase().includes(query))
}

onMounted(loadCategories)
</script>

<template>
  <div class="list-page">
    <section class="list-page-intro"><div><p class="list-kicker">Catalog architecture</p><h1>Categories <span>/ storefront structure</span></h1><p>Give every product a clear place to be discovered.</p></div><button @click="openCreate" class="list-primary-action"><span>＋</span> Add category</button></section>
    <section class="list-stat-rail"><div><span>Collections</span><strong>{{ categories.length }}</strong><small>organized categories</small></div><div><span>Published</span><strong>{{ categories.filter(category => category.status === 'active').length }}</strong><small>visible to customers</small></div><div><span>Product coverage</span><strong class="list-stat-accent">{{ categories.reduce((sum, category) => sum + (category.product_count ?? 0), 0) }}</strong><small>products organized</small></div><div class="list-stat-rail__signal"><span>Structure</span><strong>Clear</strong><small><i></i> Easy to browse</small></div></section>
    <section class="list-toolbar"><label class="list-search"><span aria-hidden="true">⌕</span><input v-model="searchQuery" type="search" placeholder="Search category or slug" /></label><span class="list-toolbar-note">Drag-free, always clear</span></section>

    <div v-if="loading" class="list-loading">Loading your category map<span></span></div>

    <div v-else class="list-table-card"><table class="list-table"><thead>
          <tr>
            <th>Category</th><th>Slug</th><th>Status</th><th>Coverage</th><th>Order</th><th class="text-right">Action</th>
          </tr>
        </thead><tbody><tr v-for="cat in categories" v-show="matchesSearch(cat)" :key="cat.uuid" class="list-row">
            <td class="list-order-id"><span class="category-avatar">✦</span><div><strong>{{ cat.name }}</strong><small>{{ cat.description ? cat.description.substring(0, 40) : `Collection ${String(cat.id).padStart(2, '0')}` }}</small></div></td><td class="list-muted">/{{ cat.slug }}</td><td><span class="list-status" :class="cat.status === 'active' ? 'list-status--lime' : 'list-status--coral'"><i></i>{{ cat.status }}</span></td><td><div class="coverage-value"><strong>{{ cat.product_count ?? 0 }}</strong><span>products</span></div></td><td class="list-muted">{{ cat.sort_order ?? 0 }}</td><td class="list-action-cell"><button @click="openEdit(cat)" class="list-edit-button">Edit <span>→</span></button><button @click="deleteConfirm = cat" class="list-edit-button" style="color:#ef4444;margin-left:8px">Delete</button></td>
          </tr><tr v-if="categories.length === 0 || !categories.some(matchesSearch)"><td colspan="6" class="list-empty">No categories found.</td></tr></tbody></table>
    </div>

    <!-- Delete Confirm Modal -->
    <div v-if="deleteConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Delete Category</h2>
        <p class="text-sm text-gray-600 mb-1">Are you sure you want to delete <strong>{{ deleteConfirm.name }}</strong>?</p>
        <p class="text-xs text-gray-400 mb-4">Categories with products cannot be deleted.</p>
        <div v-if="error" class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 rounded text-sm">{{ error }}</div>
        <div class="flex justify-end gap-3">
          <button @click="deleteConfirm = null; error = ''" class="px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
          <button @click="deleteCategory" :disabled="deleting" class="px-4 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
          {{ editing ? 'Edit Category' : 'New Category' }}
        </h2>

        <div v-if="error" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
          {{ error }}
        </div>

        <form @submit.prevent="saveCategory">
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
          </div>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Image URL</label>
            <input v-model="form.image_url" type="text" placeholder="https://..." class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            <p class="text-xs text-gray-400 mt-1">Optional: external URL for category image</p>
          </div>

          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="form.status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
          </div>

          <div class="flex justify-end gap-3">
            <button type="button" @click="showForm = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
