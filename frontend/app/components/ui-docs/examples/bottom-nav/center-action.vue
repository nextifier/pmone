<script setup lang="ts">
import { BottomNav, BottomNavItem, BottomNavAction } from "@/components/ui/bottom-nav";
</script>

<template>
  <div class="relative mx-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-background">
    <div class="grid h-40 place-items-center text-sm tracking-tight text-muted-foreground">
      App content
    </div>
    <BottomNav position="static" indicator="pill">
      <BottomNavItem icon="hugeicons:home-09" label="Home" active />
      <BottomNavItem icon="hugeicons:search-01" label="Search" />
      <BottomNavAction icon="hugeicons:add-01" label="Create" />
      <BottomNavItem icon="hugeicons:message-01" label="Messages" />
      <BottomNavItem icon="hugeicons:user-circle" label="Profile" />
    </BottomNav>
  </div>
</template>
