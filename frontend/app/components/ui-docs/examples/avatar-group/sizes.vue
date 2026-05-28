<script setup>
import { AvatarGroup } from "@/components/ui/avatar-group";

const team = [
  { id: 1, name: "Olivia Martin" },
  { id: 2, name: "Jackson Lee" },
  { id: 3, name: "Isabella Nguyen" },
];
</script>

<template>
  <div class="flex flex-col gap-4">
    <AvatarGroup :items="team" :size="1.75" />
    <AvatarGroup :items="team" :size="2.5" />
    <AvatarGroup :items="team" :size="3.5" :overlap="0.3" />
  </div>
</template>
