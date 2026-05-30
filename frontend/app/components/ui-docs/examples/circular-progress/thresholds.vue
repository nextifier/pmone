<script setup>
import { CircularProgress } from "@/components/ui/circular-progress";
</script>

<template>
  <div class="flex items-center gap-6">
    <CircularProgress :value="22" :size="64" :stroke-width="6" />
    <CircularProgress :value="55" :size="64" :stroke-width="6" />
    <CircularProgress :value="88" :size="64" :stroke-width="6" />
  </div>
</template>
