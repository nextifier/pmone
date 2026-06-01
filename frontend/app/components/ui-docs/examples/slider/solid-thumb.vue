<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([25]);
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Slider with solid thumb</Label>
    <Slider
      v-model="value"
      class="[&_[data-slot=slider-thumb]]:bg-primary [&_[data-slot=slider-track]]:opacity-70"
      aria-label="Slider with solid thumb"
    />
  </div>
</template>
