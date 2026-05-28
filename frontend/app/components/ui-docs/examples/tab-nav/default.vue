<script setup>
import { TabNav } from "@/components/ui/tab-nav";

const tabs = [
  { to: "/settings/account", label: "Account" },
  { to: "/settings/notifications", label: "Notifications" },
  { to: "/settings/security", label: "Security" },
  { to: "/settings/billing", label: "Billing" },
];
</script>

<template>
  <TabNav :tabs="tabs" class="w-full" />
</template>
