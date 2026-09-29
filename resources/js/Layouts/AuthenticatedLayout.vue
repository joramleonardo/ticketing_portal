<script>

    import Sidebar from '@/Components/Sidebar/Sidebar.vue';
    import AdminNavbar from "@/Components/Navbars/AdminNavbar.vue";
    import HeaderBody from "@/Components/Headers/HeaderBody.vue";
    import HeaderStats from "@/Components/Headers/HeaderStats.vue";
    import FooterAdmin from "@/Components/Footers/FooterAdmin.vue";

    export default {
        name: "admin-layout",
        props: {
            ticketStats: Object,
            filters: {
            type: Object,
            default: () => ({ month: '', year: '' }),
            },
        },
        components: {
            AdminNavbar,
            Sidebar,
            HeaderBody,
            HeaderStats,
            FooterAdmin,
        },
    };
</script>

<template>
    <div>
        <Sidebar />
        <div class="relative md:ml-64 bg-blueGray-100">
            <AdminNavbar />

            <!-- <HeaderStats
            :ticket-stats="ticketStats"
            :initial-month="filters.month"
            :initial-year="filters.year"
            /> -->
            <div class="relative bg-blue-900 md:pt-32 pb-12 pt-12">

            </div>

            <div class="px-4 md:px-10 mx-auto w-full -m-24">
                <main >
                    <slot />
                </main>
                <FooterAdmin />
            </div>
        </div>
    </div>
</template>

<!-- <template>
    <div>
        <Sidebar />
        <div class="relative md:ml-64 bg-blueGray-100">
            <AdminNavbar />

            <HeaderStats
            :ticket-stats="ticketStats"
            :initial-month="filters.month"
            :initial-year="filters.year"
            />
            <div class="px-4 md:px-10 mx-auto w-full -m-24">
                <main >
                    <slot />
                </main>
                <FooterAdmin />
            </div>
        </div>
    </div>
</template> -->
