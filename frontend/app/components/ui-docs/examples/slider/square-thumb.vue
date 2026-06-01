<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([25]);
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Slider with square thumb</Label>
    <Slider
      v-model="value"
      :max="100"
      :step="10"
      class="[&_[data-slot=slider-thumb]]:rounded-sm"
      aria-label="Slider with square thumb"
    />
  </div>
</template>
