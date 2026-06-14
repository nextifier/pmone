<template>
  <div class="flex flex-col gap-y-0">
    <TabNav :tabs="contentTabs" />

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

const contentBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/content`
);
const contentTabs = computed(() => [
  { label: "Rundown", icon: "hugeicons:time-schedule", to: `${contentBase.value}/rundown` },
  {
    label: "Guests / Speakers",
    icon: "hugeicons:user-multiple-02",
    to: `${contentBase.value}/guests`,
  },
  {
    label: "Programs",
    icon: "hugeicons:presentation-bar-chart-01",
    to: `${contentBase.value}/programs`,
  },
  { label: "FAQ", icon: "hugeicons:help-circle", to: `${contentBase.value}/faq` },
  { label: "Partners", icon: "hugeicons:dim-sum-02", to: `${contentBase.value}/partners` },
  { label: "Media Coverage", icon: "hugeicons:news", to: `${contentBase.value}/media-coverage` },
  { label: "Gallery", icon: "hugeicons:image-02", to: `${contentBase.value}/gallery` },
]);
</script>
