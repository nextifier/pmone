<script setup>
import { ChartSemiCircle } from "@/components/ui/chart";
</script>

<template>
  <ClientOnly>
    <div class="h-48 w-full max-w-sm">
      <ChartSemiCircle :value="68" :max="100" label="Storage used" />
    </div>
  </ClientOnly>
</template>
