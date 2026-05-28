<script setup>
import { Toggle } from "@/components/ui/toggle";
</script>

<template>
  <div class="flex items-center gap-2">
    <Toggle variant="default" aria-label="Bold">
      <Icon name="lucide:bold" />
    </Toggle>
    <Toggle variant="outline" aria-label="Underline">
      <Icon name="lucide:underline" />
    </Toggle>
  </div>
</template>
