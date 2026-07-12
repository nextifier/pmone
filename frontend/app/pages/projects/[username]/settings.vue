<template>
  <div class="flex flex-col">
    <TabNav :tabs="settingsTabs" />

    <div class="mx-auto w-full max-w-2xl pt-6">
      <NuxtPage :project="project" />
    </div>
  </div>
</template>

<script setup>
import { TabNav } from "@/components/ui/tab-nav";
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
      label: "Website Settings",
      icon: "hugeicons:globe-02",
      to: `${settingsBase.value}/website-settings`,
    },
    {
      label: "Legal Pages",
      icon: "hugeicons:legal-document-01",
      to: `${settingsBase.value}/legal-pages`,
    },
    {
      label: "SEO Meta",
      icon: "hugeicons:seo",
      to: `${settingsBase.value}/seo-meta`,
    },
    {
      label: "OG Images",
      icon: "hugeicons:image-02",
      to: `${settingsBase.value}/og-images`,
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
