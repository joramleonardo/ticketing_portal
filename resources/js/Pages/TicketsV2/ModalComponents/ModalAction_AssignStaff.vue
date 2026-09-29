<!-- resources/js/Pages/TicketsV2/MyTicketComponents/Modal_AssignStaff.vue -->
<script setup>
    import { ref, computed, watch } from 'vue'
    import { router, usePage  } from '@inertiajs/vue3'
    import Modal from '@/Components/Modal.vue' // adjust path if needed

    const props = defineProps({
    show: { type: Boolean, default: false },
    ticket: { type: Object, default: null }, // expects ticket.detail.ticket_id
    staffOptions: { type: Array, default: () => [] },     // e.g. ['John Doe','Jane Smith',...]
    defaultStatus: { type: String, default: 'Pending' },  // status after assignment
    supportTypes: {                                       // your Request Type options
        type: Array,
        default: () => ['Technical Support', 'Livestream', 'Others'],
    },
    })

    // current logged-in user from Inertia page props
    const page = usePage()
    const currentUserName = computed(() =>
        page?.props?.auth?.user?.name
        || page?.props?.auth?.user?.username
        || ''
    )
    const currentUserId = computed(() => page?.props?.auth?.user?.id ?? null)


    const emit = defineEmits(['close', 'success'])

    // form state (mirrors your previous code)
    const staffSearch = ref('')
    const selectedStaff = ref('')
    const selectedSupportType = ref('')
    const showStaffDropdown = ref(false)
    const submitting = ref(false)
    const errorMsg = ref('')

    // computed: filtered staff list based on search
    const filteredStaff = computed(() => {
    const q = (staffSearch.value || '').toLowerCase().trim()
    if (!q) return props.staffOptions
    return props.staffOptions.filter(s => (s || '').toLowerCase().includes(q))
    })

    function selectStaff(name) {
    selectedStaff.value = name
    staffSearch.value = name
    showStaffDropdown.value = false
    }

    function resetForm() {
    staffSearch.value = ''
    selectedStaff.value = ''
    selectedSupportType.value = ''
    showStaffDropdown.value = false
    errorMsg.value = ''
    }

    function close() {
    resetForm()
    emit('close')
    }

    // initialize when opening
    watch(
    () => [props.show, props.ticket?.detail?.ticket_id],
    () => {
        if (!props.show || !props.ticket) return
        // prefill support type from ticket if you want
        selectedSupportType.value =
        props.ticket?.detail?.ticket_type ||
        props.ticket?.detail?.ticket_support_type ||
        ''
        // clear staff field so user explicitly chooses
        selectedStaff.value = ''
        staffSearch.value = ''
        errorMsg.value = ''
    },
    { immediate: false }
    )

    async function assignStaff() {
        const ticketId = props.ticket?.detail?.ticket_id
        const staffName =
            (selectedStaff.value && selectedStaff.value.trim()) ||
            (staffSearch.value && staffSearch.value.trim()) ||
            ''

        if (!ticketId || !staffName || !selectedSupportType.value) {
            errorMsg.value = 'Please select both staff and request type.'
            return
        }

        const payload = {
            ticket_type: selectedSupportType.value,
            ticket_assigned_staff: staffName,
            ticket_status: props.defaultStatus, // e.g. "Pending"
            // NEW: who performed the assignment
            ticket_assigned_by: currentUserName.value,

        }

        submitting.value = true
        try {
            await router.put(`/tickets/${ticketId}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                emit('success', {
                    id: ticketId,
                    assignedTo: staffName,
                    assignedBy: currentUserName.value,
                    status: props.defaultStatus
                })
                close()
            },
            })
        } finally {
            submitting.value = false
        }
    }



</script>

<template>
    <Modal :show="show" @close="close">
        <div class="p-6 w-full sm:max-w-lg mx-auto flex flex-col max-h-[80vh]">
            <!-- Header -->
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">Assign Staff</h2>
                <button @click="close" class="text-gray-500 hover:text-gray-700">✕</button>
            </div>

            <!-- Scrollable body -->
            <div class="flex-1 overflow-y-auto pr-1">
                <!-- Error -->
                <div v-if="errorMsg" class="mb-3 text-sm text-red-600">
                {{ errorMsg }}
                </div>

                <!-- Ticket ref (read-only) -->
                <div class="mb-3 text-sm text-gray-700">
                <span class="font-semibold">Reference No:</span>
                <span class="ml-1">{{ ticket?.client_referenceNo || '—' }}</span>
                </div>

                <!-- Staff Dropdown (searchable) -->
                <div class="relative mb-4">
                <label class="block font-semibold mb-1 text-gray-700">Assign to</label>
                <input
                    type="text"
                    v-model="staffSearch"
                    placeholder="Search staff..."
                    class="w-full border rounded px-3 py-2 text-sm focus:ring focus:ring-blue-200"
                    @focus="showStaffDropdown = true"
                    @input="showStaffDropdown = true"
                />
                <ul
                    v-if="showStaffDropdown && filteredStaff.length"
                    class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded shadow max-h-40 overflow-y-auto text-sm"
                >
                    <li
                    v-for="(staff, index) in filteredStaff"
                    :key="index"
                    @click="selectStaff(staff)"
                    class="px-4 py-2 hover:bg-blue-100 cursor-pointer"
                    >
                    {{ staff }}
                    </li>
                </ul>
                </div>

                <!-- Request Type -->
                <div class="mb-4">
                <label class="block font-semibold mb-1 text-gray-700">Request Type</label>
                <select v-model="selectedSupportType" class="w-full border rounded px-3 py-2 text-sm">
                    <option disabled value="">Select request type</option>
                    <option v-for="opt in supportTypes" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-4 flex justify-end gap-2">
                <button @click="close" class="px-4 py-2 rounded border text-sm hover:bg-gray-50">Cancel</button>
                <button
                type="button"
                class="px-4 py-2 rounded shadow-sm border-2 border-blue-900 bg-blue-900 text-white text-sm hover:bg-blue-100 hover:text-blue-700 hover:border-blue-700 disabled:opacity-60"
                :disabled="submitting"
                @click="assignStaff"
                >
                {{ submitting ? 'Assigning…' : `Assign & set ${defaultStatus}` }}
                </button>
            </div>
        </div>
    </Modal>
</template>
