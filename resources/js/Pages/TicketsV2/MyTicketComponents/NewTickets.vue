<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import ModalTicketDetails_Pending from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_Pending.vue'

const toast = useToast()

const props = defineProps({
  tickets: { type: Array, default: () => [] }
})
const emit = defineEmits(['attended'])

/** Local copy so we can optimistically update */
const items = ref([])
watch(
  () => props.tickets,
  (val) => { items.value = Array.isArray(val) ? [...val] : [] },
  { immediate: true, deep: true }
)

/** Pagination (5 per page) */
const perPage = 5
const currentPage = ref(1)
const total = computed(() => items.value.length)
const totalPages = computed(() => Math.max(1, Math.ceil(total.value / perPage)))
const pageSlice = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return items.value.slice(start, start + perPage)
})
const from = computed(() => (total.value ? (currentPage.value - 1) * perPage + 1 : 0))
const to = computed(() => Math.min(currentPage.value * perPage, total.value))
function goTo(p){ if (p >= 1 && p <= totalPages.value) currentPage.value = p }
function prev(){ goTo(currentPage.value - 1) }
function next(){ goTo(currentPage.value + 1) }
watch(total, () => {
  if (currentPage.value > totalPages.value) currentPage.value = totalPages.value
  if (total.value === 0) currentPage.value = 1
})

/** Details modal */
const showDetails = ref(false)
const selected = ref(null)
function openDetails(row){
  selected.value = row
  showDetailsModal_Pending.value = true
  console.log('Opening details for:', row)

}

const page = usePage()
const currentUserName = page.props?.auth?.user?.name || null   // or .username / .id as needed


/** Disable the button while attending */
const attendingId = ref(null)

/** Attend Ticket + optimistic removal + partial reload */
async function attendTicketAction(ticketId) {
  if (!ticketId) {
    toast.error('No ticket ID found.')
    return
  }
  if (attendingId.value) return
  attendingId.value = ticketId

  await router.put(
    `/tickets-attend/${ticketId}`,
    {
        ticket_status: 'In Progress',
        ticket_attended_by: currentUserName,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        // 1) Optimistic UI: remove the ticket from local list
        items.value = items.value.filter(t => t.ticket_id !== ticketId)

        // 2) Notify + emit
         toast.success('Ticket attended successfully and now in progress!')
         router.reload({
           only: ['myNewTickets','myInProgTickets','ticketStats'],
          preserveScroll: true,
           preserveState: true,
           onFinish: () => {
             attendingId.value = null
             // 👉 Now tell the parent to refresh the table
             emit('attended', { ticketId })
             // (optional) also fire your global event
             window.dispatchEvent(new CustomEvent('tickets:attended', { detail: { ticketId } }))
           }
         })
      },
      onError: () => {
        attendingId.value = null
        toast.error('Failed to attend ticket.')
      },
    }
  )
}


const showDetailsModal_Pending = ref(false)


function closeDetailsModal_Pending() { showDetailsModal_Pending.value = false; selected.value = null }

function onAttended() {

}

onMounted(() => window.addEventListener('tickets:attended', onAttended))
onBeforeUnmount(() => window.removeEventListener('tickets:attended', onAttended))

</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <h3 class="font-semibold text-base text-blueGray-700">
        My New Tickets <span class="ml-2 text-xs text-gray-500">({{ total }})</span>
      </h3>
    </div>

    <div class="block w-full overflow-x-auto">
      <table class="items-center w-full bg-transparent border-collapse">
        <thead>
          <tr>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 py-3 text-xs uppercase font-semibold text-left">Reference No.</th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 py-3 text-xs uppercase font-semibold text-left">Email</th>
            <th class="px-6 bg-blueGray-50 text-blueGray-500 py-3 text-xs uppercase font-semibold text-left">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="total === 0">
            <td colspan="3" class="px-6 py-6 text-center text-sm text-gray-500">No new tickets assigned to you.</td>
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
                <button title="Attend Ticket"
                type="button"
                class="p-2 rounded border-2 border-yellow-500 bg-yellow-500 text-white text-xs hover:bg-yellow-100 hover:text-yellow-600 hover:border-yellow-600 disabled:opacity-60"
                :disabled="attendingId === t.ticket_id"
                @click="attendTicketAction(t.ticket_id)"
                >
                    <svg
                        v-if="attendingId !== t.ticket_id"
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        fill="currentColor"
                        class="bi bi-person-check-fill"
                        viewBox="0 0 16 16"
                    >
                        <path
                        fill-rule="evenodd"
                        d="M15.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708
                            0l-1.5-1.5a.5.5 0 0 1
                            .708-.708L12.5 7.793l2.646-2.647a.5.5
                            0 0 1 .708 0"
                        />
                        <path
                        d="M1 14s-1 0-1-1 1-4 6-4
                            6 3 6 4-1 1-1 1zm5-6a3
                            3 0 1 0 0-6 3 3 0 0 0
                            0 6"
                        />
                    </svg>

                    <!-- Spinner while attending -->
                    <svg
                        v-else
                        class="animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        width="16"
                        height="16"
                    >
                        <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                        />
                        <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                        />
                    </svg>
                </button>

              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer: pagination -->
    <div v-if="total > 0" class="px-4 py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div class="text-xs text-gray-500">
        Showing <span class="font-semibold">{{ from }}</span>–<span class="font-semibold">{{ to }}</span> of
        <span class="font-semibold">{{ total }}</span>
      </div>

      <div class="flex items-center gap-1">
        <button class="px-2 py-1 text-xs border rounded disabled:opacity-50" :disabled="currentPage === 1" @click="prev">Prev</button>
        <button
          v-for="p in totalPages"
          :key="p"
          class="px-2 py-1 text-xs border rounded"
          :class="p === currentPage ? 'bg-blue-600 text-white' : 'bg-white'"
          @click="goTo(p)"
        >
          {{ p }}
        </button>
        <button class="px-2 py-1 text-xs border rounded disabled:opacity-50" :disabled="currentPage === totalPages" @click="next">Next</button>
      </div>
    </div>

    <!-- Details modal -->
    <!-- <ModalTicketDetails_Pending v-model:open="showDetails" :ticket="selected" /> -->

        <ModalTicketDetails_Pending
            :show="showDetailsModal_Pending"
            :ticket="selected"
            :from-my-ticket="true"
            @close="closeDetailsModal_Pending"
        />
  </div>
</template>
