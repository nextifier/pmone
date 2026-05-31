<script setup>
import { BottomNav, BottomNavItem } from "@/components/ui/bottom-nav";

const items = [
  { icon: "hugeicons:home-09", label: "Home" },
  { icon: "hugeicons:search-01", label: "Search" },
  { icon: "hugeicons:notification-02", label: "Activity" },
  { icon: "hugeicons:user", label: "Profile" },
];
</script>

<template>
  <div class="grid w-full gap-4 sm:grid-cols-2">
    <div
      v-for="indicator in ['pill', 'bar', 'dot', 'none']"
      :key="indicator"
      class="flex flex-col gap-2"
    >
      <span class="text-muted-foreground text-sm tracking-tight">{{ indicator }}</span>
      <div class="relative mx-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-background">
        <BottomNav position="static" :indicator="indicator">
          <BottomNavItem
            v-for="(item, index) in items"
            :key="item.label"
            :icon="item.icon"
            :label="item.label"
            :active="index === 0"
          />
        </BottomNav>
      </div>
    </div>
  </div>
</template>
