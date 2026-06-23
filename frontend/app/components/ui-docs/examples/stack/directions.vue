<script setup>
import { Stack } from "@/components/ui/stack";

const directions = [
  { id: "br-tl", label: "br → tl" },
  { id: "bl-tr", label: "bl → tr" },
  { id: "tl-br", label: "tl → br" },
  { id: "tr-bl", label: "tr → bl" },
  { id: "b-t", label: "b → t" },
  { id: "t-b", label: "t → b" },
];
</script>

<template>
  <div class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3">
    <div v-for="d in directions" :key="d.id" class="flex flex-col items-center gap-1">
      <Stack :direction="d.id" class="text-muted-foreground">
        <Icon name="hugeicons:layers-01" class="size-7" />
      </Stack>
      <span class="text-muted-foreground font-mono text-sm tracking-tight">{{ d.label }}</span>
    </div>
  </div>
</template>
