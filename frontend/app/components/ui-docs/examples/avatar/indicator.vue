<script setup>
import { Avatar } from "@/components/ui/avatar";

const u = { name: "Jackson Lee" };
</script>

<template>
  <div class="flex items-center gap-3">
    <Avatar :model="u" indicator="success" class="size-10" />
    <Avatar :model="u" indicator="warning" class="size-10" />
    <Avatar :model="u" indicator="destructive" class="size-10" />
    <Avatar :model="u" indicator="info" class="size-10" />
  </div>
</template>
