<script setup>
import { ScrollArea } from "@/components/ui/scroll-area";

const tags = Array.from({ length: 30 }, (_, i) => `Tag ${i + 1}`);
</script>

<template>
  <ScrollArea class="h-56 w-48 rounded-md border p-4">
    <div class="flex flex-col gap-2 text-sm tracking-tight">
      <div v-for="t in tags" :key="t">{{ t }}</div>
    </div>
  </ScrollArea>
</template>
