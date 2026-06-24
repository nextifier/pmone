<template>
  <div class="flex flex-col gap-y-0">
    <TabNav v-if="!isOrderDetail" :tabs="opsTabs" />

    <div class="pt-6">
      <NuxtPage :event="event" :project="project" />
    </div>
  </div>
</template>

<script setup>
import { TabNav } from "@/components/ui/tab-nav";
const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

// Hide the operational tab nav on the order detail page (it has its own back link).
const isOrderDetail = computed(() => !!route.params.ulid);

const opsBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/operational`
);
const opsTabs = computed(() => [
  { label: "Orders", icon: "hugeicons:shopping-bag-01", to: `${opsBase.value}/orders` },
  { label: "Products", icon: "hugeicons:package-01", to: `${opsBase.value}/products` },
  { label: "Order Form Settings", icon: "hugeicons:settings-02", to: `${opsBase.value}/order-form-settings` },
]);
</script>
