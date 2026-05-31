<script setup>
import { BottomNav, BottomNavItem } from "@/components/ui/bottom-nav";

const frames = [
  { display: "always", caption: "always" },
  { display: "active", caption: "active" },
  { display: "none", caption: "none" },
];
</script>

<template>
  <div class="grid w-full gap-4 sm:grid-cols-3">
    <div v-for="frame in frames" :key="frame.display" class="flex flex-col gap-2">
      <p class="text-muted-foreground text-sm tracking-tight">{{ frame.caption }}</p>
      <div class="relative mx-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-background">
        <BottomNav position="static" :label-display="frame.display">
          <BottomNavItem icon="hugeicons:home-09" label="Home" active />
          <BottomNavItem icon="hugeicons:search-01" label="Search" />
          <BottomNavItem icon="hugeicons:message-02" label="Messages" />
          <BottomNavItem icon="hugeicons:user-circle" label="Profile" />
        </BottomNav>
      </div>
    </div>
  </div>
</template>
