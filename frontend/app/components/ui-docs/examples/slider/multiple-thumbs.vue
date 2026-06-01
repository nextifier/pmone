<script setup>
import { ref } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const value = ref([25, 50, 100]);

const formatTooltip = (val) => `${val}%`;
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <Label>Slider with multiple thumbs</Label>
    <Slider
      v-model="value"
      :show-tooltip="true"
      :tooltip-content="formatTooltip"
      aria-label="Slider with multiple thumbs"
    />
  </div>
</template>
