<script setup>
import { ScrollArea, ScrollBar } from "@/components/ui/scroll-area";
</script>

<template>
  <ScrollArea class="w-full max-w-md whitespace-nowrap rounded-md border">
    <div class="flex w-max gap-4 p-4">
      <div v-for="i in 12" :key="i" class="bg-muted size-28 shrink-0 rounded-md" />
    </div>
    <ScrollBar orientation="horizontal" />
  </ScrollArea>
</template>
