<script setup>
import { Rating } from "@/components/ui/rating";
</script>

<template>
  <div class="flex flex-col items-center gap-4">
    <Rating :model-value="3.5" readonly size="sm" aria-label="Small" />
    <Rating :model-value="3.5" readonly size="default" aria-label="Default" />
    <Rating :model-value="3.5" readonly size="lg" aria-label="Large" />
  </div>
</template>
