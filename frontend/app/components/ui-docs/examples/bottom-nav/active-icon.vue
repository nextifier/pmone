<script setup>
import { BottomNav, BottomNavItem } from "@/components/ui/bottom-nav";
</script>

<template>
  <div class="relative mx-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-background">
    <BottomNav position="static" indicator="none">
      <BottomNavItem icon="hugeicons:home-09" active-icon="hugeicons:home-05" label="Home" active />
      <BottomNavItem icon="hugeicons:search-01" active-icon="hugeicons:search-02" label="Search" />
      <BottomNavItem icon="hugeicons:favourite" active-icon="hugeicons:favourite-circle" label="Saved" />
      <BottomNavItem icon="hugeicons:user-circle" active-icon="hugeicons:user" label="Profile" />
    </BottomNav>
  </div>
</template>
