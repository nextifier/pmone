<template>
  <div class="flex flex-col gap-y-0">
    <TabNav :tabs="opsTabs" />

    <div class="pt-6">
      <NuxtPage :event="event" :project="project" />
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const opsBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/operational`
);
const opsTabs = computed(() => [
  { label: "Orders", icon: "hugeicons:shopping-bag-01", to: `${opsBase.value}/orders` },
  { label: "Products", icon: "hugeicons:package-01", to: `${opsBase.value}/products` },
  { label: "Order Form Settings", icon: "hugeicons:settings-02", to: `${opsBase.value}/order-form-settings` },
]);
</script>
