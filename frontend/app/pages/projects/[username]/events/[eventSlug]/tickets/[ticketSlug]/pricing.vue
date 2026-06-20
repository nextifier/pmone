<template>
  <PricePhasesPanel v-if="ticket" :event="event" :ticket="ticket" />
</template>

<script setup>
import PricePhasesPanel from "@/components/ticket/PricePhasesPanel.vue";

defineProps({
  event: Object,
  project: Object,
  ticket: Object,
});

usePageMeta(null, { title: "Pricing · Ticket" });
</script>
