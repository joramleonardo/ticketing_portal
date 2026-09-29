<script setup>
import { ref, onMounted } from "vue"
import ClientCreate from "@/Pages/TicketsV2/ClientComponents/ClientCreate.vue"
import ClientTrack from "@/Pages/TicketsV2/ClientComponents/ClientTrack.vue"

const activeView = ref("create")
const initialTrackRef = ref("")

function switchTab(tab) {
    activeView.value = tab
    if (tab === "create") {
        // Clear ?ref query param when switching back to create
        const url = new URL(window.location.href)
        if (url.searchParams.has("ref") || url.searchParams.has("track")) {
            url.searchParams.delete("ref")
            url.searchParams.delete("track")
            window.history.replaceState({}, "", url.pathname + url.search)
        }
    }
}

function handleTrackRef(refNo) {
    if (!refNo) return
    initialTrackRef.value = refNo
    activeView.value = "track"
    const url = new URL(window.location.href)
    url.searchParams.set("ref", refNo)
    window.history.replaceState({}, "", url.pathname + url.search)
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search)
    const refParam = (params.get("ref") || params.get("track") || "").trim()
    if (refParam) {
        initialTrackRef.value = refParam
        activeView.value = "track"
    }
})
</script>

<template>
    <div class="w-full max-w-2xl mx-auto">
        <div
            class="relative flex flex-col min-w-0 break-words w-full shadow-xl rounded-2xl bg-white border border-slate-200 overflow-hidden"
        >
            <!-- Top decorative gradient bar -->
            <div class="h-1.5 w-full bg-gradient-to-r from-sky-500 via-blue-600 to-indigo-600"></div>

            <!-- Card Header & Segmented Tabs -->
            <div class="px-6 sm:px-10 pt-7 pb-4">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-50 border border-sky-200 text-sky-700 text-xs font-semibold tracking-wide uppercase mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2 5a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2V5zm14 1a1 1 0 11-2 0 1 1 0 012 0zM2 13a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H4a2 2 0 01-2-2v-2zm14 1a1 1 0 11-2 0 1 1 0 012 0z" clip-rule="evenodd" />
                        </svg>
                        IT Support Helpdesk
                    </div>
                    <h1 class="text-slate-800 text-xl sm:text-2xl font-extrabold tracking-tight">
                        TICKETING MANAGEMENT SYSTEM
                    </h1>
                    <p class="text-slate-500 text-xs sm:text-sm mt-1">
                        Submit a support request or track an existing ticket by reference number
                    </p>
                </div>

                <!-- Segmented Pill Tabs -->
                <div class="grid grid-cols-2 gap-1.5 p-1.5 bg-slate-100 rounded-xl border border-slate-200/70" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeView === 'create'"
                        class="flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg font-semibold text-xs sm:text-sm transition-all duration-150 focus:outline-none"
                        :class="activeView === 'create'
                            ? 'bg-sky-600 text-white shadow-sm'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'"
                        @click="switchTab('create')"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                        </svg>
                        <span>Create Ticket</span>
                    </button>

                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeView === 'track'"
                        class="flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg font-semibold text-xs sm:text-sm transition-all duration-150 focus:outline-none"
                        :class="activeView === 'track'
                            ? 'bg-sky-600 text-white shadow-sm'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'"
                        @click="switchTab('track')"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                        <span>Track Ticket</span>
                    </button>
                </div>
            </div>

            <!-- Active Tab Body -->
            <ClientCreate
                v-if="activeView === 'create'"
                @track-ref="handleTrackRef"
            />
            <ClientTrack
                v-if="activeView === 'track'"
                :initial-ref="initialTrackRef"
            />
        </div>
    </div>
</template>
