
<script setup>
    import { ref, watch, reactive, computed, onMounted, nextTick} from 'vue';
    import { Link, router, usePage } from '@inertiajs/vue3';
    import { useToast } from 'vue-toastification'
    import Modal from '@/Components/Modal.vue' // adjust if needed
    import ModalAssignStaff from '@/Pages/TicketsV2/ModalComponents/ModalAction_AssignStaff.vue'
    import ModalCloseTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CloseTicket.vue'
    import ModalEditTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_EditTicket.vue'

    const toast = useToast()

    const props = defineProps({
        show: { type: Boolean, default: false },
        ticket: { type: Object, default: null }, // expects { client_*, detail: {...} }
        autoFetchLogs: { type: Boolean, default: true },
        fromMyTicket: { type: Boolean, default: false }
    })

    const emit = defineEmits(['close', 'updated'])

    function close() {
        emit('close')
        // optional: clear logs on close to avoid stale flash
        activityLogs.value = []
        isLoadingLogs.value = false
    }

    // ── Check User Logged In Role ─────────────────────────────────────────────────────────────
            const page = usePage()
            const userRole = page.props?.auth?.user?.role || ''
            const isAdmin = userRole === 'Admin'


    // ── Date Helper ─────────────────────────────────────────────────────────────
            function formatDateTime(d) {
                if (!d) return '—'
                const date = new Date(d)
                return date.toLocaleString(undefined, {
                    year: 'numeric', month: 'short', day: '2-digit',
                    hour: '2-digit', minute: '2-digit'
                })
            }

    // ── Division Map Helper ─────────────────────────────────────────────────────
            const DIVISION_MAP = Object.freeze({ 1:'FAD', 2:'IRAD', 3:'CRPD', 4:'OD-MISPS' })

            function mapDivision(value) {
                const v = (value ?? '').toString().trim()
                if (!v) return 'OTHERS'
                const n = Number(v)
                if (Number.isFinite(n) && DIVISION_MAP[n]) return DIVISION_MAP[n]
                const up = v.toUpperCase()
                if (['FAD','IRAD','CRPD','OD-MISPS'].includes(up)) return up
                if (up.includes('OTHER')) return 'OTHERS'
                return 'OTHERS'
            }

    // ── Activity Logs: state + fetch + mapping ──────────────────────────────────
            const isLoadingLogs = ref(false)
            const activityLogs = ref([])

        /** Fetch activity logs by reference code */
            async function fetchActivityLogs(refCode) {
                if (!refCode) return
                isLoadingLogs.value = true
                try {
                    const res = await fetch(`/tickets/logs/${encodeURIComponent(refCode)}`)
                    activityLogs.value = await res.json()
                } catch (err) {
                    console.error('Failed to fetch activity logs', err)
                    activityLogs.value = []
                } finally {
                    isLoadingLogs.value = false
                }
            }

        // Auto-fetch logs when modal opens or ticket changes
            watch(
                () => [props.show, props.ticket?.client_referenceNo],
                async ([showNow, refCode]) => {
                    if (props.autoFetchLogs && showNow && refCode) {
                    await Promise.all([
                        fetchActivityLogs(refCode),
                        fetchAttachments(refCode),
                    ])
                    }
                },
                { immediate: false }
            )

    // ── Assign Ticket Triggers ──────────────────────────────────
            const showAssignModal = ref(false)

            const staffOptions = [
                'Etienne Wayne Amparado',
                'Mc Gyver Basaya',
                'Michael Binondo',
                'John Michael Cagadas',
                'Chester Francisco' ,
                'Jefferson Jalandoon',
                'Joram Kate Leonardo',
                'Lloyd Mandapat',
                'Jomar Rabanera',
                'Mark Jayson Sison'
            ]

            function openAssign() {
                showAssignModal.value = true
            }

            // function handleAssignSuccess({ assignedTo, status }) {
            //     showAssignModal.value = false        // ✅ close child assign modal
            //     emit('close')                        // ✅ close parent ticket details modal
            //     toast.success(`Ticket assigned to ${assignedTo} (${status})`)
            //     // optionally refresh list or patch local state
            // }

        function handleAssignSuccess({ assignedTo, status, id }) {
            showAssignModal.value = false

            // Optimistic UI
            applyTicketPatch({
                ticket_id: id ?? props.ticket?.detail?.ticket_id,
                ticket_assigned_staff: assignedTo,
                ticket_status: status,
            })

            // Tell parent list to refresh
            emit('updated', {
                id: id ?? props.ticket?.detail?.ticket_id,
                assignedTo,
                status,
            })

            // Optional: close the details modal
            emit('close')

            toast.success(`Ticket assigned to ${assignedTo} (${status})`)
        }

    // ── Close Ticket Triggers ──────────────────────

        const showCloseModal = ref(false)
        function openCloseModal() { showCloseModal.value = true }
        function handleCloseSuccess() {
            showCloseModal.value = false
            emit('close')
        }

    // ── Edit Ticket Section ──────────────────────

        const selectedTicket = ref(null);
        const showEditModal = ref(false);
        const editForm = ref({
            id: null,
            status: '',
            assignedStaff: '',
            problem: ''
        });

        function closeEditModal() {
            showEditModal.value = false;
        }

        watch(showEditModal, (newVal) => {
            if (newVal && selectedTicket.value) {
                const status = selectedTicket.value.status || {};
                editForm.value = {
                    id: status.id || null,
                    status: status.status || '',
                    assignedStaff: status.assignedStaff || '',
                    problem: status.problem_that_needed_support || 'No description provided'
                };
                staffSearch.value = status.assignedStaff || '';
            }
        });

        function applyTicketPatch(patch) {
            // 0) Normalize alias (defensive)
            if (patch.ticket_attended_by && !patch.ticket_assigned_staff) {
                patch.ticket_assigned_staff = patch.ticket_attended_by
                delete patch.ticket_attended_by
            }

            // 1) Decide which object to patch (edit modal holds a ref to the same object,
            //    but we handle both explicitly to be safe)
            const target = selectedTicket.value || props.ticket
            if (!target) return

            // 2) Build the next detail object
            const nextDetail = {
                ...(target.detail || {}),
                ...patch,
            }

            // 3) Apply to the selected ticket (used by the edit modal)
            if (selectedTicket.value) {
                selectedTicket.value.detail = nextDetail
            }

            // 4) Also apply to the ticket shown in this details modal (in case they’re not the same reference)
            if (props.ticket && props.ticket !== selectedTicket.value) {
                props.ticket.detail = nextDetail
            }
        }

        const groupedStaff = {
            'Technical Support': [
                'Michael Binondo',
                'Jefferson Jalandoon',
                'Lloyd Mandapat'
            ],
            'Network Administrator': [
                'Mark Jayson Sison'
            ],
            'System Management': [
                'Etienne Wayne Amparado',
                'John Michael Cagadas',
                'Chester Francisco',
                'Joram Kate Leonardo'
            ],
            'STARBOOKS': [
                'Mc Gyver Basaya',
                'Jomar Rabanera'
            ]
        };

        const staffSearch = ref('');
        const showStaffDropdown = ref(false);

        // Optional: close dropdown when clicking outside
        onMounted(() => {
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.relative')) {
                showStaffDropdown.value = false;
                }
            });
        });

        function openEdit(t) {
            selectedTicket.value = t
            showEditModal.value = true
        }

    // ── Status Trigger ──────────────────────────────────
            const status = computed(() => (props.ticket?.detail?.ticket_status || props.ticket?.ticket_status || '').trim())

    // ── Attachment Triggers ──────────────────────────────────
        const loadingAttachments = ref(false)
        const attachments = ref([])

        function formatBytes(bytes) {
            if (!bytes && bytes !== 0) return '—'
            const units = ['B','KB','MB','GB','TB']
            let i = 0, n = Number(bytes)
            while (n >= 1024 && i < units.length - 1) { n /= 1024; i++ }
            return `${n.toFixed(n >= 10 || i === 0 ? 0 : 1)} ${units[i]}`
        }

        async function fetchAttachments(refCode) {
            if (!refCode) {
                attachments.value = [];
                return
            }
            loadingAttachments.value = true
            try {
                const res = await fetch(`/tickets/${encodeURIComponent(refCode)}/attachments`)
                attachments.value = await res.json()
            } catch (e) {
                console.error('Failed to fetch attachments', e)
                attachments.value = []
            } finally {
                loadingAttachments.value = false
            }
        }


</script>

<template>
     <!-- ───────────────────── MODAL - View Details ───────────────────── -->
            <Modal :show="show" @close="close">
                <div class="p-6 w-full max-w-3xl flex flex-col max-h-[80vh]">
                    <!-- Modal Title Section -->
                    <div class="flex items-center justify-between mb-6 border-b pb-2">
                        <h2 class="text-2xl font-bold text-gray-800 uppercase flex items-center gap-2">
                            <!-- SVG Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-file-text-fill text-gray-700" viewBox="0 0 16 16">
                            <path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M5 4h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1m-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5M5 8h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1m0 2h3a.5.5 0 0 1 0 1H5a.5.5 0 0 1 0-1"/>
                            </svg>
                            Ticket Details
                        </h2>

                        <!-- Buttons Section -->
                        <div class="flex gap-2">

                                    <button
                                            @click="openEdit(ticket)"
                                            class="bg-teal-500 text-white font-bold uppercase text-sm px-6 py-3 rounded shadow
                                                    active:bg-teal-600
                                                    hover:shadow-lg outline-none
                                                    focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150"
                                            type="button">
                                    Edit Details
                                    </button>
                            <!-- <button
                                @click="openEdit(ticket)"
                                class="px-4 py-2 rounded shadow-sm border-2 border-solid
                                    border-blue-900 bg-blue-900 text-white
                                    hover:bg-blue-100 hover:text-blue-700 hover:border-blue-700"
                            >
                                Edit
                            </button> -->
                            <!-- <button
                            @click="close"
                            class="px-4 py-2 rounded shadow-sm border-2 border-solid border-gray-700 bg-gray-700 text-white hover:bg-gray-400 hover:text-gray-900 hover:border-gray-900"
                            >
                                Close
                            </button> -->
                        </div>
                    </div>

                    <div v-if="ticket" class="flex-1 overflow-y-auto pr-2 space-y-4">
                        <!-- Status + Actions (aligned in one row) -->
                        <div class="sm:col-span-2">
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded border p-3 bg-gray-50">
                                <!-- Status -->
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-gray-600">Status:</p>
                                    <span
                                        class="inline-block px-2 py-1 text-xs rounded font-medium"
                                        :class="{
                            'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-lightBlue-600 bg-lightBlue-200 last:mr-0 mr-1': ticket.detail?.ticket_status === 'New',
                            'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-pink-600 bg-pink-200 last:mr-0 mr-1': ticket.detail?.ticket_status === 'Pending',
                            'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-amber-600 bg-amber-200 last:mr-0 mr-1': ticket.detail?.ticket_status === 'In Progress',
                            'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-emerald-600 bg-emerald-200 last:mr-0 mr-1': ticket.detail?.ticket_status === 'Completed',
                            'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-indigo-600 bg-indigo-200 last:mr-0 mr-1': ticket.detail?.ticket_status === 'Closed'
                                        }"
                                    >
                                    {{ status || 'N/A' }}
                                    </span>
                                </div>

                                <!-- Actions -->
                                <div v-if="fromMyTicket || ticket.detail?.ticket_status === 'New' && isAdmin"
                                    class="flex items-center gap-2">


                                    <button v-if="ticket.detail?.ticket_status === 'New'"
                                            @click="openAssign"
                                            class="bg-lightBlue-500 text-white font-bold uppercase text-sm px-6 py-3 rounded shadow
                                                    active:bg-lightBlue-600
                                                    hover:shadow-lg outline-none
                                                    focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150"
                                            type="button">
                                    Assign Ticket
                                    </button>
                                    <button v-if="ticket.detail?.ticket_status === 'New'"
                                            @click="openCloseModal"
                                            class="bg-blueGray-500 text-white font-bold uppercase text-sm px-6 py-3 rounded shadow
                                                    active:bg-blueGray-600
                                                    hover:shadow-lg outline-none
                                                    focus:outline-none mr-1 mb-1 ease-linear transition-all duration-150"
                                            type="button">
                                    Close Ticket
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Client Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-gray-500">Reference No.</div>
                                <div class="font-semibold">{{ ticket.client_referenceNo }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500">Email</div>
                                <div class="font-semibold">{{ ticket.client_email || ticket.client_name || '—' }}</div>
                            </div>
                        </div>

                        <hr class="border-gray-200" />

                        <!-- Request Details -->
                        <div class="sm:col-span-2 gap-4">
                            <div>
                                <div class="text-xs text-gray-500">Request Type</div>
                                <div class="font-semibold">{{ ticket.detail?.ticket_type }}</div>
                            </div>

                            <div class="mt-4">
                                <div class="text-xs text-gray-500">Problem</div>
                                <div class="font-semibold">
                                    {{ ticket.detail?.ticket_problem_that_needed_support || '—' }}
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-200" />

                        <!-- Attachments -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500">Attachments</div>
                                <span v-if="loadingAttachments" class="text-xs text-gray-500">Loading…</span>
                            </div>

                            <div v-if="!loadingAttachments && (!attachments || attachments.length === 0)"
                                class="text-sm text-gray-500">
                                No attachments.
                            </div>

                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="att in attachments" :key="att.id"
                                    class="border rounded-md p-2 bg-white shadow-sm flex flex-col">
                                <!-- Preview -->
                                <div class="flex items-center justify-center bg-gray-50 rounded mb-2 min-h-[120px]">
                                    <img v-if="att.is_image"
                                        :src="att.url"
                                        :alt="att.name"
                                        class="max-h-40 object-contain rounded" />
                                    <div v-else class="text-center p-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mx-auto mb-2 text-gray-500" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                                    </svg>
                                    <div class="text-xs text-gray-600 truncate">{{ att.name }}</div>
                                    <div class="text-[10px] text-gray-500">{{ att.mime || 'file' }} • {{ formatBytes(att.size) }}</div>
                                    </div>
                                </div>

                                <!-- Name -->
                                <div class="text-xs text-gray-700 truncate mb-2" :title="att.name">
                                    {{ att.name }}
                                </div>

                                <!-- Actions -->
                                <div class="mt-auto flex gap-2">
                                    <a :href="att.url"
                                    target="_blank"
                                    class="flex-1 text-center text-xs px-3 py-2 rounded border hover:bg-gray-50">
                                    Open
                                    </a>

                                    <!-- secure download via route -->
                                    <a :href="`/tickets/${encodeURIComponent(ticket.client_referenceNo)}/attachments/${att.id}`"
                                    class="flex-1 text-center text-xs px-3 py-2 rounded bg-blue-600 text-white hover:bg-blue-700"
                                    download>
                                    Download
                                    </a>
                                </div>
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-200" />

                        <!-- Dates -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-xs text-gray-500">Date Requested</div>
                                <div class="font-medium text-gray-800">
                                {{ formatDateTime(ticket.detail?.date_requested || ticket.created_at) }}
                                </div>
                            </div>
                        </div>


                    </div>
                </div>
            </Modal>

     <!-- ───────────────────── MODAL - Assign Staff ───────────────────── -->
            <!-- <ModalAssignStaff
                :show="showAssignModal"
                :ticket="ticket"
                :staff-options="staffOptions"
                :support-types="['Technical Support','Livestream','Others']"
                default-status="Pending"
                @close="showAssignModal = false"
                @success="handleAssignSuccess"
            /> -->
            <ModalAssignStaff
                :show="showAssignModal"
                :ticket="ticket"
                :staff-options="staffOptions"
                :support-types="['Technical Support','Livestream','Others']"
                default-status="Pending"
                @close="showAssignModal = false"
                @success="handleAssignSuccess"
            />

     <!-- ───────────────────── MODAL - Close Ticket ───────────────────── -->
            <ModalCloseTicket
                :show="showCloseModal"
                :ticket="ticket"
                @close="showCloseModal = false"
                @success="handleCloseSuccess"
            />

     <!-- ───────────────────── MODAL - Edit Ticket ───────────────────── -->
            <ModalEditTicket
                :show="showEditModal"
                :ticket="selectedTicket"
                :grouped-staff="groupedStaff"
                @close="closeEditModal"
                @updated="applyTicketPatch"
            />
</template>
