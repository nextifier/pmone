<script setup lang="ts">
import { BottomNav, BottomNavItem } from "@/components/ui/bottom-nav";
import { ref } from "vue";

const scrollRegion = ref<HTMLElement | null>(null);
</script>

<template>
  <div class="relative mx-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-background">
    <div ref="scrollRegion" class="h-64 overflow-y-auto">
      <div class="space-y-3 p-4">
        <p class="text-sm tracking-tight text-muted-foreground">
          Scroll down to tuck the bar away, scroll up to reveal it again.
        </p>
        <div v-for="row in 14" :key="row" class="h-12 rounded-lg bg-muted" />
      </div>
    </div>
    <BottomNav position="static" hide-on-scroll :scroll-target="scrollRegion">
      <BottomNavItem icon="hugeicons:home-09" label="Home" active />
      <BottomNavItem icon="hugeicons:search-01" label="Search" />
      <BottomNavItem icon="hugeicons:activity-01" label="Activity" />
      <BottomNavItem icon="hugeicons:message-01" label="Messages" />
      <BottomNavItem icon="hugeicons:user-circle" label="Profile" />
    </BottomNav>
  </div>
</template>
