<template>
  <AllotmentsPanel :event-id="event.id" :hotel-slug="hotelSlug" />
</template>

<script setup>
import AllotmentsPanel from "@/components/hotel/AllotmentsPanel.vue";

defineProps({
  event: Object,
  project: Object,
  hotel: Object,
});

const route = useRoute();
const hotelSlug = computed(() => route.params.hotelSlug);

usePageMeta(null, { title: "Allotments · Hotel" });
</script>
