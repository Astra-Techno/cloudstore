<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'

const props = defineProps<{ latitude: number | null; longitude: number | null }>()
const emit = defineEmits<{ (e: 'update:latitude', value: number): void; (e: 'update:longitude', value: number): void }>()

const mapElement = ref<HTMLElement | null>(null)
const error = ref('')
let map: any
let marker: any

function setPin(lat: number, lng: number, center = true) {
  emit('update:latitude', Number(lat.toFixed(6)))
  emit('update:longitude', Number(lng.toFixed(6)))
  if (!map) return
  const position = { lat, lng }
  marker?.setLatLng([lat, lng])
  if (center) map.panTo(position)
}

function useCurrentLocation() {
  if (!navigator.geolocation) { error.value = 'This browser does not support location access.'; return }
  navigator.geolocation.getCurrentPosition(
    (position) => setPin(position.coords.latitude, position.coords.longitude),
    () => { error.value = 'Location access was denied or unavailable.' },
    { enableHighAccuracy: true, timeout: 15000 },
  )
}

async function loadMap() {
  try {
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
    const initial = props.latitude != null && props.longitude != null ? { lat: props.latitude, lng: props.longitude } : { lat: 20.5937, lng: 78.9629 }
    map = L.map(mapElement.value).setView([initial.lat, initial.lng], props.latitude != null ? 16 : 5)
    L.tileLayer(import.meta.env.VITE_MAP_TILE_URL || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>', maxZoom: 19 }).addTo(map)
    marker = L.marker([initial.lat, initial.lng], { draggable: true }).addTo(map)
    map.on('click', (event: any) => setPin(event.latlng.lat, event.latlng.lng, false))
    marker.on('dragend', (event: any) => { const point = marker.getLatLng(); setPin(point.lat, point.lng, false) })
  } catch (e) { error.value = e instanceof Error ? e.message : 'Map picker could not load.' }
}

onMounted(loadMap)
watch(() => [props.latitude, props.longitude], ([lat, lng]) => {
  if (map && lat != null && lng != null) {
    marker?.setLatLng([lat, lng])
    map.panTo([lat, lng])
  }
})
</script>

<template>
  <div class="md:col-span-2">
    <div ref="mapElement" class="h-72 w-full rounded-xl border border-gray-200 overflow-hidden" />
    <div class="mt-2 flex items-center gap-3"><button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" @click="useCurrentLocation">Use my current location</button><p v-if="!error" class="text-xs text-gray-500">Click the map or drag the pin to set your shop location.</p></div>
    <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
  </div>
</template>
