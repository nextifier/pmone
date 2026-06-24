<script setup>
import { Rating } from "@/components/ui/rating";
</script>

<template>
  <div class="flex flex-col items-center gap-4">
    <Rating :model-value="4" readonly color="yellow" aria-label="Yellow" />
    <Rating :model-value="4" readonly color="primary" aria-label="Primary" />
    <Rating :model-value="4" readonly color="foreground" aria-label="Foreground" />
  </div>
</template>
