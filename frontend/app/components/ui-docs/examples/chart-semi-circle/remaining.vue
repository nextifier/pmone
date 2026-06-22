<script setup>
import { ChartSemiCircle } from "@/components/ui/chart";
</script>

<template>
  <ClientOnly>
    <div class="w-full max-w-[220px]">
      <ChartSemiCircle :value="1240" :max="2000" show-remaining center-label="Credits remaining" />
    </div>
  </ClientOnly>
</template>
