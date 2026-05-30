<script setup>
import { CircularProgress } from "@/components/ui/circular-progress";
</script>

<template>
  <div class="flex items-end gap-6">
    <CircularProgress :value="72" :size="40" :stroke-width="4" />
    <CircularProgress :value="72" :size="64" :stroke-width="6" />
    <CircularProgress :value="72" :size="96" :stroke-width="8" />
  </div>
</template>
