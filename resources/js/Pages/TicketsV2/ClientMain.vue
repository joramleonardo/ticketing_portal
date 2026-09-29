<template>
  <div class="relative min-h-screen flex flex-col justify-between bg-slate-900 overflow-x-hidden">
    <!-- Single static background layer (avoids fixed-background repaint lag during scroll) -->
    <div
      class="pointer-events-none absolute inset-0 bg-no-repeat bg-cover bg-center opacity-35"
      :style="`background-image: url('${registerBg2}');`"
    ></div>

    <!-- Main Portal Content -->
    <main class="relative z-10 flex-1 flex items-center justify-center py-8 sm:py-12 px-4">
      <ClientPick />
    </main>

    <!-- Footer (non-overlapping) -->
    <div class="relative z-10">
      <FooterSmall />
    </div>
  </div>
</template>
<script setup>
import FooterSmall from "@/Components/Footers/FooterSmall.vue";
import ClientPick from "@/Pages/TicketsV2/ClientComponents/ClientPick.vue";
import registerBg2 from "@/assets/img/register_bg_2.png";
</script>
