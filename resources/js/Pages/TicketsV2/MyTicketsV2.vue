
<script setup>
    import { ref, watch, reactive, computed, onMounted, onBeforeUnmount, nextTick} from 'vue';
    import { Link, router, usePage } from '@inertiajs/vue3';
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
    import CardNewTickets from "@/Pages/TicketsV2/MyTicketComponents/NewTickets.vue"
    import CardRecentTickets from "@/Pages/TicketsV2/MyTicketComponents/InProgressTickets.vue"
    import CardNoRatingTickets from "@/Pages/TicketsV2/MyTicketComponents/NoRatingTickets.vue"
    import CardNoCSMTickets from "@/Pages/TicketsV2/MyTicketComponents/NoCSMTickets.vue"
    import CardAllMyTickets from "@/Pages/TicketsV2/MyTicketComponents/AllMyTickets.vue"

const props = defineProps({
  ticketStats: Object,
  filters: Object,
  tickets: Object,
  pendingTickets: Array,
  myNewTickets: Array,
  myInProgTickets: Array,
  noCsmTickets: Array,
})


const page = usePage()

const localMyNew = ref(props.myNewTickets ? [...props.myNewTickets] : [])
const localMyInProg = ref(props.myInProgTickets ? [...props.myInProgTickets] : [])

// keep local copy updated after reloads/navigation
watch(() => props.myInProgTickets, (val) => {
  localMyInProg.value = val ? [...val] : []
})

function reloadMyInProg() {
  router.reload({
    only: ['myInProgTickets'],
    onSuccess: () => {
      // ensure latest props are reflected locally
      localMyInProg.value = page.props.myInProgTickets ? [...page.props.myInProgTickets] : []
    },
  })
}

const allRef = ref(null)

function handleAttended(/* { ticketId } */) {
    allRef.value?.refresh()
}

function handleChanged() {
  router.reload({
    preserveScroll: true,
    only: ['myNewTickets','myInProgTickets','ticketStats','noCsmTickets'],
    onSuccess: () => {
      allRef.value?.refresh()
    },
  })
}
</script>

<template>
    <Head title="My Ticket" />

    <AuthenticatedLayout :ticket-stats="ticketStats" :filters="filters">
        <div class="flex flex-wrap mt-4">
            <div class="w-full xl:w-8/12 px-4">
                      <CardAllMyTickets
                        ref="allRef"
                        :tickets="tickets"
                        :filters="filters"

                        @attended="handleChanged"
                        @completed="handleChanged"
                      />
            </div>
            <div class="w-full xl:w-4/12 px-4">
                <CardNewTickets :tickets="myNewTickets" @attended="handleChanged" />
                <CardRecentTickets :tickets="localMyInProg" @completed="handleChanged" />
                <CardNoRatingTickets :pending-tickets="pendingTickets" />
                <CardNoCSMTickets :tickets="noCsmTickets" />
            </div>
            <div class="w-full xl:w-4/12 px-4">
            </div>
            <div class="w-full px-4">
            </div>
        </div>
    </AuthenticatedLayout>
</template>

