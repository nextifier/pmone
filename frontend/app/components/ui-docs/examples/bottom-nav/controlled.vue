<script setup>
import { BottomNav, BottomNavItem } from "@/components/ui/bottom-nav";
import { ref } from "vue";

const active = ref("home");
</script>

<template>
  <div class="relative mx-auto w-full max-w-sm overflow-hidden rounded-2xl border bg-background">
    <div class="grid h-40 place-items-center text-sm tracking-tight text-muted-foreground">
      Selected: {{ active }}
    </div>
    <BottomNav v-model="active" position="static" indicator="pill">
      <BottomNavItem value="home" icon="hugeicons:home-09" label="Home" />
      <BottomNavItem value="search" icon="hugeicons:search-01" label="Search" />
      <BottomNavItem value="activity" icon="hugeicons:activity-01" label="Activity" />
      <BottomNavItem value="profile" icon="hugeicons:user-circle" label="Profile" />
    </BottomNav>
  </div>
</template>
