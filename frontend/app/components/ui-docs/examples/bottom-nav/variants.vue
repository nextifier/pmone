<script setup lang="ts">
import { BottomNav, BottomNavItem } from "@/components/ui/bottom-nav";

const variants = ["default", "floating", "glass"] as const;
</script>

<template>
  <div class="flex flex-col gap-6">
    <div v-for="variant in variants" :key="variant" class="flex flex-col gap-2">
      <p class="text-sm font-medium tracking-tight text-muted-foreground">
        {{ variant }}
      </p>
      <div class="relative mx-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-background">
        <div class="grid h-32 place-items-center text-sm tracking-tight text-muted-foreground">
          App content
        </div>
        <BottomNav position="static" :variant="variant">
          <BottomNavItem icon="hugeicons:home-09" label="Home" active />
          <BottomNavItem icon="hugeicons:search-01" label="Search" />
          <BottomNavItem icon="hugeicons:activity-01" label="Activity" />
          <BottomNavItem icon="hugeicons:message-01" label="Messages" />
          <BottomNavItem icon="hugeicons:user-circle" label="Profile" />
        </BottomNav>
      </div>
    </div>
  </div>
</template>
