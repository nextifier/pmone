<template>
  <div class="flex flex-col gap-y-0">
    <TabNav :tabs="contentTabs" />

    <div ref="contentArea" class="pt-6">
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

const contentArea = ref(null);

const contentBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/content`
);
const contentTabs = computed(() => [
  { label: "Rundown", icon: "hugeicons:time-schedule", to: `${contentBase.value}/rundown` },
  { label: "Tickets", icon: "hugeicons:ticket-01", to: `${contentBase.value}/tickets` },
  { label: "Programs", icon: "hugeicons:presentation-bar-chart-01", to: `${contentBase.value}/programs` },
  { label: "FAQ", icon: "hugeicons:help-circle", to: `${contentBase.value}/faq` },
  { label: "Partners", icon: "hugeicons:agreement-01", to: `${contentBase.value}/partners` },
  { label: "Gallery", icon: "hugeicons:image-02", to: `${contentBase.value}/gallery` },
]);

const eventTabs = inject("eventTabs");
useTabSwipe(contentArea, contentTabs, { parentTabs: eventTabs });
</script>
