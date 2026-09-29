<!-- resources/js/Pages/TicketsV2/ModalComponents/ModalAction_CloseTicket.vue -->
<script setup>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import { useToast } from 'vue-toastification'
const toast = useToast()

const props = defineProps({
  show: { type: Boolean, default: false },
  ticket: { type: Object, default: null },   // expects ticket.detail.ticket_id
})
const emit = defineEmits(['close', 'success'])

const remarks = ref('')
const submitting = ref(false)
const errorMsg = ref('')

watch(
  () => [props.show, props.ticket?.detail?.ticket_id],
  () => {
    if (!props.show) return
    remarks.value = ''
    errorMsg.value = ''
  }
)

function close() {
  emit('close')
}

async function submitClose() {
  const ticketId = props.ticket?.detail?.ticket_id
  if (!ticketId) {
    errorMsg.value = 'No ticket ID found.'
    return
  }
  if (!remarks.value.trim()) {
    errorMsg.value = 'Remarks are required.'
    return
  }

  submitting.value = true
  errorMsg.value = ''
  try {
    await router.put(`/tickets-close/${ticketId}`, {
      ticket_status: 'Closed',
      ticket_remarks: remarks.value.trim(),
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Ticket closed successfully!')
        emit('success', { id: ticketId })
        close()
      },
      onError: () => {
        errorMsg.value = 'Failed to close ticket.'
        toast.error('Failed to close ticket.')
      }
    })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
    <Modal :show="show" @close="close">
        <div class="p-6 w-full sm:max-w-md mx-auto flex flex-col max-h-[70vh]">
            <h2 class="text-lg font-semibold mb-3">Close Ticket</h2>

            <div class="flex-1 overflow-y-auto space-y-3">
                <div v-if="errorMsg" class="text-sm text-red-600">{{ errorMsg }}</div>

                <div class="text-sm text-gray-700">
                    <div><span class="font-semibold">Reference No:</span> {{ ticket?.client_referenceNo || '—' }}</div>
                    <div><span class="font-semibold">Current Status:</span> {{ ticket?.detail?.ticket_status || '—' }}</div>
                </div>

                <div>
                    <label class="block font-semibold mb-1">Remarks
                        <span class="text-xs text-gray-500 font-normal ml-1"> (Reason / context for closing… ) </span>
                        <span class="text-red-600">*</span>

                    </label>
                    <textarea
                        v-model="remarks"
                        rows="4"
                        class="w-full border rounded px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button @click="close" class="px-4 py-2 rounded border text-sm hover:bg-gray-50">Cancel</button>
                <button
                type="button"
                class="px-4 py-2 rounded bg-gray-700 text-white text-sm hover:bg-gray-800 disabled:opacity-60"
                :disabled="submitting || !remarks.trim()"
                @click="submitClose"
                >
                {{ submitting ? 'Closing…' : 'Close Ticket' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
