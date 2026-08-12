<template>
  <div class="flex flex-col">
    <TabNav :tabs="settingsTabs" />

    <!-- Settings forms read best at a single measure, so they are capped here
         rather than in each tab. A tab that puts a live preview beside its
         controls needs the whole column instead and opts out with
         `definePageMeta({ wideSettings: true })`. -->
    <div class="w-full pt-6" :class="route.meta.wideSettings ? '' : 'mx-auto max-w-2xl'">
      <NuxtPage :project="project" />
    </div>
  </div>
</template>

<script setup>
import { TabNav } from "@/components/ui/tabs";
definePageMeta({
  middleware: ["permission"],
  permissions: ["projects.update"],
});

const props = defineProps({
  project: Object,
});

const route = useRoute();

const { hasPermission } = usePermission();

const settingsBase = computed(() => `/projects/${route.params.username}/settings`);
const settingsTabs = computed(() => {
  const tabs = [
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
    {
      label: "SEO",
      icon: "hugeicons:seo",
      to: `${settingsBase.value}/seo`,
    },
  ];

  if (hasPermission("events.update_branding")) {
    tabs.push({
      label: "Branding",
      icon: "hugeicons:paint-board",
      to: `${settingsBase.value}/branding`,
    });
  }

  tabs.push({
    label: "Hotel Reservations",
    icon: "hugeicons:hotel-01",
    to: `${settingsBase.value}/hotel-reservations`,
  });

  if (hasPermission("payment_gateways.read")) {
    tabs.push({
      label: "Payment Gateways",
      icon: "hugeicons:credit-card",
      to: `${settingsBase.value}/payment-gateways`,
    });
  }

  return tabs;
});
</script>
