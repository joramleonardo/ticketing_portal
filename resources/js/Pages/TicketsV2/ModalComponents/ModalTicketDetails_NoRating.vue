<script setup>
        import { ref, watch, computed } from 'vue'
        import Modal from '@/Components/Modal.vue'
        import { useToast } from 'vue-toastification'

        const toast = useToast()

        const props = defineProps({
            open: { type: Boolean, default: false },
            ticket: { type: Object, default: null }
        })
        const emit = defineEmits(['update:open'])

        const t = computed(() => props.ticket || {})

        function close() {
            emit('update:open', false)
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
            await fetchActivityLogs(refCode)
            }
        },
        { immediate: false }
    )


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


    // ── Copy Rating Link Action ──────────────────────

    function ratingHref(refNo) {
        return `https://ticketingportal.jkleonardo.tech/client/rate/${encodeURIComponent(refNo)}`
    }

        async function copyRatingLink(refNo) {
        if (!refNo) return

        const link = ratingHref(refNo)

    try {
        await navigator.clipboard.writeText(link)
        console.log('Copied:', link)
        toast.success('Rating link copied!') // optional feedback
    } catch (err) {
        console.error('Failed to copy rating link', err)
        toast.error('Could not copy rating link')
    }
    }
</script>

<template>



    <Modal :show="open" @close="close">
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
                    @click="close"
                    class="px-4 py-2 rounded shadow-sm border-2 border-solid border-gray-700 bg-gray-700 text-white hover:bg-gray-400 hover:text-gray-900 hover:border-gray-900"
                    >
                        Close
                    </button>
                </div>
            </div>
            <div v-if="!t.client_referenceNo" class="mt-6 text-sm text-gray-500">
                No ticket selected.
            </div>
            <div v-else class="flex-1 overflow-y-auto pr-2 space-y-4">
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
                                {{ t.ticket_status || 'N/A' }}
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="w-full sm:w-auto px-4 py-2 rounded shadow-sm border-2 border-yellow-500 bg-yellow-500 text-white hover:bg-yellow-100 hover:text-yellow-600 hover:border-yellow-600"
                                @click="copyRatingLink(t.client_referenceNo)"
                            >
                                Copy Rating Link
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Client Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Reference No.</div>
                        <div class="font-semibold">{{ t.client_referenceNo }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Email</div>
                        <div class="font-semibold">{{ t.client_email || t.client_name || '—' }}</div>
                    </div>
                </div>

                <hr class="border-gray-200" />

                <!-- Request Details -->
                <div class="sm:col-span-2 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Request Type</div>
                        <div class="font-semibold">{{ t.ticket_type }}</div>
                    </div>

                    <div class="mt-4">
                        <div class="text-xs text-gray-500">Problem</div>
                        <div class="font-semibold">
                            {{ t.ticket_problem_that_needed_support || '—' }}
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="text-xs text-gray-500">Actions Taken</div>
                        <div class="font-semibold">
                            {{ t.ticket_actions_taken || '—' }}
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200" />

                <!-- Dates -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="text-xs text-gray-500">Date Requested</div>
                        <div class="font-medium text-gray-800">
                        {{ formatDateTime(t.date_requested )|| t.created_at }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Date Completed</div>
                        <div class="font-medium text-gray-800">
                        {{ formatDateTime(t.date_completed) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
