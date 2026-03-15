<template>
  <div class="flex flex-col">
    <TabNav :tabs="settingsTabs" />

    <div ref="contentArea" class="mx-auto w-full max-w-2xl pt-6">
      <NuxtPage :project="project" />
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const props = defineProps({
  project: Object,
});

const route = useRoute();

const contentArea = ref(null);

const settingsBase = computed(() => `/projects/${route.params.username}/settings`);
const settingsTabs = computed(() => [
  { label: "General", icon: "hugeicons:settings-01", to: settingsBase.value, exact: true },
  { label: "Members", icon: "hugeicons:user-group", to: `${settingsBase.value}/members` },
  {
    label: "Contact Form",
    icon: "hugeicons:mail-at-sign-01",
    to: `${settingsBase.value}/contact-form`,
  },
  {
    label: "Brand Fields",
    icon: "hugeicons:structure-03",
    to: `${settingsBase.value}/brand-fields`,
  },
]);

const projectTabs = inject("projectTabs");
useTabSwipe(contentArea, settingsTabs, { parentTabs: projectTabs });
</script>
