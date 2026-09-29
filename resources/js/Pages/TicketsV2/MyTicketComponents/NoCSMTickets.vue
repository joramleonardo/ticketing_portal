<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  // Completed tickets NOT found in the CSM sheet
  tickets: { type: Array, default: () => [] },
})

/* ── Pagination ───────────────────────────────────────────── */
const perPage = 5
const currentPage = ref(1)

/* ── Filters (month/year) ─────────────────────────────────── */
const now = new Date()
const month = ref(String(now.getMonth() + 1)) // e.g. "9"
const year  = ref(String(now.getFullYear()))   // e.g. "2025"


const monthNames = [
  'January','February','March','April','May','June',
  'July','August','September','October','November','December'
]

// Reset page when list or filters change
watch([() => props.tickets, month, year], () => { currentPage.value = 1 })

/* ── Helpers ──────────────────────────────────────────────── */
function parseYMDParts(val) {
  if (!val) return null

  // Fast path if it's "YYYY-MM-DD..."
  if (typeof val === 'string') {
    const m = val.match(/^(\d{4})-(\d{2})-(\d{2})/)
    if (m) return { y: Number(m[1]), m: Number(m[2]), d: Number(m[3]) }
  }

  const d = new Date(val)
  if (Number.isNaN(d.getTime())) return null
  return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate() }
}

const rateUrl = (refNo) => `/client/rate/${encodeURIComponent(refNo)}`

/* ── Base + filtered lists ────────────────────────────────── */
const base = computed(() => props.tickets || [])

// Build Year options dynamically from data (descending)
const yearOptions = computed(() => {
  const set = new Set()
  for (const t of base.value) {
    const p = parseYMDParts(t?.date_completed)
    if (p) set.add(p.y)
  }
  return Array.from(set).sort((a,b) => b - a)
})

const filtered = computed(() => {
  const m = month.value === 'all' ? null : Number(month.value)
  const y = year.value  === 'all' ? null : Number(year.value)

  if (!m && !y) return base.value

  return base.value.filter(t => {
    const p = parseYMDParts(t?.date_completed)
    if (!p) return false // no date -> cannot match month/year filter
    if (y && p.y !== y) return false
    if (m && p.m !== m) return false
    return true
  })
})

/* ── Paging derived from filtered list ────────────────────── */
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const startIndex = computed(() => (currentPage.value - 1) * perPage)
const pageSlice  = computed(() => filtered.value.slice(startIndex.value, startIndex.value + perPage))

function goTo(p) {
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
}

/* ── Compact pager items (with ellipses) ──────────────────── */
const pages = computed(() => {
  const total = totalPages.value
  const cur = currentPage.value
  const items = []
  const add = (p) => items.push({ type: 'page', value: p })
  const gap = () => items.push({ type: 'gap' })

  if (total <= 7) {
    for (let p = 1; p <= total; p++) add(p)
  } else {
    add(1)
    if (cur > 4) gap()
    const start = Math.max(2, cur - 1)
    const end   = Math.min(total - 1, cur + 1)
    for (let p = start; p <= end; p++) add(p)
    if (cur < total - 3) gap()
    add(total)
  }
  return items
})

/* ── Refresh (server reload) ──────────────────────────────── */
function refresh() {
  router.reload({
    only: ['noCsmTickets'],
    preserveScroll: true,
    // If you want to bypass the 10-min cache, uncomment:
    // data: { force_csm: 1 },
  })
  currentPage.value = 1
}
</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <h3 class="font-semibold text-base">
        No CSM Yet
        <span class="ml-2 text-xs text-gray-500">({{ filtered.length }})</span>
      </h3>

      <!-- Filters + Refresh -->
      <div class="flex items-center gap-2">
        <!-- Month -->
        <label class="text-[11px] text-gray-500">Month</label>
        <select v-model="month" class="text-xs border rounded px-2 py-1 bg-white">
          <option value="all">All</option>
          <option v-for="(name, idx) in monthNames" :key="idx" :value="String(idx + 1)">
            {{ name }}
          </option>
        </select>

        <!-- Year -->
        <label class="text-[11px] text-gray-500">Year</label>
        <select v-model="year" class="text-xs border rounded px-2 py-1 bg-white">
          <option value="all">All</option>
          <option v-for="y in yearOptions" :key="y" :value="String(y)">{{ y }}</option>
        </select>

        <!-- <button class="text-xs underline" @click="refresh">Refresh</button> -->
      </div>
    </div>

    <div class="block w-full overflow-x-auto">
      <table class="items-center w-full bg-transparent border-collapse">
        <thead>
          <tr>
            <th
              class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100
                     py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left"
            >
              Reference No.
            </th>
            <th
              class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100
                     py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left"
            >
              Email
            </th>
            <th
              class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100
                     py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left"
            >
              Action
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="3" class="px-6 py-6 text-center text-sm text-gray-500">
              No tickets pending CSM submission.
            </td>
          </tr>

          <tr v-for="t in pageSlice" :key="t.client_referenceNo" class="hover:bg-gray-50">
            <th class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left">
              {{ t.client_referenceNo }}
            </th>
            <td class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4">
              {{ t.client_email || t.client_name || '-' }}
            </td>
            <td class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4">
              <a
                :href="rateUrl(t.client_referenceNo)"
                target="_blank"
                class="px-3 py-1.5 rounded-lg border text-xs hover:bg-indigo-50 border-indigo-600 text-indigo-700"
                title="Open Rate/CSM page in a new tab"
              >
                Rate / CSM
              </a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="filtered.length > perPage" class="px-4 py-3 border-t bg-gray-50">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="text-xs text-gray-500">
          Page {{ currentPage }} of {{ totalPages }}
        </div>

        <div class="flex items-center gap-1 overflow-x-auto max-w-full whitespace-nowrap">
          <button
            class="px-2 py-1 text-xs border rounded disabled:opacity-50"
            :disabled="currentPage === 1"
            @click="goTo(currentPage - 1)"
          >Prev</button>

          <template v-for="(it, idx) in pages" :key="idx">
            <button
              v-if="it.type === 'page'"
              class="px-2 py-1 text-xs border rounded"
              :class="it.value === currentPage ? 'bg-blue-600 text-white' : 'bg-white'"
              @click="goTo(it.value)"
            >{{ it.value }}</button>
            <span v-else class="px-2 text-xs text-gray-500 select-none">…</span>
          </template>

          <button
            class="px-2 py-1 text-xs border rounded disabled:opacity-50"
            :disabled="currentPage === totalPages"
            @click="goTo(currentPage + 1)"
          >Next</button>
        </div>
      </div>
    </div>
  </div>
</template>
