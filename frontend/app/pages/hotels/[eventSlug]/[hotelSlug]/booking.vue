<script setup>
definePageMeta({ layout: "default" });

const route = useRoute();
const eventSlug = route.params.eventSlug;
const hotelSlug = route.params.hotelSlug;

// V3 wizard consolidates detail + booking flow.
// Redirect /booking → root page with step=3 (guest info).
await navigateTo({
  path: `/hotels/${eventSlug}/${hotelSlug}`,
  query: { step: "3" },
});
</script>

<template>
  <div />
</template>
