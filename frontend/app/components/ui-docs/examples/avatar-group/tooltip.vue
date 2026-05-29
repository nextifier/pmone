<script setup>
import { AvatarGroup } from "@/components/ui/avatar-group";

const team = [
  { id: 1, name: "Olivia Martin" },
  { id: 2, name: "Jackson Lee" },
  { id: 3, name: "Isabella Nguyen" },
  { id: 4, name: "Sofia Davis" },
  { id: 5, name: "Liam Brown" },
];
</script>

<template>
  <div class="flex flex-col items-center gap-6 sm:flex-row sm:gap-10">
    <AvatarGroup :items="team" :max="4" />
    <AvatarGroup :items="team" :max="4" :show-tooltip="false" />
  </div>
</template>
