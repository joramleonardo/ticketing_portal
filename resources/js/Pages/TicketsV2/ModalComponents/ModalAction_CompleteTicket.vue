<script setup>
    import { ref, reactive, computed, watch } from 'vue'
    import { router } from '@inertiajs/vue3'
    import { useToast } from 'vue-toastification'
    import Modal from '@/Components/Modal.vue'

    const toast = useToast()

    // Props — provide staffList & subcategories from parent (with safe defaults)
    const props = defineProps({
        show: { type: Boolean, default: false },
        ticket: { type: Object, default: null }, // expects ticket.detail.ticket_id
        staffList: { type: Array, default: () => [] },
        subcategories: {
            type: Object,
            default: () => ({
            Hardware: ['PC', 'Printer', 'Peripherals', 'Others'],
            Software: ['Install', 'Configure', 'Troubleshoot', 'Others'],
            'Hardware & Software': ['Driver', 'Firmware', 'Compatibility', 'Others'],
            'System Modification': [], // hidden in UI anyway
            }),
        },
        supportTypes: {
            type: Array,
            default: () => ['Technical Support', 'Livestream'],
        },
    })

    const emit = defineEmits(['close', 'success'])

    // Form state
    const completionForm = reactive({
        supportType: 'Technical Support',
        nature: '',
        subCategory: '',
        assistedBy: [],
        problem: '',
        actionTaken: '',
        remarks: '',
    })

    const submitting = ref(false)
    const errorMsg = ref('')

    // Prefill when opening / ticket changes
    watch(

        () => [props.show, props.ticket?.detail?.ticket_id],
        () => {
            if (!props.show || !props.ticket) return
            const d = props.ticket.detail ?? {}
            completionForm.supportType = props.ticket.ticket_type || props.ticket.detail?.ticket_type
            completionForm.nature      = ''
            completionForm.subCategory = ''
            completionForm.assistedBy  = []
            completionForm.problem     = props.ticket.ticket_problem_that_needed_support || props.ticket.detail?.ticket_problem_that_needed_support || ''
            completionForm.actionTaken = ''
            completionForm.remarks     = ''
            errorMsg.value = ''
        },
        { immediate: false }
    )

    // Basic validation
    const canSubmit = computed(() => {
        if (!completionForm.supportType || !completionForm.nature) return false
        if (completionForm.nature !== 'System Modification' && !completionForm.subCategory) return false
        if (!completionForm.actionTaken?.trim()) return false
        return true
    })

    function closeModal() {
        emit('close')
    }

    async function submitCompletion() {
        const ticketId =
            props.ticket?.detail?.ticket_id ??
            props.ticket?.ticket_id ??
            props.ticket?.id ??
            props.ticket?.detail?.id
        if (!ticketId) {
            errorMsg.value = 'No ticket ID found.'
            return
        }
        if (!canSubmit.value) {
            errorMsg.value = 'Please complete the required fields.'
            return
        }

        const payload = {
            ticket_status: 'Completed', // <- required by your controller
            ticket_type: completionForm.supportType,
            ticket_nature_of_support: completionForm.nature,
            ticket_support_type: completionForm.subCategory,
            ticket_assisted_by: completionForm.assistedBy.join(', '), // store multiple assistants as comma-separated string
            ticket_problem_that_needed_support: completionForm.problem,
            ticket_actions_taken: completionForm.actionTaken,
            ticket_remarks: completionForm.remarks,
        }

        submitting.value = true
        errorMsg.value = ''
        try {
            await router.put(`/tickets-complete/${ticketId}`, payload, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Ticket completed successfully!')
                    emit('success', { id: ticketId })
                    closeModal()
                },
                onError: () => {
                    errorMsg.value = 'Completion failed. Please try again.'
                    toast.error('Completion failed!')
                },
            })
        } finally {
            submitting.value = false
        }
    }
</script>

<template>
  <Modal :show="show" @close="closeModal">
    <div class="p-6 w-full sm:max-w-lg mx-auto flex flex-col max-h-[80vh]">
      <h2 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2">Complete Ticket</h2>

      <!-- Scrollable body -->
      <div class="flex-1 overflow-y-auto space-y-4">
        <div v-if="errorMsg" class="text-sm text-red-600">{{ errorMsg }}</div>

        <!-- 1. Request Type -->
        <div>
            <label class="block font-semibold mb-1">Request Type</label>
            <select v-model="completionForm.supportType" class="w-full border rounded px-3 py-2 text-sm">
                <option v-for="opt in supportTypes" :key="opt" :value="opt">{{ opt }}</option>
            </select>
        </div>

        <!-- 2. Nature of Support -->
        <div>
          <label class="block font-semibold mb-1">Nature of Support</label>
          <select v-model="completionForm.nature" class="w-full border rounded px-3 py-2 text-sm">
            <option disabled value="">Select</option>
            <option>Hardware</option>
            <option>Software</option>
            <option>Hardware & Software</option>
            <option>System Modification</option>
          </select>
        </div>

        <!-- 3. Subcategory (conditional) -->
        <div v-if="completionForm.nature !== 'System Modification'">
            <label class="block font-semibold mb-1">Support Type</label>
            <select v-model="completionForm.subCategory" class="w-full border rounded px-3 py-2 text-sm">
                    <option disabled value="">Select</option>
                    <option
                        v-for="sub in subcategories[completionForm.nature] || []"
                        :key="sub"
                        :value="sub"
                        >
                        {{ sub }}
                    </option>
            </select>
        </div>

        <!-- 4. Assisted By (checkbox multi-select) -->
        <div>
          <label class="block font-semibold mb-1">Assisted By</label>
          <div class="grid grid-cols-2 gap-2 text-sm">
            <label v-for="staff in staffList" :key="staff" class="flex items-center gap-2">
              <input type="checkbox" :value="staff" v-model="completionForm.assistedBy" />
              {{ staff }}
            </label>
          </div>
        </div>

        <!-- 5. Problem -->
        <div>
          <label class="block font-semibold mb-1">Problem that needed support</label>
          <textarea v-model="completionForm.problem" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        </div>

        <!-- 6. Action Taken -->
        <div>
          <label class="block font-semibold mb-1">Describe Action Taken <span class="text-red-600">*</span></label>
          <textarea v-model="completionForm.actionTaken" rows="3" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        </div>

        <!-- 7. Remarks -->
        <div>
          <label class="block font-semibold mb-1">Remarks</label>
          <textarea v-model="completionForm.remarks" rows="2" class="w-full border rounded px-3 py-2 text-sm"></textarea>
        </div>
      </div>

      <!-- Footer -->
      <div class="mt-4 flex justify-end gap-2">
        <button @click="closeModal" class="px-4 py-2 rounded border text-sm hover:bg-gray-50">
          Cancel
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded bg-green-700 text-white text-sm hover:bg-green-800 disabled:opacity-60"
          :disabled="submitting || !canSubmit"
          @click="submitCompletion"
        >
          {{ submitting ? 'Completing…' : 'Confirm Completion' }}
        </button>
      </div>
    </div>
  </Modal>
</template>
