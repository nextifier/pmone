<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-6xl">
    <div class="space-y-1">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:user-circle" class="size-5 sm:size-6" />
        <h1 class="page-title">Account</h1>
      </div>
      <p class="page-description">Manage your tickets, orders, and profile.</p>
    </div>

    <TabNav :tabs="tabs" />

    <NuxtPage />
  </div>
</template>

<script setup>
import { TabNav } from "@/components/ui/tabs";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const tabs = [
  { label: "Tickets", icon: "hugeicons:ticket-01", to: "/account/tickets" },
  { label: "Orders", icon: "hugeicons:shopping-bag-02", to: "/account/orders" },
  { label: "Profile", icon: "hugeicons:user-circle", to: "/account/profile" },
];

usePageMeta(null, { title: "Account" });
</script>
