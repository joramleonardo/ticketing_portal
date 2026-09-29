<script setup>
    import { ref } from 'vue'
    import { router } from '@inertiajs/vue3'

    import { useToast } from 'vue-toastification'
    const toast = useToast()

    const emit = defineEmits(['success','created','close'])

    const form = ref({
        client_email: '',

        // Ticket Details Fields
        ticket_type: '', //Request Type
        ticket_attended_by: '',
        ticket_problem_that_needed_support: ''
    })

    const errors = ref({})
    const isSubmitting = ref(false)

    async function submit() {
        if (isSubmitting.value) return
        isSubmitting.value = true

        errors.value = {}

        await router.post('/tickets-v2', {
            // Client details
            client_email: form.value.client_email,

            // Ticket details
            ticket_type: form.value.ticket_type,
            ticket_problem_that_needed_support: form.value.ticket_problem_that_needed_support,

            // NOTE: we do NOT send client_referenceNo here; backend will generate it.
        }, {
            onSuccess: (page) => {
                emit('success')
                emit('created')   // <-- tell parent to refresh the table
                emit('close')     // <-- if the parent controls a Create modal, it can hide it

                isSubmitting.value = false
                const refNo = page.props?.flash?.refNo
                toast.success(refNo
                    ? `New ticket ${refNo} created and marked In Progress.`
                    : 'New ticket created and marked In Progress.'
                )
                // reset
                Object.assign(form.value, {
                    client_email: '',
                    ticket_type: '',
                    ticket_problem_that_needed_support: ''
                })
            },
            onError: (e) => {
                isSubmitting.value = false
                errors.value = e || {}
                toast.error('Failed to create ticket.')
            }
        })
    }
</script>


<template>
    <div class="max-h-[80vh] overflow-y-auto p-6">
        <h1 class="text-2xl font-bold text-blue-900 mb-6 border-b pb-2 flex items-center gap-2">
            <!-- Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-fill" viewBox="0 0 16 16">
                <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
            </svg>
            Create New Ticket
        </h1>

        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Client Email -->
            <div>
                <label for="modal-create-client-email" class="block text-sm font-medium mb-1">Email</label>
                <span id="modal-create-client-email-hint" class="block text-xs text-gray-500 mb-1">Enter the client's email address (e.g., name@stii.dost.gov.ph).</span>
                <input
                    id="modal-create-client-email"
                    v-model="form.client_email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="name@stii.dost.gov.ph"
                    aria-describedby="modal-create-client-email-hint"
                    aria-errormessage="modal-create-client-email-err"
                    :aria-invalid="errors.client_email ? 'true' : undefined"
                    class="email-input w-full border px-3 py-2 rounded"
                />
                <div id="modal-create-client-email-err" class="text-red-500 text-sm mt-1" aria-live="polite">{{ errors.client_email || '' }}</div>
            </div>

            <!-- Request Type -->
            <div>
                <label class="block text-sm font-medium mb-1">Request Type</label>
                <span class="block text-xs text-transparent mb-1 select-none">&nbsp;</span>
                <select v-model="form.ticket_type" class="w-full border px-3 py-2 rounded">
                    <option disabled value="">Select</option>
                    <option>Technical Support</option>
                    <option>Livestream</option>
                </select>
                <div v-if="errors.ticket_type" class="text-red-500 text-sm mt-1">{{ errors.ticket_type }}</div>
            </div>

            <!-- Problem Description -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium">Problem That Needed Support</label>
                <textarea v-model="form.ticket_problem_that_needed_support" class="w-full border px-3 py-2 rounded"></textarea>
                <div v-if="errors.ticket_problem_that_needed_support" class="text-red-500 text-sm mt-1">{{ errors.ticket_problem_that_needed_support }}</div>
            </div>

            <!-- Submit -->
            <div class="md:col-span-2 text-right">
                <button type="submit" class="bg-blue-900 text-white px-6 py-2 rounded hover:bg-blue-800">
                    Submit Ticket
                </button>
            </div>
        </form>

    </div>
</template>

<style scoped>
.email-input:user-invalid {
    border-color: #dc2626;
}
</style>
