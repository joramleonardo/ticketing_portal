<template>
    <div class="flex-auto px-6 sm:px-10 pb-8 pt-2">
        <form @submit.prevent="submit" class="space-y-5">
            <!-- Email -->
            <div class="relative w-full">
                <label for="client_email" class="block text-slate-700 text-xs font-bold uppercase tracking-wider mb-1">
                    Email Address <span class="text-red-500">*</span>
                </label>
                <span id="client-email-hint" class="block text-xs text-slate-500 mb-2">
                    We'll send your ticket confirmation and updates to this email (e.g., you@example.com).
                </span>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                        </svg>
                    </div>
                    <input
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="you@stii.dost.gov.ph"
                        id="client_email"
                        v-model="form.client_email"
                        aria-describedby="client-email-hint"
                        aria-errormessage="client-email-error"
                        :aria-invalid="emailInvalid || !!errors.client_email || !!errors.email ? 'true' : undefined"
                        :class="{ 'user-invalid-fallback': emailInvalid || !!errors.client_email || !!errors.email }"
                        @blur="onEmailBlur"
                        @input="onEmailInput"
                        class="pl-10 pr-3.5 py-2.5 placeholder-slate-400 text-slate-800 border border-slate-300
                        relative bg-white rounded-xl text-sm shadow-sm outline-none transition
                        focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 w-full"
                    />
                </div>

                <p id="client-email-error" aria-live="polite" class="text-xs text-red-600 mt-1.5 min-h-[1rem]">
                    {{ errors.client_email || errors.email || (emailInvalid ? 'Please enter a valid email address.' : '') }}
                </p>
            </div>

            <!-- Problem -->
            <div class="relative w-full">
                <label for="ticket_problem_that_needed_support" class="block text-slate-700 text-xs font-bold uppercase tracking-wider mb-1">
                    Problem That Needed Support <span class="text-red-500">*</span>
                </label>
                <span class="block text-xs text-slate-500 mb-2">
                    Describe the issue clearly, including any error messages or location/room details.
                </span>
                <textarea
                    id="ticket_problem_that_needed_support"
                    v-model="form.ticket_problem_that_needed_support"
                    rows="4"
                    required
                    class="px-3.5 py-2.5 placeholder-slate-400 text-slate-800 border border-slate-300
                    relative bg-white rounded-xl text-sm shadow-sm outline-none transition
                    focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 w-full"
                    placeholder="Describe the issue you are experiencing…"
                ></textarea>
                <p v-if="errors.ticket_problem_that_needed_support" class="text-xs text-red-600 mt-1.5">
                    {{ errors.ticket_problem_that_needed_support }}
                </p>
            </div>

            <!-- Attachments (optional) -->
            <div class="relative w-full">
                <label for="attachments" class="block text-slate-700 text-xs font-bold uppercase tracking-wider mb-1">
                    Attachments <span class="text-slate-400 font-normal normal-case">(Optional)</span>
                </label>

                <label
                    for="attachments"
                    class="mt-1 flex flex-col items-center justify-center w-full px-4 py-5 border-2 border-dashed rounded-xl cursor-pointer transition"
                    :class="isDragging ? 'border-sky-500 bg-sky-50/70' : 'border-slate-300 bg-slate-50/60 hover:bg-slate-100/70 hover:border-slate-400'"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="onFilesDropped"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-sky-600 mb-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <span class="text-xs sm:text-sm font-medium text-slate-700">
                        Click to choose files <span class="text-slate-400 font-normal">or drag and drop here</span>
                    </span>
                    <span class="text-[11px] text-slate-400 mt-1">
                        Images (JPG, PNG, GIF, WEBP) or PDF up to 10 MB each
                    </span>
                    <input
                        id="attachments"
                        type="file"
                        multiple
                        accept="image/*,.pdf"
                        class="sr-only"
                        @change="onFilesSelected"
                    />
                </label>

                <!-- Preview of selected files -->
                <ul v-if="selectedFiles.length" class="mt-3 space-y-1.5">
                    <li
                        v-for="(f, i) in selectedFiles"
                        :key="i"
                        class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-slate-100 border border-slate-200 text-xs text-slate-700"
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="inline-flex items-center justify-center h-6 w-6 rounded bg-white border border-slate-200 text-sky-600 shrink-0 font-bold text-[10px]">
                                {{ f.type === 'application/pdf' ? 'PDF' : 'IMG' }}
                            </span>
                            <span class="truncate font-medium">{{ f.name }}</span>
                            <span class="text-slate-400 shrink-0">({{ formatBytes(f.size) }})</span>
                        </div>
                        <button
                            type="button"
                            class="text-slate-400 hover:text-red-600 p-1 rounded transition"
                            title="Remove file"
                            @click="removeFile(i)"
                        >
                            ✕
                        </button>
                    </li>
                </ul>

                <p v-if="errors.attachments" class="text-xs text-red-600 mt-1.5">{{ errors.attachments }}</p>
                <p v-if="errors['attachments.0']" class="text-xs text-red-600 mt-1.5">{{ errors['attachments.0'] }}</p>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button
                    type="submit"
                    :disabled="isSubmitting"
                    :aria-busy="isSubmitting"
                    class="w-full inline-flex items-center justify-center gap-2
                           bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white font-bold uppercase tracking-wide text-sm px-6 py-3.5 rounded-xl shadow-md
                           transition-all duration-150
                           hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-sky-400
                           disabled:opacity-60 disabled:cursor-not-allowed disabled:shadow-none disabled:pointer-events-none"
                >
                    <svg
                        v-if="isSubmitting"
                        class="h-4 w-4 animate-spin"
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    <span>{{ isSubmitting ? 'Submitting Request…' : 'Submit Ticket' }}</span>
                </button>
            </div>
        </form>

        <!-- Success modal with reference number -->
        <Modal :show="showRefModal" @close="closeRefModal">
            <div class="p-6 sm:p-8 w-full sm:max-w-lg mx-auto text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <h2 class="text-xl font-bold text-slate-800 mb-2">Ticket Submitted Successfully!</h2>
                <p class="text-sm text-slate-600 mb-5">
                    Please save your Reference Number below to track your request. A confirmation email has also been sent to your address.
                </p>

                <div
                    class="bg-slate-100 border-2 border-dashed border-slate-300 hover:border-sky-500 rounded-xl px-4 py-3.5 text-center text-xl font-mono font-bold text-slate-800 tracking-wider mb-5 select-all cursor-pointer transition"
                    @click="copyRef"
                    title="Click to copy reference number"
                >
                    {{ generatedRefNo }}
                </div>

                <div class="flex flex-col sm:flex-row gap-2.5 justify-center">
                    <button
                        type="button"
                        @click="copyRef"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 text-xs font-semibold uppercase tracking-wide transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                            <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                        </svg>
                        Copy Reference No.
                    </button>

                    <button
                        type="button"
                        @click="goToTrackNow"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold uppercase tracking-wide shadow-sm transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                        Track This Ticket
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import Modal from '@/Components/Modal.vue'

const emit = defineEmits(['track-ref'])
const toast = useToast()

const RECENT_REFS_KEY = 'stii_recent_ticket_refs'

function saveRecentRef(refNo) {
    const clean = (refNo || '').trim()
    if (!clean) return
    try {
        const raw = localStorage.getItem(RECENT_REFS_KEY)
        const list = Array.isArray(JSON.parse(raw)) ? JSON.parse(raw) : []
        const updated = [clean, ...list.filter((r) => r !== clean)].slice(0, 5)
        localStorage.setItem(RECENT_REFS_KEY, JSON.stringify(updated))
    } catch {
        // ignore storage errors
    }
}

const form = ref({
    client_email: '',
    ticket_problem_that_needed_support: '',
})

const errors = ref({})
const isSubmitting = ref(false)
const emailInvalid = ref(false)
const isDragging = ref(false)

function onEmailBlur(e) {
    const input = e.target
    if (input && !input.validity.valid) {
        emailInvalid.value = true
    }
}

function onEmailInput(e) {
    const input = e.target
    if (input && input.validity.valid) {
        emailInvalid.value = false
    }
    if (errors.value.client_email || errors.value.email) {
        delete errors.value.client_email
        delete errors.value.email
    }
}

// modal state
const showRefModal = ref(false)
const generatedRefNo = ref('')

function reset() {
    form.value.client_email = ''
    form.value.ticket_problem_that_needed_support = ''
    errors.value = {}
    emailInvalid.value = false
}

async function copyRef() {
    const text = (generatedRefNo.value || '').trim()
    if (!text) return

    if (window.isSecureContext && navigator.clipboard && navigator.clipboard.writeText) {
        try {
            await navigator.clipboard.writeText(text)
            toast.success('Reference number copied.')
            return
        } catch (err) {
            console.warn('navigator.clipboard failed, falling back:', err)
        }
    }

    try {
        const ta = document.createElement('textarea')
        ta.value = text
        ta.setAttribute('readonly', '')
        ta.style.position = 'fixed'
        ta.style.top = '-9999px'
        document.body.appendChild(ta)
        ta.select()
        ta.setSelectionRange(0, ta.value.length)
        const ok = document.execCommand('copy')
        document.body.removeChild(ta)

        if (ok) {
            toast.success('Reference number copied.')
        } else {
            throw new Error('execCommand returned false')
        }
    } catch (err) {
        console.error('Fallback copy failed:', err)
        toast.error('Copy failed. Please select the number and copy manually.')
    }
}

function closeRefModal() {
    showRefModal.value = false
}

function goToTrackNow() {
    const refNo = generatedRefNo.value
    showRefModal.value = false
    if (refNo) {
        emit('track-ref', refNo)
    }
}

function submit() {
    if (isSubmitting.value) return
    isSubmitting.value = true
    errors.value = {}

    const payload = {
        client_email: form.value.client_email,
        ticket_problem_that_needed_support: form.value.ticket_problem_that_needed_support,
        attachments: selectedFiles.value
    }

    router.post(
        '/client-tickets-v2',
        payload,
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: (page) => {
                const props = page?.props ?? router.page?.props ?? {}
                const ref = props.flash?.refNo ?? props.refNo ?? ''
                toast.success(ref ? `Ticket submitted. Ref: ${ref}` : 'Ticket submitted.')
                generatedRefNo.value = ref
                if (ref) saveRecentRef(ref)
                showRefModal.value = true
                reset()
                selectedFiles.value = []
                const input = document.getElementById('attachments')
                if (input) input.value = ''
            },
            onError: (e) => {
                errors.value = e || {}
                const first = e && Object.values(e)[0]
                if (first) toast.error(first)
            },
            onFinish: () => {
                isSubmitting.value = false
            },
        }
    )
}

const selectedFiles = ref([])

function filterValidFiles(fileList) {
    const ok = []
    for (const f of fileList) {
        const isImage = f.type.startsWith('image/')
        const isPdf = f.type === 'application/pdf'
        const under10mb = f.size <= 10 * 1024 * 1024
        if ((isImage || isPdf) && under10mb) {
            ok.push(f)
        } else {
            toast.warning(`Skipped "${f.name}" (only images/PDF under 10 MB allowed).`)
        }
    }
    return ok
}

function onFilesSelected(e) {
    const files = Array.from(e.target.files || [])
    selectedFiles.value = [...selectedFiles.value, ...filterValidFiles(files)]
}

function onFilesDropped(e) {
    isDragging.value = false
    const files = Array.from(e.dataTransfer?.files || [])
    selectedFiles.value = [...selectedFiles.value, ...filterValidFiles(files)]
}

function removeFile(idx) {
    selectedFiles.value.splice(idx, 1)
}

function formatBytes(bytes) {
    const n = Number(bytes || 0)
    if (n < 1024) return `${n} B`
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
    return `${(n / (1024 * 1024)).toFixed(1)} MB`
}
</script>

<style scoped>
input:user-invalid,
input.user-invalid-fallback {
    border-color: #d93025;
}
input:user-valid {
    border-color: #1e8e3e;
}
</style>
