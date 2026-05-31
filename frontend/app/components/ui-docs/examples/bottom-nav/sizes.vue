<script setup>
import { BottomNav, BottomNavItem } from "@/components/ui/bottom-nav";

const sizes = ["sm", "md", "lg"];
</script>

<template>
  <div class="flex flex-col gap-4">
    <div
      v-for="size in sizes"
      :key="size"
      class="relative mx-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-background"
    >
      <p class="text-muted-foreground px-4 pt-3 text-sm tracking-tight">
        size="{{ size }}"
      </p>
      <BottomNav position="static" :size="size">
        <BottomNavItem icon="hugeicons:home-09" label="Home" active />
        <BottomNavItem icon="hugeicons:search-01" label="Search" />
        <BottomNavItem icon="hugeicons:message-01" label="Messages" />
        <BottomNavItem icon="hugeicons:user" label="Profile" />
      </BottomNav>
    </div>
  </div>
</template>
