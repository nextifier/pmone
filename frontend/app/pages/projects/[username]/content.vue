<template>
  <div class="flex flex-col gap-y-0">
    <TabNav :tabs="contentTabs" />

    <div ref="contentArea" class="pt-6">
      <NuxtPage :project="project" />
    </div>
  </div>
</template>

<script setup>
import { TabNav } from "@/components/ui/tab-nav";

const props = defineProps({
  project: Object,
});

const route = useRoute();

const contentArea = ref(null);

const contentBase = computed(() => `/projects/${route.params.username}/content`);
const contentTabs = computed(() => [
  { label: "Banners", icon: "hugeicons:image-02", to: `${contentBase.value}/banners` },
]);

const projectTabs = inject("projectTabs", null);
useTabSwipe(contentArea, contentTabs, { parentTabs: projectTabs });
</script>
