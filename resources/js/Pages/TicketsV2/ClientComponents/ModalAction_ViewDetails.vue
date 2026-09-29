<script setup>
import { ref, watch, onMounted, nextTick } from "vue"
import axios from "axios"

const props = defineProps({
  show: { type: Boolean, default: false },
  ticketRef: { type: String, default: null },
})
const emit = defineEmits(["close"])

const loading = ref(false)
const posting = ref(false)
const error = ref("")
const details = ref(null)
const messages = ref([])
const input = ref("")
const listEl = ref(null) // for auto-scroll

const scrollToBottom = async () => {
  await nextTick()
  if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight
}

const loadAll = async () => {
  if (!props.ticketRef) return
  error.value = ""
  loading.value = true
  try {
    const [{ data: d }, { data: m }] = await Promise.all([
      axios.get(`/client/ticket/${encodeURIComponent(props.ticketRef)}`),
      axios.get(`/client/ticket/${encodeURIComponent(props.ticketRef)}/messages`),
    ])
    details.value = d
    messages.value = Array.isArray(m) ? m : []
    await scrollToBottom()
  } catch (e) {
    error.value = "Failed to load ticket details/messages."
  } finally {
    loading.value = false
  }
}

const sendMessage = async () => {
  const text = input.value.trim()
  if (!text || posting.value || !props.ticketRef) return
  posting.value = true
  try {
    await axios.post(`/client/ticket/${encodeURIComponent(props.ticketRef)}/messages`, {
      message: text,
      sender_type: "client", // this is the client portal
    })
    input.value = ""
    // refresh messages only
    const { data } = await axios.get(`/client/ticket/${encodeURIComponent(props.ticketRef)}/messages`)
    messages.value = Array.isArray(data) ? data : []
    await scrollToBottom()
  } catch (e) {
    // keep it simple
  } finally {
    posting.value = false
  }
}

// Enter handler
const onKeydown = (e) => {
  if (e.key === "Enter" && !e.shiftKey) {
    e.preventDefault()
    sendMessage()
  }
}

watch(
  () => props.show,
  (v) => { if (v) loadAll() }
)
watch(
  () => props.ticketRef,
  () => { if (props.show) loadAll() }
)
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
    <!-- overlay -->
    <div class="absolute inset-0 bg-black/40" @click="$emit('close')"></div>

    <!-- modal -->
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-4xl mx-4">
      <div class="flex items-center justify-between px-5 py-4 border-b">
        <h3 class="text-lg font-semibold">Ticket Details</h3>
        <button class="text-gray-500 hover:text-gray-700" @click="$emit('close')">✕</button>
      </div>

      <div class="p-5">
        <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
        <div v-else>
          <div v-if="error" class="text-sm text-red-600 mb-4">{{ error }}</div>

          <!-- Simple ticket details -->
          <div v-if="details" class="grid md:grid-cols-2 gap-4 mb-6">
            <div class="p-4 rounded border">
              <div class="text-xs text-gray-500">Reference No</div>
              <div class="font-mono text-base">{{ details.client_referenceNo }}</div>
            </div>
            <div class="p-4 rounded border">
              <div class="text-xs text-gray-500">Status</div>
              <div class="text-base">{{ details.ticket_status }}</div>
            </div>
            <div class="p-4 rounded border md:col-span-2">
              <div class="text-xs text-gray-500">Problem</div>
              <div class="text-base break-words">{{ details.ticket_problem_that_needed_support }}</div>
            </div>
            <div class="p-4 rounded border">
              <div class="text-xs text-gray-500">Date Requested</div>
              <div class="text-base">{{ details.date_requested || "—" }}</div>
            </div>
            <div class="p-4 rounded border">
              <div class="text-xs text-gray-500">Date Attended</div>
              <div class="text-base">{{ details.date_attended || "—" }}</div>
            </div>
            <div class="p-4 rounded border">
              <div class="text-xs text-gray-500">Date Completed</div>
              <div class="text-base">{{ details.date_completed || "—" }}</div>
            </div>
          </div>

          <!-- Messages -->
          <div class="rounded border overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b text-sm font-semibold">Message Log</div>
            <div ref="listEl" class="max-h-72 overflow-auto p-4 space-y-3">
              <div
                v-for="m in messages"
                :key="m.id"
                class="flex"
                :class="m.sender_type === 'client' ? 'justify-end' : 'justify-start'"
              >
                <div
                  class="px-3 py-2 rounded-lg max-w-[80%]"
                  :class="m.sender_type === 'client' ? 'bg-sky-500 text-white' : 'bg-gray-100 text-gray-800'"
                >
                  <div class="text-[11px] opacity-80 mb-1">
                    {{ m.sender_name || (m.sender_type === 'client' ? 'Client' : 'Support') }} · {{ m.created_at }}
                  </div>
                  <div class="whitespace-pre-wrap break-words">{{ m.message }}</div>
                </div>
              </div>
            </div>

            <!-- Input -->
            <div class="p-3 border-t">
              <textarea
                v-model="input"
                @keydown="onKeydown"
                rows="2"
                placeholder="Type a message and press Enter to send… (Shift+Enter for newline)"
                class="w-full border rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:ring-sky-200"
              ></textarea>
              <div class="mt-2 flex justify-end">
                <button
                  class="bg-teal-500 text-white active:bg-teal-600 font-bold uppercase text-xs px-4 py-2 rounded shadow hover:shadow-md outline-none focus:outline-none ease-linear transition-all duration-150 disabled:opacity-60"
                  :disabled="posting || !input.trim()"
                  @click="sendMessage"
                >
                  {{ posting ? 'Sending…' : 'Send' }}
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="flex justify-end gap-2 px-5 py-4 border-t">
        <button class="px-4 py-2 rounded border hover:bg-gray-50" @click="$emit('close')">Close</button>
      </div>
    </div>
  </div>
</template>
