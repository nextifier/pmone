<script setup>
import { Avatar } from "@/components/ui/avatar";

const users = [
  { name: "Olivia Martin" },
  { name: "Jackson Lee" },
  { name: "Isabella Nguyen" },
];
</script>

<template>
  <div class="flex items-center gap-3">
    <Avatar v-for="u in users" :key="u.name" :model="u" class="size-10" />
  </div>
</template>
