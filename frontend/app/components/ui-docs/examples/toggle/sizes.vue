<script setup>
import { Toggle } from "@/components/ui/toggle";
</script>

<template>
  <div class="flex items-center gap-2">
    <Toggle size="sm" aria-label="Bold sm">
      <Icon name="lucide:bold" />
    </Toggle>
    <Toggle size="default" aria-label="Bold default">
      <Icon name="lucide:bold" />
    </Toggle>
    <Toggle size="lg" aria-label="Bold lg">
      <Icon name="lucide:bold" />
    </Toggle>
  </div>
</template>
