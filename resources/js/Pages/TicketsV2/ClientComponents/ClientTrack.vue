<template>
  <div class="flex-auto px-6 sm:px-10 pb-8 pt-2">
    <!-- Search form -->
    <form @submit.prevent="trackTickets" class="space-y-4">
      <div class="relative w-full">
        <label for="reference_code" class="block text-slate-700 text-xs font-bold uppercase tracking-wider mb-1">
          Reference Number
        </label>
        <span class="block text-xs text-slate-500 mb-2">
          Enter the reference code from your confirmation email (e.g., 20260926-001).
        </span>

        <!-- Reference input + Clear + Track buttons -->
        <div class="flex flex-col sm:flex-row gap-2">
          <div class="relative flex-1">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
              </svg>
            </div>
            <input
              v-model.trim="referenceCode"
              type="text"
              placeholder="e.g., 20260926-001"
              id="reference_code"
              class="w-full pl-10 pr-9 py-2.5 placeholder-slate-400 text-slate-800 border border-slate-300
                     bg-white rounded-xl text-sm font-mono shadow-sm outline-none transition
                     focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500"
            />
            <button
              v-if="referenceCode"
              type="button"
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
              @click="clearRef"
              :disabled="loading"
              title="Clear reference code"
            >
              ✕
            </button>
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white
                   font-bold uppercase tracking-wide text-xs sm:text-sm px-6 py-2.5 rounded-xl shadow-sm
                   hover:shadow transition-all duration-150 disabled:opacity-60 shrink-0"
          >
            <svg
              v-if="loading"
              class="h-4 w-4 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <span>{{ loading ? 'Searching…' : 'Track Ticket' }}</span>
          </button>
        </div>

        <p v-if="errorMessage" class="text-xs text-red-600 mt-2">{{ errorMessage }}</p>

        <!-- Recent Reference Numbers Quick-Chips -->
        <div v-if="recentRefs.length" class="mt-3 flex flex-wrap items-center gap-1.5">
          <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mr-1">
            Recent:
          </span>
          <div
            v-for="refItem in recentRefs"
            :key="refItem"
            class="inline-flex items-center rounded-lg border text-xs font-mono transition"
            :class="referenceCode === refItem
              ? 'bg-sky-50 border-sky-300 text-sky-700 font-semibold'
              : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'"
          >
            <button
              type="button"
              class="px-2.5 py-1 text-left"
              @click="selectRecentRef(refItem)"
            >
              {{ refItem }}
            </button>
            <button
              type="button"
              class="pr-2 pl-0.5 py-1 text-slate-400 hover:text-red-500"
              title="Remove from recent"
              @click.stop="removeRecentRef(refItem)"
            >
              ×
            </button>
          </div>
        </div>
      </div>
    </form>

    <!-- Results -->
    <div class="mt-6" v-if="searched || loading">
      <!-- Loading skeleton -->
      <div v-if="loading" class="space-y-4">
        <div class="h-28 rounded-2xl bg-slate-100 animate-pulse"></div>
        <div class="h-32 rounded-2xl bg-slate-100 animate-pulse"></div>
      </div>

      <template v-else>
        <div v-if="ticketRef" class="space-y-4">
          <!-- Ticket Summary Card -->
          <div class="rounded-2xl border border-slate-200 shadow-sm bg-slate-50/60 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200/80 pb-4">
              <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Reference No.</div>
                <div class="font-mono text-lg font-bold text-slate-900 mt-0.5">
                  {{ ticketRef.client_referenceNo || ticketRef.reference_no || referenceCode }}
                </div>
              </div>

              <div class="flex items-center gap-2">
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wide" :class="statusBadgeClass(ticketRef.ticket_status)">
                  {{ ticketRef.ticket_status || 'N/A' }}
                </span>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 py-4 border-b border-slate-200/80 text-sm">
              <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Email</div>
                <div class="font-semibold text-slate-800 truncate mt-0.5">
                  {{ ticketRef.client_email || ticketRef.client_name || '—' }}
                </div>
              </div>
              <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Tech Assigned</div>
                <div class="font-semibold text-slate-800 mt-0.5">
                  {{ ticketRef.assigned_to_name || ticketRef.tech_assigned || ticketRef.assigned_to || 'Pending Assignment' }}
                </div>
              </div>
            </div>

            <div class="pt-4">
              <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Problem That Needed Support</div>
              <div class="text-sm font-medium text-slate-800 mt-1 whitespace-pre-wrap break-words">
                {{ ticketRef.ticket_problem || '—' }}
              </div>
            </div>
          </div>

          <!-- Horizontal Progress Tracker -->
          <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-5 flex items-center justify-between">
              <div class="text-xs font-bold uppercase tracking-wider text-slate-700">Ticket Progress</div>
              <div class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                {{ currentStepLabel }}
              </div>
            </div>

            <div class="relative">
              <!-- Track -->
              <div class="absolute left-0 right-0 top-3.5 h-1.5 rounded-full bg-slate-100"></div>
              <!-- Progress -->
              <div
                class="absolute left-0 top-3.5 h-1.5 rounded-full bg-emerald-500 transition-all duration-300"
                :style="{ width: progressPercent + '%' }"
              ></div>

              <!-- Steps -->
              <div class="relative flex justify-between">
                <div v-for="s in steps" :key="s.key" class="flex flex-col items-center w-1/4">
                  <div
                    class="grid place-items-center h-7 w-7 rounded-full border-2 bg-white z-10 transition"
                    :class="s.done ? 'border-emerald-500 bg-emerald-50' : 'border-slate-300'"
                  >
                    <svg
                      v-if="s.done"
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 24 24"
                      class="h-4 w-4 text-emerald-600"
                    >
                      <path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
                    </svg>
                    <div v-else class="h-2 w-2 rounded-full bg-slate-300"></div>
                  </div>
                  <div class="mt-2 text-xs font-semibold" :class="s.done ? 'text-emerald-700' : 'text-slate-500'">
                    {{ s.label }}
                  </div>
                  <div class="text-[10px] text-slate-400 text-center mt-0.5">
                    {{ s.date ? formatDate(s.date) : '—' }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-2">
              <button
                @click="openView(ticketRef.client_referenceNo || ticketRef.reference_no)"
                type="button"
                class="inline-flex items-center gap-1.5 bg-sky-600 hover:bg-sky-700 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow-sm transition"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                </svg>
                Open Chat & Details
              </button>

              <button
                @click="openRateTab(ticketRef.client_referenceNo || ticketRef.reference_no)"
                type="button"
                class="inline-flex items-center gap-1.5 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs px-4 py-2.5 rounded-xl shadow-sm transition"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
                Rate Ticket
              </button>
            </div>

            <button
              @click="copyTrackingLink(ticketRef.client_referenceNo || ticketRef.reference_no)"
              type="button"
              class="inline-flex items-center gap-1.5 border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs px-3.5 py-2 rounded-xl transition"
              title="Copy direct tracking link"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd" />
              </svg>
              Copy Link
            </button>
          </div>
        </div>

        <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center text-slate-500 text-sm">
          No ticket found for Reference Code <span class="font-mono font-semibold text-slate-700">{{ referenceCode }}</span>.
        </div>
      </template>
    </div>
  </div>

  <!-- View Details Modal -->
  <Modal_ViewDetails :show="showView" :ticket-ref="selectedRef" @close="closeView" />

  <!-- Rate Ticket Modal -->
  <Modal_RateTicket :show="showRate" :ticket-ref="selectedRef" @close="closeRate" />
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import { useToast } from 'vue-toastification'
import Modal_ViewDetails from '@/Pages/TicketsV2/ClientComponents/ModalAction_ViewDetails.vue'
import Modal_RateTicket from '@/Pages/TicketsV2/ClientComponents/ModalAction_RateTicket.vue'

const props = defineProps({
  initialRef: {
    type: String,
    default: '',
  },
})

const toast = useToast()
const RECENT_REFS_KEY = 'stii_recent_ticket_refs'

// Input
const referenceCode = ref('')
const recentRefs = ref([])

// Results (reference)
const ticketRef = ref(null)

// UX state
const loading = ref(false)
const errorMessage = ref('')
const searched = ref(false)
const showView = ref(false)
const showRate = ref(false)
const selectedRef = ref(null)

function loadRecentRefs() {
  try {
    const raw = localStorage.getItem(RECENT_REFS_KEY)
    const parsed = JSON.parse(raw)
    recentRefs.value = Array.isArray(parsed) ? parsed.slice(0, 5) : []
  } catch {
    recentRefs.value = []
  }
}

function saveRecentRef(refNo) {
  const clean = (refNo || '').trim()
  if (!clean) return
  try {
    const updated = [clean, ...recentRefs.value.filter((r) => r !== clean)].slice(0, 5)
    recentRefs.value = updated
    localStorage.setItem(RECENT_REFS_KEY, JSON.stringify(updated))
  } catch {
    // ignore storage errors
  }
}

function removeRecentRef(refNo) {
  try {
    const updated = recentRefs.value.filter((r) => r !== refNo)
    recentRefs.value = updated
    localStorage.setItem(RECENT_REFS_KEY, JSON.stringify(updated))
  } catch {
    // ignore
  }
}

function selectRecentRef(refNo) {
  referenceCode.value = refNo
  trackTickets()
}

function syncUrlRef(refNo) {
  try {
    const url = new URL(window.location.href)
    if (refNo) {
      url.searchParams.set('ref', refNo)
    } else {
      url.searchParams.delete('ref')
      url.searchParams.delete('track')
    }
    window.history.replaceState({}, '', url.pathname + url.search)
  } catch {
    // ignore
  }
}

async function copyTrackingLink(refNo) {
  const clean = (refNo || referenceCode.value || '').trim()
  if (!clean) return
  const link = `https://ticketingportal.jkleonardo.tech/?ref=${encodeURIComponent(clean)}`
  try {
    await navigator.clipboard.writeText(link)
    toast.success('Tracking link copied!')
  } catch {
    toast.error('Could not copy tracking link.')
  }
}

const openView = (refNo) => { selectedRef.value = refNo; showView.value = true }
const closeView = () => { showView.value = false; selectedRef.value = null }
const closeRate = () => { showRate.value = false; selectedRef.value = null }

const formatDate = (val) => {
  if (!val) return ''
  const d = new Date(val)
  if (Number.isNaN(d.getTime())) return String(val)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const clearRef = () => {
  referenceCode.value = ''
  ticketRef.value = null
  searched.value = false
  errorMessage.value = ''
  syncUrlRef('')
}

// Helper to pull the first available field name from an object
const firstOf = (obj, candidates) => {
  for (const k of candidates) {
    const v = obj?.[k]
    if (v !== undefined && v !== null && v !== '') return v
  }
  return null
}

// Timeline steps (computed from ticketRef)
const steps = computed(() => {
  if (!ticketRef.value) return []
  const t = ticketRef.value

  const created   = firstOf(t, ['created_at',  'createdAt',  'date_created'])
  const assigned  = firstOf(t, ['assigned_at', 'date_assigned', 'assignedAt'])
  const attended  = firstOf(t, ['attended_at', 'date_attended', 'attendedAt'])
  const completed = firstOf(t, ['completed_at','date_completed','completedAt'])

  const items = [
    { key: 'created',   label: 'Created',    date: created },
    { key: 'assigned',  label: 'Assigned',   date: assigned },
    { key: 'attended',  label: 'Attended',   date: attended },
    { key: 'completed', label: 'Completed',  date: completed },
  ]

  const fallbackIdx = statusStepIndex()
  return items.map((x, i) => ({ ...x, done: Boolean(x.date) || i <= fallbackIdx }))
})

// Status badge
function statusBadgeClass(status) {
  return {
    'text-orange-700 bg-orange-100 border border-orange-200': status === 'New',
    'text-red-700 bg-red-100 border border-red-200': status === 'Pending',
    'text-amber-700 bg-amber-100 border border-amber-200': status === 'In Progress',
    'text-emerald-700 bg-emerald-100 border border-emerald-200': status === 'Completed',
    'text-slate-700 bg-slate-200 border border-slate-300': status === 'Closed'
  }
}

async function trackTickets() {
  errorMessage.value = ''
  searched.value = false
  loading.value = true
  ticketRef.value = null

  try {
    if (!referenceCode.value) {
      errorMessage.value = 'Please enter a Reference Code.'
      return
    }
    const cleanRef = referenceCode.value.trim()
    const { data } = await axios.get(`/client/track/ref/${encodeURIComponent(cleanRef)}`)
    ticketRef.value = data?.ticket || data || null
    searched.value = true
    if (ticketRef.value) {
      const resolvedRef = ticketRef.value.client_referenceNo || ticketRef.value.reference_no || cleanRef
      saveRecentRef(resolvedRef)
      syncUrlRef(resolvedRef)
    } else {
      errorMessage.value = 'Reference Code not found.'
    }
  } catch (e) {
    errorMessage.value = 'Reference Code not found or server error.'
    searched.value = true
  } finally {
    loading.value = false
  }
}

const openRateTab = (refNo) => {
  const url = `/client/rate/${encodeURIComponent(refNo)}`
  window.open(url, '_blank', 'noopener')
}

// Progress helpers for the horizontal tracker
const currentStepIndex = computed(() => {
  let idx = statusStepIndex()
  const s = steps.value
  for (let i = 0; i < s.length; i++) {
    if (s[i].done) idx = Math.max(idx, i)
  }
  return idx
})

const currentStepLabel = computed(() =>
  steps.value[currentStepIndex.value]?.label || '—'
)

const progressPercent = computed(() => {
  const totalSteps = steps.value.length
  if (totalSteps <= 1) return 0
  return (currentStepIndex.value / (totalSteps - 1)) * 100
})

function statusStepIndex () {
  const s = (ticketRef.value?.ticket_status || '').toLowerCase()

  if (s.includes('closed') || s.includes('completed')) return 3 // Completed
  if (s.includes('progress')) return 2                         // Attended
  if (s.includes('assign')) return 1                           // Assigned
  return 0
}

onMounted(() => {
  loadRecentRefs()
  const startRef = (props.initialRef || '').trim()
  if (startRef) {
    referenceCode.value = startRef
    trackTickets()
  }
})

watch(
  () => props.initialRef,
  (newRef) => {
    const clean = (newRef || '').trim()
    if (clean && clean !== referenceCode.value) {
      referenceCode.value = clean
      trackTickets()
    }
  }
)
</script>
