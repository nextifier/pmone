<script setup>
import { AvatarGroup } from "@/components/ui/avatar-group";

const team = [
  { id: 1, name: "Olivia Martin" },
  { id: 2, name: "Jackson Lee" },
  { id: 3, name: "Isabella Nguyen" },
  { id: 4, name: "Sofia Davis" },
];
</script>

<template>
  <AvatarGroup :items="team" />
</template>
