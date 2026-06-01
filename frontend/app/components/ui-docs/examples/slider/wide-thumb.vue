<script setup>
import { ref } from "vue";
import { Slider } from "@/components/ui/slider";

const value = ref([60]);
</script>

<template>
  <div class="flex w-full max-w-sm items-center gap-3">
    <span class="text-muted-foreground text-xs font-medium tracking-tight sm:text-sm">OPEN</span>
    <Slider
      v-model="value"
      :max="100"
      :step="1"
      aria-label="Roller shade position"
      class="[&_[data-slot=slider-thumb]]:h-5 [&_[data-slot=slider-thumb]]:w-8 [&_[data-slot=slider-thumb]]:rounded-md"
    />
    <span class="text-muted-foreground text-xs font-medium tracking-tight sm:text-sm">CLOSE</span>
  </div>
</template>
