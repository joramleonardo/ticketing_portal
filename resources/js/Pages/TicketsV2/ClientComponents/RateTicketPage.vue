<script setup>
    import { ref, computed, watch, onMounted } from "vue"
    import axios from "axios"
    import registerBg2 from "@/assets/img/register_bg_2.png";
    import { useToast } from "vue-toastification"

    const props = defineProps({
        refNo: { type: String, required: true }, // from Inertia props
    })

    // ---- state
    const loading = ref(false)
    const posting = ref(false)
    const error = ref("")
    const details = ref(null)
    const existing = ref(null)

    // tracking ticket (with dates from activity logs via /client/track/ref/{refNo})
    const tracking = ref(null)
    const toast = useToast()
    const score = ref(0)
    const comment = ref("")

    const ratingMap = [
    { score: 5, label: "Excellent" },
    { score: 4, label: "Very Good" },
    { score: 3, label: "Good" },
    { score: 2, label: "Poor" },
    { score: 1, label: "Very Poor" },
    ]
    const labelForScore = (n) => ratingMap.find(r => r.score === n)?.label || ""
    // const canSubmit = computed(() => score.value > 0 && !posting.value)
    // Disable submit when already rated
    const isRated = computed(() => !!existing.value && (
      existing.value.rating_status === 'Submitted' || !!existing.value.rating_result
    ))
    const canSubmit = computed(() => score.value > 0 && !posting.value && !isRated.value)

    // ====== CSM link (Google Form prefill) ======
    const CSM_BASE =
    "https://docs.google.com/forms/d/e/1FAIpQLSfda5Huf3g5zB5kIoemYLrM-SPR41VmTYRQ2gyeGwaeTtXPsg/viewform?usp=pp_url"

    const ENTRY = {
    CAT_ENG: "entry.613601820",
    DATE_ENG: "entry.204278290",
    SEX_ENG: "entry.468511028",
    SERVICE_ENG: "entry.474414778",

    CAT_TAG: "entry.1426259693",
    DATE_TAG: "entry.1416427164",
    SEX_TAG: "entry.2016337212",
    SERVICE_TAG: "entry.2008685738",

    STATIC_1: "entry.1725600",
    STATIC_2: "entry.1819805617",

    REF_ENG: "entry.298800992",
    NAME_ENG: "entry.855472974",
    REF_TAG: "entry.1449935561",
    NAME_TAG: "entry.1990048049",
    }

    const STATIC_PREFILL = new URLSearchParams({
    [ENTRY.CAT_ENG]: "Government (Employee or another agency)",
    [ENTRY.SERVICE_ENG]: "Technical Support Services",
    [ENTRY.CAT_TAG]: "Gobyerno (Empleyado o Ahensya)",
    [ENTRY.SERVICE_TAG]: "Technical Support Services",
    [ENTRY.STATIC_1]: "0",
    [ENTRY.STATIC_2]: "0",
    })

    const formatYmd = (val) => {
    if (!val) return ""
    if (typeof val === "string") {
        const m = val.match(/^(\d{4}-\d{2}-\d{2})/)
        if (m) return m[1]
    }
    const d = new Date(val)
    if (Number.isNaN(d.getTime())) return ""
    const pad = (n) => String(n).padStart(2, "0")
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`
    }


    // pretty datetime for the timeline labels
    const formatDate = (val) => {
        if (!val) return ""
        const d = new Date(val)
        if (Number.isNaN(d.getTime())) return String(val)
        const pad = (n) => String(n).padStart(2, "0")
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
    }

    const csmUrl = computed(() => {
        const url = new URL(CSM_BASE)
        const params = new URLSearchParams(url.search)

        STATIC_PREFILL.forEach((v, k) => params.set(k, v))

        if (details.value) {
            const completed = formatYmd(details.value.date_completed)
            const sex = details.value.client_sex || ""
            const ref = details.value.client_referenceNo || ""
            const name = details.value.client_email || details.value.client_name || ""

            if (completed) {
            params.set(ENTRY.DATE_ENG, completed)
            params.set(ENTRY.DATE_TAG, completed)
            }
            if (sex) {
            params.set(ENTRY.SEX_ENG, sex)
            params.set(ENTRY.SEX_TAG, sex)
            }
            if (ref) {
            params.set(ENTRY.REF_ENG, ref)
            params.set(ENTRY.REF_TAG, ref)
            }
            if (name) {
            params.set(ENTRY.NAME_ENG, name)
            params.set(ENTRY.NAME_TAG, name)
            }
        }

        url.search = params.toString()
        return url.toString()
    })

    // ---------- Tracking helpers ----------
    // pull first available field name from object
    const firstOf = (obj, keys) => {
        for (const k of keys) {
        const v = obj?.[k]
        if (v !== undefined && v !== null && v !== "") return v
        }
        return null
    }

    // map status to fallback step (0..3)
    function statusStepIndex () {
        const s = (tracking.value?.ticket_status || '').toLowerCase()
        if (s.includes('closed') || s.includes('completed')) return 3   // Completed
        if (s.includes('progress')) return 2                            // Attended
        if (s.includes('assign')) return 1                              // Assigned
        return 0                                                        // Created (New/Pending/unknown)
    }

    // Build steps from activity-log dates (controller populates these)
    const steps = computed(() => {
        if (!tracking.value) return []
        const t = tracking.value
        const created   = firstOf(t, ['created_at',  'createdAt',  'date_created'])
        const assigned  = firstOf(t, ['assigned_at', 'date_assigned', 'assignedAt'])
        const attended  = firstOf(t, ['attended_at', 'date_attended', 'attendedAt'])
        const completed = firstOf(t, ['completed_at','date_completed','completedAt'])

        const items = [
        { key: 'created',   label: 'Created',   date: created },
        { key: 'assigned',  label: 'Assigned',  date: assigned },
        { key: 'attended',  label: 'Attended',  date: attended },
        { key: 'completed', label: 'Completed', date: completed },
        ]

        const fb = statusStepIndex()
        return items.map((x, i) => ({ ...x, done: Boolean(x.date) || i <= fb }))
    })

    const currentStepIndex = computed(() => {
        let idx = statusStepIndex()
        const s = steps.value
        for (let i = 0; i < s.length; i++) if (s[i].done) idx = Math.max(idx, i)
        return idx
    })
    const currentStepLabel = computed(() => steps.value[currentStepIndex.value]?.label || '—')
    const progressPercent  = computed(() => {
        const total = steps.value.length
        if (total <= 1) return 0
        return (currentStepIndex.value / (total - 1)) * 100
    })


    // ---- data loading
    const load = async () => {
        if (!props.refNo) return
        loading.value = true
        error.value = ""
        try {
            const [{ data: d }, { data: r }, { data: t }] = await Promise.all([
                axios.get(`/client/ticket/${encodeURIComponent(props.refNo)}`),
                axios.get(`/client/ticket/${encodeURIComponent(props.refNo)}/rating`),
                // 👇 returns { ticket: { created_at, assigned_at, attended_at, completed_at, ticket_status, ... } }
                axios.get(`/client/track/ref/${encodeURIComponent(props.refNo)}`),
            ])
            details.value  = d
            existing.value = r || null
            tracking.value = t?.ticket || null

            if (existing.value?.rating_result) {
                const found = ratingMap.find(x => x.label === existing.value.rating_result)
                score.value = found?.score || 0
                comment.value = existing.value.rating_comment_suggestion || ""
            } else {
                score.value = 0
                comment.value = ""
            }
        } catch (e) {
            error.value = "Failed to load ticket/rating details."
        } finally {
            loading.value = false
        }
    }

    onMounted(load)
    watch(() => props.refNo, load)

    // ---- submit
    const submit = async () => {
        if (!canSubmit.value || !props.refNo) return
            posting.value = true
            error.value = ""
        try {
            await axios.post(`/client/ticket/${encodeURIComponent(props.refNo)}/rating`, {
                rating_result: labelForScore(score.value),
                rating_comment_suggestion: comment.value,
            })
            await load()
            toast.success("Thank you! Your feedback has been submitted.")
        } catch (e) {
            error.value = "Unable to submit rating."
            toast.error("Unable to submit rating. Please try again.")
        } finally {
            posting.value = false
        }
    }
</script>

<template>
  <div>
    <!-- <navbar /> -->
    <main>
      <section class="relative w-full h-full py-40 min-h-screen">
        <div
          class="absolute top-0 w-full h-full bg-blueGray-800 bg-no-repeat bg-full"
          :style="`background-image: url('${registerBg2}');`"
            >
        </div>
        <div class="container mx-auto px-4 h-full">
            <div class="flex content-center items-center justify-center h-full">
                <div class="w-full lg:w-6/12 px-4">
                    <div class="relative flex flex-col min-w-0 break-words w-full mb-6 shadow-lg rounded-lg bg-blueGray-200 border-0" >
                        <div class="rounded-t mb-0 px-6 py-6">
                            <div class="text-center mb-3">
                                <h3 class="text-blueGray-500 font-bold">
                                TICKETING MANAGEMENT SYSTEM
                                </h3>
                            </div>
                            <div class="flex border-t border-gray-300">
                                <div class="flex-auto px-4 lg:px-10 py-10 pt-0">
                                    <div class="text-blueGray-400 text-center mb-3 mt-5 font-bold">
                                        <small>CLIENT FEEDBACK</small>
                                    </div>

                                    <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
                                    <template v-else>
                                        <div v-if="error" class="text-sm text-red-600">{{ error }}</div>

                                        <!-- Order-style tracker for this reference -->
                                        <div v-if="tracking" class="mb-4 space-y-4">
                                            <!-- Header -->
                                            <div class="rounded-2xl border shadow-sm bg-white p-5">
                                                <div class="flex flex-wrap items-start justify-between gap-4">
                                                    <div class="space-y-1">
                                                        <div class="text-xs text-blueGray-500">Reference</div>
                                                        <div class="font-mono text-lg font-semibold text-blueGray-900">
                                                        {{ tracking.client_referenceNo || props.refNo }}
                                                        </div>
                                                    </div>
                                                    <div class="text-right space-y-1">
                                                        <div class="text-xs text-blueGray-500">Email</div>
                                                        <div class="font-semibold">{{ tracking.client_email || tracking.client_name || '—' }}</div>
                                                    </div>
                                                </div>
                                                <div class="flex flex-wrap items-start justify-between gap-4 mt-2">
                                                    <div class="space-y-1">
                                                        <div class="text-xs text-blueGray-500">Problem that needed support</div>
                                                        <div class="font-mono text-lg font-semibold text-blueGray-900">
                                                        {{ tracking.ticket_problem}}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                                    <span class="text-blueGray-500">Status:</span>
                                                    <span
                                                        class="px-2 py-1 rounded"
                                                        :class="{
                                                        'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-orange-600 bg-orange-200': tracking.ticket_status === 'New',
                                                        'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-red-600 bg-red-200': tracking.ticket_status === 'Pending',
                                                        'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-amber-600 bg-amber-200': tracking.ticket_status === 'In Progress',
                                                        'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-emerald-600 bg-emerald-200': tracking.ticket_status === 'Completed',
                                                        'text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-blueGray-600 bg-blueGray-200': tracking.ticket_status === 'Closed'
                                                        }"
                                                    >
                                                        {{ tracking.ticket_status || 'N/A' }}
                                                    </span>
                                                    <span class="mx-2 h-4 w-px bg-blueGray-200"></span>
                                                    <span class="text-blueGray-500">Tech assigned:</span>
                                                    <span class="font-medium">{{ tracking.assigned_to_name || tracking.assigned_to || '—' }}</span>
                                                </div>
                                            </div>

                                            <!-- Horizontal progress (static, no animations) -->
                                            <div class="rounded-2xl border bg-white p-5 shadow-sm">
                                                <div class="mb-4 flex items-center justify-between">
                                                    <div class="text-sm font-semibold text-blueGray-900">Ticket Progress</div>
                                                    <div class="text-xs text-blueGray-500">{{ currentStepLabel }}</div>
                                                </div>

                                                <div class="relative">
                                                    <!-- Track -->
                                                    <div class="absolute left-0 right-0 top-3 h-1.5 rounded-full bg-blueGray-100"></div>
                                                    <!-- Progress (static) -->
                                                    <div class="absolute left-0 top-3 h-1.5 rounded-full bg-emerald-400" :style="{ width: progressPercent + '%' }"></div>

                                                    <!-- Steps -->
                                                    <div class="relative flex justify-between">
                                                        <div v-for="s in steps" :key="s.key" class="flex flex-col items-center w-1/4">
                                                        <div class="grid place-items-center h-7 w-7 rounded-full border-2 bg-white z-10"
                                                            :class="s.done ? 'border-emerald-500' : 'border-blueGray-300'">
                                                            <!-- check icon when done, dot otherwise -->
                                                            <svg v-if="s.done" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                                class="h-4 w-4 text-emerald-600">
                                                            <path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/>
                                                            </svg>
                                                            <div v-else class="h-2.5 w-2.5 rounded-full bg-blueGray-300"></div>
                                                        </div>
                                                        <div class="mt-2 text-xs font-medium" :class="s.done ? 'text-emerald-700' : 'text-blueGray-600'">
                                                            {{ s.label }}
                                                        </div>
                                                        <div class="text-[10px] text-blueGray-500">
                                                            {{ s.date ? formatDate(s.date) : '—' }}
                                                        </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border bg-white p-5 shadow-sm">
                                            <div class="mb-4 flex items-center justify-between">
                                                <div class="text-sm font-semibold text-blueGray-900">Please help us determine the quality of our services by ticking the appropriate option.</div>
                                            </div>

                                            <!-- Stars + comment -->
                                            <div class="p-4 border rounded space-y-3">
                                                <div class="flex items-center gap-2 mt-2 mb-5">
                                                    <button
                                                        v-for="n in 5"
                                                        :key="n"
                                                        type="button"
                                                        class="p-1"
                                                        :class="isRated ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''"
                                                        @click="!isRated && (score = n)"
                                                        :aria-label="`Rate ${n} star(s)`"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="w-7 h-7"
                                                            :class="n <= score ? 'fill-yellow-400 stroke-yellow-500' : 'fill-transparent stroke-gray-400'">
                                                        <path stroke-width="1.5"
                                                                d="M10 2.5l2.6 5.2 5.7.8-4.1 4 1 5.7-5.2-2.7-5.2 2.7 1-5.7-4.1-4 5.7-.8L10 2.5z"/>
                                                        </svg>
                                                    </button>
                                                    <div class="text-sm text-gray-700 min-w-[120px]">
                                                        {{ score ? (['Very Poor','Poor','Good','Very Good','Excellent'][score-1]) : 'Select rating' }}
                                                    </div>
                                                </div>

                                                <div>
                                                    <label class="text-sm font-medium mb-1 block">Comments / Suggestions</label>
                                                    <textarea
                                                        v-model="comment"
                                                        rows="3"
                                                        class="w-full border rounded px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-indigo-200"
                                                        placeholder="Tell us more about your experience (optional)…"
                                                        :disabled="isRated"
                                                    ></textarea>
                                                </div>

                                                <div v-if="isRated" class="text-xs px-3 py-2 rounded bg-green-50 text-green-700 border border-green-200">
                                                    Feedback already submitted for this ticket. Thank you!
                                                </div>

                                                <div class="flex flex-wrap gap-2 justify-between items-center">
                                                    <a
                                                        :href="csmUrl"
                                                        target="_blank"
                                                        class="px-4 py-2 rounded border text-white bg-lightBlue-500
                                                                hover:shadow-lg outline-none focus:outline-none
                                                        "
                                                    >
                                                        Answer CSM
                                                    </a>

                                                    <button
                                                        class="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:shadow-md disabled:opacity-60"
                                                        :disabled="!canSubmit"
                                                        @click="submit"
                                                    >
                                                        {{ posting ? 'Submitting…' : (isRated ? 'Feedback Submitted' : 'Submit Feedback') }}
                                                    </button>
                                                </div>

                                                <p v-if="details && !details.date_completed" class="text-xs text-amber-600">
                                                    Note: Ticket completion date is not set yet. You can still send feedback, but the CSM link may require a completion date.
                                                </p>
                                            </div>
                                        </div>


                                    </template>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
      </section>
    </main>
  </div>
</template>
