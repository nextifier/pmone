<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([2, 7]);
</script>

<template>
  <div class="space-y-4">
    <Label>Vertical dual range slider and tooltip</Label>
    <div class="flex h-40 justify-center">
      <Slider
        v-model="value"
        :max="10"
        orientation="vertical"
        show-tooltip
        aria-label="Vertical dual range slider"
      />
    </div>
  </div>
</template>
