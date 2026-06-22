<script setup>
import { ChartSemiCircle } from "@/components/ui/chart";
</script>

<template>
  <ClientOnly>
    <div class="w-full max-w-[220px]">
      <ChartSemiCircle
        :value="82"
        :max="100"
        gradient="from-sky-500 via-indigo-500 to-fuchsia-500"
        center-label="Performance"
        suffix="%"
      />
    </div>
  </ClientOnly>
</template>
