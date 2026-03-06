<template>
  <div class="flex flex-col gap-y-0">
    <TabNav :tabs="settingsTabs" />

    <div class="pt-6">
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

const settingsBase = computed(() => `/projects/${route.params.username}/settings`);
const settingsTabs = computed(() => [
  { label: "General", icon: "hugeicons:settings-01", to: settingsBase.value, exact: true },
  { label: "Members", icon: "hugeicons:user-group", to: `${settingsBase.value}/members` },
  { label: "Contact Form", icon: "hugeicons:mail-at-sign-01", to: `${settingsBase.value}/contact-form` },
  { label: "Brand Fields", icon: "hugeicons:structure-03", to: `${settingsBase.value}/brand-fields` },
]);
</script>
