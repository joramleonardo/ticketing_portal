
<script setup>
    import { ref, watch, computed } from 'vue'
    import Modal from '@/Components/Modal.vue' // adjust if needed

    const props = defineProps({
        show: { type: Boolean, default: false },
        ticket: { type: Object, default: null }, // expects { client_*, detail: {...} }
        autoFetchLogs: { type: Boolean, default: true },
    })
    const emit = defineEmits(['close'])

    function close() {
        emit('close')
        // optional: clear logs on close to avoid stale flash
        activityLogs.value = []
        isLoadingLogs.value = false
    }

    // ── Helpers (kept local so component stays portable) ─────────────
    function formatDate(d) {
        if (!d) return '—'
        const date = new Date(d)
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
    }
    function formatDateTime(d) {
        if (!d) return '—'
        const date = new Date(d)
        return date.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: '2-digit',
            hour: '2-digit', minute: '2-digit'
        })
    }

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

    function statusClasses(s) {
        return {
            'bg-blue-200 text-blue-800': s === 'New',
            'bg-red-200 text-red-800': s === 'Pending',
            'bg-yellow-200 text-yellow-800': s === 'In Progress',
            'bg-green-200 text-green-800': s === 'Completed',
        }
    }
    function getTicketId() {
        return props.ticket?.detail?.ticket_id ?? null
    }

    // ── Activity Logs: state + fetch + mapping ──────────────────────
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

    /** Map logs for display: if Ticket Created, show client email as actor */
    const displayLogs = computed(() => {
        const clientEmail = props.ticket?.client_email || props.ticket?.client_name || 'Unknown Client'
        return (activityLogs.value || []).map(log => {
            const name = (log?.activity_name || '').trim()
            const user = (name === 'Ticket Created')
            ? clientEmail
            : (log?.activity_user || 'Unknown User')
            return {
            ...log,
            _display_user: user,
            _display_date: log?.activity_date ? formatDateTime(log.activity_date) : '—',
            }
        })
    })

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
                    <!-- <button
                    @click="showEditModal = true"
                    class="px-4 py-2 rounded shadow-sm border-2 border-solid border-blue-900 bg-blue-900 text-white hover:bg-blue-100 hover:text-blue-700 hover:border-blue-700"
                    >
                        Edit
                    </button> -->
                    <button
                    @click="close"
                    class="px-4 py-2 rounded shadow-sm border-2 border-solid border-gray-700 bg-gray-700 text-white hover:bg-gray-400 hover:text-gray-900 hover:border-gray-900"
                    >
                        Close
                    </button>
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
                        <div class="flex items-center gap-2">
                        <button
                            v-if="ticket.detail?.ticket_status === 'Pending'"
                            type="button"
                            class="w-full sm:w-auto px-4 py-2 rounded shadow-sm border-2 border-yellow-500 bg-yellow-500 text-white hover:bg-yellow-100 hover:text-yellow-600 hover:border-yellow-600"
                            @click="attendTicketAction"
                        >
                            Attend Ticket
                        </button>

                        <button
                            v-if="ticket.detail?.ticket_status === 'In Progress'"
                            type="button"
                            class="w-full sm:w-auto px-4 py-2 rounded shadow-sm border-2 border-green-700 bg-green-700 text-white hover:bg-green-100 hover:text-green-700 hover:border-green-700"
                            @click="openCompleteModal"
                        >
                            Mark as Complete
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

                <!-- Request Completed Details -->
                <div class="sm:col-span-2 gap-4">
                    <div class="mt-4">
                        <div class="text-xs text-gray-500">Remarks</div>
                        <div class="font-semibold">
                            {{ ticket.detail?.ticket_remarks || '—' }}
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
                    <div>
                        <div class="text-xs text-gray-500">Date Closed</div>
                        <div class="font-medium text-gray-800">
                        {{ formatDateTime(ticket.detail?.date_completed) }}
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200" />

                <!-- Activity Logs -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-gray-800">Activity Logs</h3>
                        <button
                        v-if="ticket?.client_referenceNo"
                        class="text-xs px-2 py-1 border rounded hover:bg-gray-50"
                        @click="fetchActivityLogs(ticket.client_referenceNo)"
                        :disabled="isLoadingLogs"
                        >
                        {{ isLoadingLogs ? 'Refreshing…' : 'Refresh' }}
                        </button>
                    </div>

                    <div v-if="isLoadingLogs" class="text-sm text-gray-500">Loading logs…</div>

                    <ul v-else-if="displayLogs.length" class="divide-y divide-gray-200">
                        <li v-for="(log, idx) in displayLogs" :key="idx" class="py-2">
                        <p class="text-sm">
                            <strong>{{ log.activity_name }}</strong>
                            <span class="mx-1">by</span>
                            <span class="italic">{{ log._display_user }}</span>
                            <span class="text-gray-500 text-xs"> ({{ log._display_date }})</span>
                        </p>
                        <p v-if="log.activity_details" class="text-xs text-gray-600 mt-1">
                            {{ log.activity_details }}
                        </p>
                        </li>
                    </ul>

                    <div v-else class="text-sm text-gray-500">No activity logs found.</div>
                </div>
            </div>
        </div>
    </Modal>
</template>
