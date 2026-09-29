<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import ModalNoRatingTicketDetails from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_NoRating.vue'

const props = defineProps({
  pendingTickets: {
    type: Array,
    default: () => []
  }
})

const perPage = 5
const currentPage = ref(1)

const tickets = computed(() => props.pendingTickets || [])

const totalPages = computed(() => Math.max(1, Math.ceil(tickets.value.length / perPage)))
const pageSlice = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return tickets.value.slice(start, start + perPage)
})

function goTo(p) {
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
}

const selected = ref(null)
const showDetails = ref(false)

function openDetails(ticket) {
    selected.value = ticket
    showDetails.value = true
}


function refresh() {
  router.reload({ only: ['pendingTickets'] })
  currentPage.value = 1
}

</script>

<template>
  <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <h3 class="font-semibold text-base">
        No Ratings Yet
        <span class="ml-2 text-xs text-gray-500">({{ tickets.length }})</span>
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
          <tr v-if="tickets.length === 0">
            <td colspan="3" class="px-6 py-6 text-center text-sm text-gray-500">No pending tickets for rating.</td>
          </tr>

          <tr v-for="t in pageSlice" :key="t.client_referenceNo" class="hover:bg-gray-50">
            <th class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4 text-left">
              {{ t.client_referenceNo }}
            </th>
            <td class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4">
              {{ t.client_email || t.client_name || '-' }}
            </td>
            <td class="border-t-0 px-6 align-middle border-l-0 border-r-0 text-xs whitespace-nowrap p-4">
              <button
                type="button"
                class="px-3 py-1.5 rounded-lg border text-xs hover:bg-blue-50 border-blue-600 text-blue-700"
                @click="openDetails(t)"
              >
                View Details
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination (shows only if more than 5) -->
    <div v-if="tickets.length > perPage" class="px-4 py-3 flex items-center justify-between">
        <div class="text-xs text-gray-500">
            Page {{ currentPage }} of {{ totalPages }}
        </div>
        <div class="flex gap-1">
            <button
            class="px-2 py-1 text-xs border rounded disabled:opacity-50"
            :disabled="currentPage === 1"
            @click="goTo(currentPage - 1)"
            >Prev</button>

            <button
            v-for="p in totalPages"
            :key="p"
            class="px-2 py-1 text-xs border rounded"
            :class="p === currentPage ? 'bg-blue-600 text-white' : 'bg-white'"
            @click="goTo(p)"
            >{{ p }}</button>

            <button
            class="px-2 py-1 text-xs border rounded disabled:opacity-50"
            :disabled="currentPage === totalPages"
            @click="goTo(currentPage + 1)"
            >Next</button>
        </div>
    </div>

    <ModalNoRatingTicketDetails v-model:open="showDetails" :ticket="selected" />
  </div>
</template>
