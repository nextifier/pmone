<script setup>
import { Avatar } from "@/components/ui/avatar";

const user = {
  name: "Olivia Martin",
  profile_image: {
    sm: "https://i.pravatar.cc/80?img=47",
    md: "https://i.pravatar.cc/120?img=47",
  },
};
</script>

<template>
  <Avatar :model="user" size="md" class="size-12" circle />
</template>
