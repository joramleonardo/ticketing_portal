<script setup>
    import { ref, watch, reactive, computed, onMounted, nextTick} from 'vue';
    import { Link, router, usePage } from '@inertiajs/vue3';
    import Modal from "@/Components/Modal.vue";
    import NewTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CreateNewTicket.vue';
    import LateTicket from '@/Pages/TicketsV2/ModalComponents/ModalAction_CreateLateTicket.vue';
    import ModalTicketDetails_New from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_New.vue'
    import ModalTicketDetails_Pending from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_Pending.vue'
    import ModalTicketDetails_InProgress from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_InProgress.vue'
    import ModalTicketDetails_Completed from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_Completed.vue'
    import ModalTicketDetails_Closed from '@/Pages/TicketsV2/ModalComponents/ModalTicketDetails_Closed.vue'
    import DeleteTicketButton from '@/Pages/TicketsV2/ModalComponents/ModalAction_DeleteTicket.vue'

    // ── Reads the logged in user's role ──────────────────────────────────────────────────────
    const page = usePage()
    const isAdmin = computed(() => page.props?.auth?.user?.role === 'Admin')

    const props = defineProps({
        tickets: Object, // ← the paginator returned by the controller
        filters: Object, // ← from controller
    })
    const tickets = ref(Array.isArray(props.tickets?.data) ? props.tickets.data : [])
    const isLoading = ref(false)

    // ── Fetch ticket ──────────────────────────────────────────────────────
            onMounted(() => {
                fetchTickets()
            })
            const fetchTickets = (target = 1) => {
                isLoading.value = true
                const url = typeof target === 'string' ? target : buildUrl(target)
                router.visit(url, {
                    method: 'get',
                    preserveState: true,
                    preserveScroll: true,
                    only: ['tickets', 'filters'],
                    onSuccess: (pageObj) => {
                        const p = pageObj.props.tickets || {}
                        tickets.value = Array.isArray(p.data) ? p.data : []
                        pagination.value = {
                            current_page: p.current_page ?? 1,
                            last_page: p.last_page ?? 1,
                            total: p.total ?? 0,
                            prev_page_url: p.prev_page_url ?? null,
                            next_page_url: p.next_page_url ?? null,
                            links: Array.isArray(p.links) ? p.links : [],
                        }
                        // keep filters in sync if backend normalizes them
                        if (pageObj.props.filters) {
                            searchText.value = pageObj.props.filters.search ?? searchText.value
                            statusFilter.value = pageObj.props.filters.status ?? statusFilter.value
                            perPage.value = pageObj.props.filters.perPage ?? perPage.value
                            divisionFilter.value = pageObj.props.filters.division ?? divisionFilter.value
                        }
                        isLoading.value = false
                    },
                    onError: () => { isLoading.value = false }
                })
            }

    // ── Pagination ──────────────────────────────────────────────────────
            const pagination = ref({
                current_page: props.tickets?.current_page ?? 1,
                last_page: props.tickets?.last_page ?? 1,
                total: props.tickets?.total ?? 0,
                prev_page_url: props.tickets?.prev_page_url ?? null,
                next_page_url: props.tickets?.next_page_url ?? null,
                links: Array.isArray(props.tickets?.links) ? props.tickets.links : [], // <- important
            })
            const perPage = ref(
                Number(new URLSearchParams(window.location.search).get('perPage')) || (props.tickets?.per_page ?? 10)
            )
            const searchText = ref((
                new URLSearchParams(location.search).get('search')) ?? (props.filters?.search ?? ''))
            const statusFilter = ref((
                new URLSearchParams(location.search).get('status')) ?? (props.filters?.status ?? ''))
            const buildUrl = (page = 1) => {
                const params = new URLSearchParams()
                // core
                params.set('page', page)
                if (perPage.value) params.set('perPage', perPage.value)
                // search
                const s = (searchText.value || '').trim()
                if (s) params.set('search', s)
                // status
                const st = (statusFilter.value || '').trim()
                if (st) params.set('status', st)
                // division ('' means All, so only send when chosen)
                const dv = (divisionFilter.value ?? '').toString().trim()  // e.g. "1","2","3","4","OTHERS"
                if (dv) params.set('division', dv)
                return `/admin/tickets-v2?${params.toString()}`
            }
            const pageFrom = computed(() => {
                const p = pagination.value
                if (!p?.total) return 0
                return (p.current_page - 1) * (p.per_page ?? perPage.value) + 1
            })
            const pageTo = computed(() => {
                const p = pagination.value
                if (!p?.total) return 0
                const end = p.current_page * (p.per_page ?? perPage.value)
                return end > p.total ? p.total : end
            })

    // ── Search & Filter Section ──────────────────────────────────────────────────────
            let searchTimer
            const onSearchInput = () => {
                clearTimeout(searchTimer)
                const s = (searchText.value || '').trim()
                if (s.length > 0 && s.length < 2) return   // avoid hammering server for 1-char
                searchTimer = setTimeout(() => fetchTickets(1), 550)
            }
            const clearFilters = () => {
                searchText.value = ''
                statusFilter.value = ''
                divisionFilter.value = ''
                fetchTickets(1)
            }

    // ── Division helpers ──────────────────────────────────────────────────────
            const divisionFilter = ref('') // '' = All divisions
            const DIVISION_OPTIONS = [
                { value: '', label: 'All' },
                { value: '1', label: 'FAD' },
                { value: '2', label: 'IRAD' },
                { value: '3', label: 'CRPD' },
                { value: '4', label: 'OD-MISPS' },
                { value: 'OTHERS', label: 'OTHERS' },
            ]

    const showStaffDropdown = ref(false);

    // Optional: close dropdown when clicking outside
    onMounted(() => {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.relative')) {
            showStaffDropdown.value = false;
            }
        });
    });

    const DIVISION_MAP = Object.freeze({
        1: 'FAD',
        2: 'IRAD',
        3: 'CRPD',
        4: 'OD-MISPS',
    })

    function mapDivision(value) {
        const v = (value ?? '').toString().trim()
        if (!v) return 'OTHERS'
        const n = Number(v)
        if (Number.isFinite(n) && DIVISION_MAP[n]) return DIVISION_MAP[n]
        const up = v.toUpperCase()
        if (['FAD', 'IRAD', 'CRPD', 'OD-MISPS'].includes(up)) return up
        if (up.includes('OTHER')) return 'OTHERS'
        return 'OTHERS'
    }

    // ── Delete Ticket Section ──────────────────────────────────────────────────────
            function onTicketDeleted({ id }) {
                const idx = tickets.value.findIndex(r =>
                    (r?.client_id ?? r?.id ?? r?.detail?.ticket_id ?? null) === id
                )
                if (idx !== -1) tickets.value.splice(idx, 1)
            }

    // ── View Details Modal Section──────────────────────────────────────────────────────
            const selectedRow = ref(null)
            const showDetailsModal_New= ref(false)
            const showDetailsModal_Pending= ref(false)
            const showDetailsModal_InProgress = ref(false)
            const showDetailsModal_Completed = ref(false)
            const showDetailsModal_Closed = ref(false)
            function openModal(row) {
                selectedRow.value = row
                if (row.detail?.ticket_status === "New") {
                    showDetailsModal_New.value = true
                } else if (row.detail?.ticket_status === "Pending"){
                    showDetailsModal_Pending.value = true
                } else if (row.detail?.ticket_status === "In Progress"){
                    showDetailsModal_InProgress.value = true
                } else if (row.detail?.ticket_status === "Completed"){
                    showDetailsModal_Completed.value = true
                }else if (row.detail?.ticket_status === "Closed"){
                    showDetailsModal_Closed.value = true
                }
            }
            function closeDetailsModal_New() {
                showDetailsModal_New.value = false
                selectedRow.value = null
            }
            function closeDetailsModal_Pending() {
                showDetailsModal_Pending.value = false
                selectedRow.value = null
            }
            function closeDetailsModal_InProgress() {
                showDetailsModal_InProgress.value = false
                selectedRow.value = null
            }
            function closeDetailsModal_Completed() {
                showDetailsModal_Completed.value = false
                selectedRow.value = null
            }
            function closeDetailsModal_Closed() {
                showDetailsModal_Closed.value = false
                selectedRow.value = null
            }

    // ── Create New & Late Ticket Modal Section──────────────────────────────────────────────────────
            const showLateModal = ref(false)
            const showNewModal = ref(false)
            function closeLateModal() {
                showLateModal.value = false
            }
            function closeNewModal() {
                showNewModal.value = false
            }
            function onTicketCreated() {
                fetchTickets(1)
            }

    function onTicketUpdated(/* payload: { id, assignedTo, status } */) {
        fetchTickets(pagination.value.current_page ?? 1)
    }
</script>

<template>
    <div class="relative flex flex-col min-w-0 break-words bg-white w-full mb-6 shadow-lg rounded">
        <!-- Top header section -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <h3 class="font-semibold text-base text-blueGray-700">
                All Tickets
            </h3>
            <div class="flex items-center gap-2">
                <!-- Create Ticket -->
                <button @click="showNewModal = true"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-900 border border-blue-900 rounded shadow-lg hover:shadow-xl hover:bg-blue-100 hover:text-blue-900 hover:border-blue-700 transition duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Ticket
                </button>

                <!-- Late Ticket -->
                <button @click="showLateModal = true"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-red-700 border border-red-700 rounded shadow-lg hover:shadow-xl hover:bg-red-100 hover:text-red-700 hover:border-red-600 transition duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Late Ticket
                </button>
                <!-- Refresh table -->
                <button
                @click="fetchTickets"
                class="inline-flex items-center px-4 py-2 text-sm font-medium
                        text-white bg-cyan-500 border border-cyan-500 rounded shadow-lg
                        hover:shadow-xl hover:bg-cyan-100 hover:text-cyan-500 hover:border-cyan-700 transition duration-200"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2z"/>
                        <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466"/>
                    </svg>
                    Refresh Table
                </button>
            </div>
        </div>

        <div class="block w-full overflow-x-auto">
            <!-- Filters Bar -->
            <div class="mb-4 bg-white border border-gray-200 p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <!-- Search (wide) -->
                    <div class="md:col-span-7">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div class="relative">
                            <input type="text"
                                v-model="searchText" @input="onSearchInput"
                                placeholder="Ref No., Email, Ticket ID, Type, Problem…"
                                class="w-full h-9 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-3 text-sm"
                            />
                            <button
                                v-if="searchText" @click="clearFilters"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-500 hover:text-gray-700"
                                title="Clear search"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    <!-- Status (compact) -->
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select
                        v-model="statusFilter"
                        @change="fetchTickets(1)"
                        class="w-full h-9 rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 px-2 text-sm"
                        >
                            <option value="">All</option>
                            <option>New</option>
                            <option>Pending</option>
                            <option>In Progress</option>
                            <option>Completed</option>
                        </select>
                    </div>
                    <!-- Actions (right-aligned, same height as selects) -->
                    <div class="md:col-span-2 flex items-end justify-start md:justify-end gap-2">
                        <button
                        @click="fetchTickets(1)"
                        class="h-9 inline-flex items-center px-4 text-sm font-medium text-white bg-blue-900 border border-blue-900 rounded shadow-sm hover:shadow hover:bg-blue-100 hover:text-blue-900 hover:border-blue-700 transition"
                        >
                            Apply
                        </button>
                        <button
                        @click="clearFilters"
                        class="h-9 inline-flex items-center px-4 text-sm font-medium text-black bg-white border border-blue-900 rounded shadow-sm hover:shadow hover:bg-blue-100 hover:text-blue-900 hover:border-blue-700 transition"
                        >
                            Reset
                        </button>
                    </div>
                </div>
                <!-- Active filters chips -->
                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    <span v-if="searchText" class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                        Search: <strong class="font-medium">{{ searchText }}</strong>
                        <button class="ml-1 text-xs hover:text-red-600" @click="searchText=''; fetchTickets(1)">✕</button>
                    </span>
                    <span v-if="statusFilter" class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-gray-100 text-gray-700 border border-gray-200">
                        Status: <strong class="font-medium">{{ statusFilter }}</strong>
                        <button class="ml-1 text-xs hover:text-red-600" @click="statusFilter=''; fetchTickets(1)">✕</button>
                    </span>
                </div>
            </div>

            <table class="items-center w-full bg-transparent border-collapse">
                <thead>
                    <tr>
                        <th class="px-4 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">
                            #
                        </th>
                        <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">
                            Ticket Details
                        </th>
                        <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">
                            Status
                        </th>
                        <th class="px-6 bg-blueGray-50 text-blueGray-500 align-middle border border-solid border-blueGray-100 py-3 text-xs uppercase border-l-0 border-r-0 whitespace-nowrap font-semibold text-left">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ticket, index) in tickets" :key="ticket.id">
                        <td class="border-t-0 px-4 py-4 text-sm text-gray-700 font-bold">
                            {{ index + 1 }}
                        </td>
                        <td class="border-t-0 px-6 py-4 text-sm text-gray-700">
                            <div>
                                Ref. No. <span class="text-red-600 font-bold">{{ ticket.client_referenceNo }}</span>
                            </div>
                            <div class="text-gray-500">
                                <span class="text-gray-700 font-bold">{{ ticket.client_email || ticket.client_name || '—' }}</span>
                            </div>
                        </td>
                        <td class="border-t-0 px-6 py-4">
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
                                {{ ticket.detail?.ticket_status || 'N/A' }}
                            </span>
                        </td>
                        <td class="border-t-0 px-6 py-4">
                            <div class="flex items-center gap-2">
                                <!-- View -->
                                <button
                                    @click="openModal(ticket)"
                                    class="p-2 rounded-full bg-blue-900 text-white hover:bg-blue-100 hover:text-blue-700"
                                    title="View Details"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye-fill" viewBox="0 0 16 16">
                                        <path d="M10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0"/>
                                        <path d="M0 8s3-5.5 8-5.5S16 8 16 8s-3 5.5-8 5.5S0 8 0 8m8 3.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7"/>
                                    </svg>
                                </button>
                                <DeleteTicketButton
                                    :ticket="ticket"
                                    :is-admin="isAdmin"
                                    @deleted="onTicketDeleted"/>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-6">
                <div class="w-full bg-white border border-gray-200 p-4">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <!-- A) Rows per page -->
                        <div class="flex items-center gap-3">
                            <label class="text-sm text-gray-600">Rows per page</label>
                            <select v-model.number="perPage"
                                @change="fetchTickets(1)"
                                class="text-sm rounded-lg border-gray-300 focus:ring-2 focus:ring-blue-900 focus:border-blue-900 px-3 py-2"
                            >
                                <option :value="5">5</option>
                                <option :value="10">10</option>
                                <option :value="20">20</option>
                                <option :value="50">50</option>
                            </select>
                        </div>
                        <!-- B) Page info -->
                        <div class="text-sm text-gray-600">
                            <span class="font-medium text-gray-700">Page {{ pagination.current_page }}</span>
                            of {{ pagination.last_page }}
                            <span v-if="pagination.total" class="ml-2 text-gray-900">
                            • Showing <span class="font-medium text-gray-700">{{ pageFrom }}</span>–<span class="font-medium text-gray-700">{{ pageTo }}</span>
                            of <span class="font-medium text-gray-700">{{ pagination.total }}</span>
                            </span>
                        </div>
                        <!-- C) Controls -->
                        <div class="flex items-center gap-1" v-if="pagination?.links?.length">
                            <!-- Prev -->
                            <button type="button"
                            class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border text-sm
                                    transition disabled:opacity-40 disabled:cursor-not-allowed
                                    hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-900"
                            :disabled="!pagination.prev_page_url"
                            @click="pagination.prev_page_url && fetchTickets(pagination.prev_page_url)"
                            aria-label="Previous page"
                            >
                            <!-- chevron-left -->
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M12.707 15.707a1 1 0 01-1.414 0l-5-5a1 1 0 010-1.414l5-5a1 1 0 111.414 1.414L8.414 10l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                            </svg>
                            <span class="hidden sm:inline">Prev</span>
                            </button>
                            <!-- Numbered links -->
                            <button type="button"
                            v-for="(link, i) in pagination.links" :key="i"
                            class="min-w-[2.25rem] px-3 py-2 rounded-lg border text-sm
                                    transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-900"
                            :class="{
                                'bg-blue-900 text-white border-blue-600 hover:bg-blue-900': !!link?.active,
                                'text-gray-700 border-gray-200': !link?.active,
                                'opacity-40 cursor-not-allowed': !link?.url
                            }"
                            :disabled="!link?.url"
                            @click="link?.url && fetchTickets(link.url)"
                            v-html="link?.label ?? ''"
                            ></button>
                            <!-- Next -->
                            <button type="button"
                            class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border text-sm
                                    transition disabled:opacity-40 disabled:cursor-not-allowed
                                    hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-900"
                            :disabled="!pagination.next_page_url"
                            @click="pagination.next_page_url && fetchTickets(pagination.next_page_url)"
                            aria-label="Next page"
                            >
                            <span class="hidden sm:inline">Next</span>
                            <!-- chevron-right -->
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 11-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                            </button>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ───────────────────── MODAL - Create New & Late Ticket ───────────────────── -->
            <Modal :show="showLateModal" @close="closeLateModal" maxWidth="2xl">
                <LateTicket @success="closeLateModal" />
            </Modal>
            <Modal :show="showNewModal" @close="closeNewModal" maxWidth="2xl">
                <NewTicket
                    @success="closeNewModal"
                    @created="onTicketCreated"
                    @close="closeNewModal"
                />
            </Modal>
    <!-- ───────────────────── MODAL - View Details ───────────────────── -->
            <!-- <ModalTicketDetails_New
                :show="showDetailsModal_New"
                :ticket="selectedRow"
                :from-my-ticket="false"
                @close="closeDetailsModal_New"
            /> -->
            <ModalTicketDetails_New
            :show="showDetailsModal_New"
            :ticket="selectedRow"
            @close="closeDetailsModal_New"
            @updated="onTicketUpdated"
            />
            <ModalTicketDetails_Pending
                :show="showDetailsModal_Pending"
                :ticket="selectedRow"
                :from-my-ticket="false"
                @close="closeDetailsModal_Pending"
            />
            <ModalTicketDetails_InProgress
                :show="showDetailsModal_InProgress"
                :ticket="selectedRow"
                :from-my-ticket="false"
                @close="closeDetailsModal_InProgress"
            />
            <ModalTicketDetails_Completed
                :show="showDetailsModal_Completed"
                :ticket="selectedRow"
                :from-my-ticket="false"
                @close="closeDetailsModal_Completed"
            />
            <ModalTicketDetails_Closed
                :show="showDetailsModal_Closed"
                :ticket="selectedRow"
                :from-my-ticket="false"
                @close="closeDetailsModal_Closed"
            />
</template>
