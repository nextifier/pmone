<template>
  <TransferOptionsPanel :event-id="event.id" :hotel-slug="hotelSlug" />
</template>

<script setup>
import TransferOptionsPanel from "@/components/hotel/TransferOptionsPanel.vue";

defineProps({
  event: Object,
  project: Object,
  hotel: Object,
});

const route = useRoute();
const hotelSlug = computed(() => route.params.hotelSlug);

usePageMeta(null, { title: "Transfers · Hotel" });
</script>
