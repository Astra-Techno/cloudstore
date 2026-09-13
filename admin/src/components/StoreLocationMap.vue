<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps<{ latitude: number | null; longitude: number | null }>()
const emit = defineEmits<{ (e: 'update:latitude', value: number): void; (e: 'update:longitude', value: number): void }>()

const mapElement = ref<HTMLElement | null>(null)
const error = ref('')
const search = ref('')
const searching = ref(false)
const results = ref<Array<{ display_name: string; lat: string; lon: string }>>([])
const mapplsKey = (import.meta.env.VITE_MAPPLS_STATIC_KEY as string | undefined)?.trim()
const usingMappls = ref(false)
let map: any
let marker: any
let resizeObserver: ResizeObserver | undefined

function setPin(lat: number, lng: number, center = true) {
  emit('update:latitude', Number(lat.toFixed(6)))
  emit('update:longitude', Number(lng.toFixed(6)))
  if (!map) return
  const position = { lat, lng }
  if (usingMappls.value) {
    marker?.setPosition?.(position)
    if (center) map?.setCenter?.(position)
  } else {
    marker?.setLatLng([lat, lng])
    if (center) map.panTo(position)
  }
}

function useCurrentLocation() {
  if (!navigator.geolocation) { error.value = 'This browser does not support location access.'; return }
  navigator.geolocation.getCurrentPosition(
    (position) => setPin(position.coords.latitude, position.coords.longitude),
    () => { error.value = 'Location access was denied or unavailable.' },
    { enableHighAccuracy: true, timeout: 15000 },
  )
}

async function searchAddress() {
  const query = search.value.trim()
  if (query.length < 3) { error.value = 'Enter at least 3 characters to search.'; return }
  searching.value = true
  error.value = ''
  results.value = []
  try {
    const response = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('Address search is temporarily unavailable.')
    results.value = await response.json()
    if (!results.value.length) error.value = 'No matching address found. Try a more specific landmark or town.'
  } catch (e) { error.value = e instanceof Error ? e.message : 'Address search failed.' }
  finally { searching.value = false }
}

function chooseResult(result: { display_name: string; lat: string; lon: string }) {
  setPin(Number(result.lat), Number(result.lon))
  search.value = result.display_name
  results.value = []
}

async function loadMap() {
  try {
    const initial = props.latitude != null && props.longitude != null ? { lat: props.latitude, lng: props.longitude } : { lat: 20.5937, lng: 78.9629 }
    if (mapplsKey) {
      await new Promise<void>((resolve, reject) => {
        if ((window as any).mappls) return resolve()
        const script = document.createElement('script')
        script.src = `https://sdk.mappls.com/map/sdk/web?v=3.0&access_token=${encodeURIComponent(mapplsKey)}`
        script.async = true; script.onload = () => resolve(); script.onerror = () => reject(new Error('Mappls could not load. Check the key whitelist.'))
        document.head.appendChild(script)
      })
      await nextTick()
      const mappls = (window as any).mappls
      map = new mappls.Map(mapElement.value, { center: initial, zoom: props.latitude != null ? 16 : 5 })
      marker = new mappls.Marker({ map, position: initial, draggable: true })
      usingMappls.value = true
      return
    }
    if (!(window as any).L) {
      await new Promise<void>((resolve, reject) => {
        if (!document.querySelector('link[data-leaflet]')) {
          const stylesheet = document.createElement('link')
          stylesheet.rel = 'stylesheet'
          stylesheet.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
          stylesheet.dataset.leaflet = 'true'
          document.head.appendChild(stylesheet)
        }
        const script = document.createElement('script')
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
        script.async = true
        script.onload = () => resolve()
        script.onerror = () => reject(new Error('Map picker could not load. Check the internet connection.'))
        document.head.appendChild(script)
      })
    }
    await nextTick()
    const L = (window as any).L
    map = L.map(mapElement.value).setView([initial.lat, initial.lng], props.latitude != null ? 16 : 5)
    L.tileLayer(import.meta.env.VITE_MAP_TILE_URL || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>', maxZoom: 19 }).addTo(map)
    marker = L.marker([initial.lat, initial.lng], { draggable: true }).addTo(map)
    map.on('click', (event: any) => setPin(event.latlng.lat, event.latlng.lng, false))
    marker.on('dragend', (event: any) => { const point = marker.getLatLng(); setPin(point.lat, point.lng, false) })
    // This component is inside a tab that is initially hidden. Leaflet reads a
    // zero-size container in that state unless it is invalidated when visible.
    resizeObserver = new ResizeObserver(() => map?.invalidateSize({ animate: false }))
    resizeObserver.observe(mapElement.value!)
    requestAnimationFrame(() => map.invalidateSize({ animate: false }))
  } catch (e) { error.value = e instanceof Error ? e.message : 'Map picker could not load.' }
}

onMounted(loadMap)
onBeforeUnmount(() => { resizeObserver?.disconnect(); map?.remove() })
watch(() => [props.latitude, props.longitude], ([lat, lng]) => {
  if (map && lat != null && lng != null) {
    if (usingMappls.value) { marker?.setPosition?.({ lat, lng }); map?.setCenter?.({ lat, lng }); return }
    marker?.setLatLng([lat, lng])
    map.panTo([lat, lng])
  }
})
</script>

<template>
  <div class="md:col-span-2">
    <form class="mb-3 flex gap-2" @submit.prevent="searchAddress">
      <input v-model="search" type="search" class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Search shop address, area, or landmark" aria-label="Search shop address" />
      <button type="submit" :disabled="searching" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">{{ searching ? 'Searching…' : 'Search' }}</button>
    </form>
    <div v-if="results.length" class="mb-3 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
      <button v-for="result in results" :key="`${result.lat}-${result.lon}`" type="button" class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm hover:bg-gray-50 last:border-0" @click="chooseResult(result)">{{ result.display_name }}</button>
    </div>
    <div ref="mapElement" class="h-72 w-full rounded-xl border border-gray-200 overflow-hidden" />
    <div class="mt-2 flex items-center gap-3"><button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="useCurrentLocation">Use my current location</button><p v-if="!error" class="text-xs text-gray-500">Click the map or drag the pin to set your shop location.</p></div>
    <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
  </div>
</template>
