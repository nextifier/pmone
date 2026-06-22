<script setup>
import { ChartSemiCircle } from "@/components/ui/chart";
</script>

<template>
  <ClientOnly>
    <div class="w-full max-w-[220px]">
      <ChartSemiCircle :value="68" :max="100" center-label="Storage used" show-max />
    </div>
  </ClientOnly>
</template>
