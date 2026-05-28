<script setup>
import { Avatar } from "@/components/ui/avatar";

const u = { name: "Isabella Nguyen" };
</script>

<template>
  <div class="flex items-center gap-3">
    <Avatar :model="u" rounded="rounded-md" class="size-10" />
    <Avatar :model="u" rounded="rounded-lg" class="size-10" />
    <Avatar :model="u" rounded="rounded-xl" class="size-10" />
    <Avatar :model="u" circle class="size-10" />
  </div>
</template>
