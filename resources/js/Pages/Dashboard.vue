<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import CardNewTickets from "@/Components/Cards/CardNewTickets.vue";
    import CardMyTickets from "@/Components/Cards/CardMyTickets.vue";
    import HeaderStats from "@/Components/Headers/HeaderStats.vue";

    import { Head, router } from '@inertiajs/vue3';
    import { ref } from 'vue';

    const props = defineProps({
        ticketStats: Object,
        filters: Object,
        initialMonth: String,
        initialYear: String
    });

    const selectedMonth = ref(props.initialMonth || '');
    const selectedYear = ref(props.initialYear || '');

    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

    function monthName(month) {
    return new Date(2000, month - 1).toLocaleString('default', { month: 'long' });
    }

    function applyFilter() {
    router.get('/dashboard', {
        month: selectedMonth.value,
        year: selectedYear.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
    }
</script>


<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout :ticketStats="ticketStats">

        <div>
            <HeaderStats
            :ticket-stats="ticketStats"
            :initial-month="filters.month"
            :initial-year="filters.year"
            />
        <div class="flex flex-wrap mt-4">
            <div class="w-full xl:w-8/12 mb-12 xl:mb-0 px-4">
                <CardNewTickets />
            </div>
            <div class="w-full xl:w-4/12 px-4">
                <CardMyTickets />
            </div>
        </div>
    </div>
    </AuthenticatedLayout>
</template>
