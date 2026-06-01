<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([25]);
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Slider with tiny thumb</Label>
    <Slider
      v-model="value"
      class="[&_[data-slot=slider-thumb]]:border-background [&_[data-slot=slider-thumb]]:bg-primary [&_[data-slot=slider-thumb]]:h-6 [&_[data-slot=slider-thumb]]:w-2.5 [&_[data-slot=slider-thumb]]:border-[3px] [&_[data-slot=slider-thumb]]:shadow-none [&_[data-slot=slider-thumb]]:ring-offset-0"
      aria-label="Slider with tiny thumb"
    />
  </div>
</template>
