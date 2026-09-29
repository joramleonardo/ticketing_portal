<script setup>
import { ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import ModalNoRatingTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_NoRating.vue'
import ModalCompleteTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CompleteTicket.vue'
import ModalTicketDetails_Pending from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_InProgress.vue'


const emit = defineEmits(['completed'])   // 👈 add this

const props = defineProps({
  tickets: {
    type: Array,
    default: () => []
  }
})

const tickets = computed(() => props.tickets || [])
const perPage = 5
const currentPage = ref(1)

const total = computed(() => tickets.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage)))

const pageSlice = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return tickets.value.slice(start, start + perPage)
})

const from = computed(() => total.value === 0 ? 0 : (currentPage.value - 1) * perPage + 1)
const to = computed(() => Math.min(currentPage.value * perPage, total.value))

function goTo(p) {
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
}
function goFirst() { goTo(1) }
function goPrev()  { goTo(currentPage.value - 1) }
function goNext()  { goTo(currentPage.value + 1) }
function goLast()  { goTo(totalPages.value) }

// keep current page valid when data changes
watch(() => total.value, () => {
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value
  if (total.value === 0) currentPage.value = 1
})

// show a compact page list: 1 … c-1 c c+1 … N
const pageButtons = computed(() => {
  const t = totalPages.value
  const c = currentPage.value
  if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1)
  const pages = new Set([1, 2, t - 1, t, c - 1, c, c + 1])
  const arr = [...pages].filter(p => p >= 1 && p <= t).sort((a, b) => a - b)
  // insert ellipses
  const out = []
  for (let i = 0; i < arr.length; i++) {
    out.push(arr[i])
    if (i < arr.length - 1 && arr[i + 1] !== arr[i] + 1) out.push('…')
  }
  return out
})

const selected = ref(null)
const showDetails = ref(false)
function openDetails(row){
  selected.value = row
  showDetailsModal_Pending.value = true
  console.log('Opening details for:', row)

}

function refresh() {
  router.reload({ only: ['myInProgTickets'] })
  currentPage.value = 1
}

// ── Complete Ticket ───────────────────────────
const showCompleteModal = ref(false)
const selectedTicket = ref(null)
function openCompleteModal(row) {
    console.log('Opening Complete Ticket modal for:', selectedTicket)
  selectedTicket.value = row
  showCompleteModal.value = true
}
const staffOptions = [
  'Etienne Wayne Amparado',
  'Mc Gyver Basaya',
  'Michael Binondo',
  'John Michael Cagadas',
  'Chester Francisco',
  'Jefferson Jalandoon',
  'Joram Kate Leonardo',
  'Lloyd Mandapat',
  'Jomar Rabanera',
  'Mark Jayson Sison'
]

        function handleCompleteSuccess({ id }) {
          showCompleteModal.value = false
          // tell the page parent to refresh everything (NewTickets + AllMyTickets)
          emit('completed', { id })
        }

const showDetailsModal_Pending = ref(false)


function closeDetailsModal_Pending() { showDetailsModal_Pending.value = false; selected.value = null }

</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <h3 class="font-semibold text-base">
        My In Progress Tickets
        <span class="ml-2 text-xs text-gray-500">({{ total }})</span>
      </h3>
      <button class="text-xs underline" @click="refresh">Refresh</button>
    </div>

    <div class="block w-full overflow-x-auto">
      <table class="items-center w-full bg-transparent border-collapse">
        <thead>
          <tr>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">
              Reference No.
            </th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">
              Email
            </th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">
              Action
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="total === 0">
            <td colspan="3" class="px-6 py-6 text-center text-sm text-gray-500">No in progress tickets.</td>
          </tr>

          <tr v-for="t in pageSlice" :key="t.client_referenceNo" class="hover:bg-gray-50">
            <th class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left">
              {{ t.client_referenceNo }}
            </th>
            <td class="border-t-0 px-6 py-2 text-xs">
              {{ t.client_email || t.client_name || '-' }}
            </td>
            <td class="border-t-0 px-6 py-2 text-xs">
              <div class="flex flex-wrap gap-2">
                <button title="View Details"
                type="button"
                class="p-2 rounded border text-xs hover:bg-blue-50 border-blue-600 text-blue-700"
                @click="openDetails(t)"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        fill="currentColor"
                        class="bi bi-eye-fill"
                        viewBox="0 0 16 16"
                    >
                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                        <path
                        d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5
                            a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"
                        />
                    </svg>
                </button>

                <button title="Mark as Complete"
                type="button"
                class="p-2 rounded border-2 border-green-700 bg-green-700 text-white text-xs hover:bg-green-100 hover:text-green-700"
                @click="openCompleteModal(t)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
  <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
</svg>
                </button>

              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer: range + pagination -->
    <div v-if="total > 0" class="px-4 py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="text-xs text-gray-500">
        Showing <span class="font-semibold">{{ from }}</span>–<span class="font-semibold">{{ to }}</span> of
        <span class="font-semibold">{{ total }}</span>
      </div>

      <div class="flex items-center gap-1">
        <button
          class="px-2 py-1 text-xs border rounded disabled:opacity-50"
          :disabled="currentPage === 1"
          @click="goFirst"
          aria-label="First page"
        >« First</button>

        <button
          class="px-2 py-1 text-xs border rounded disabled:opacity-50"
          :disabled="currentPage === 1"
          @click="goPrev"
          aria-label="Previous page"
        >‹ Prev</button>

        <template v-for="p in pageButtons" :key="`p-${p}`">
          <button
            v-if="p !== '…'"
            class="px-2 py-1 text-xs border rounded"
            :class="p === currentPage ? 'bg-blue-600 text-white' : 'bg-white'"
            @click="goTo(p)"
          >{{ p }}</button>
          <span v-else class="px-2 py-1 text-xs text-gray-400 select-none">…</span>
        </template>

        <button
          class="px-2 py-1 text-xs border rounded disabled:opacity-50"
          :disabled="currentPage === totalPages"
          @click="goNext"
          aria-label="Next page"
        >Next ›</button>

        <button
          class="px-2 py-1 text-xs border rounded disabled:opacity-50"
          :disabled="currentPage === totalPages"
          @click="goLast"
          aria-label="Last page"
        >Last »</button>
      </div>
    </div>

    <ModalTicketDetails_Pending
            :show="showDetailsModal_Pending"
            :ticket="selected"
            :from-my-ticket="true"
            @close="closeDetailsModal_Pending"
        />
  </div>

  <!-- Complete Ticket modal -->
  <ModalCompleteTicket
    :show="showCompleteModal"
    :ticket="selectedTicket"
    :staff-list="staffOptions"
    :subcategories="{
      Hardware: ['Troubleshooting','Evaluation','Network','Testing','Consultation','Assistance','Others'],
      Software: ['Installation','Data Retrieval','Virus/Malwares','Data Conversion/Uploading/Processing','Reformat','Download','Assistance','Others'],
      'Hardware & Software': ['Troubleshooting','Evaluation','Network','Testing','Consultation','Assistance','Installation','Data Retrieval','Virus/Malwares','Data Conversion/Uploading/Processing','Reformat','Download','Assistance','Others'],
      'System Modification': []
    }"
    @close="showCompleteModal = false"
    @success="handleCompleteSuccess"
  />
</template>
