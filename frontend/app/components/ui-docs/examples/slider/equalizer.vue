<script setup>
import { reactive } from "vue";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const values = reactive({
  "60Hz": [2],
  "250Hz": [1],
  "1kHz": [-1],
  "4kHz": [-3],
  "16kHz": [2],
});

const bands = [
  { key: "60Hz", label: "60" },
  { key: "250Hz", label: "250" },
  { key: "1kHz", label: "1k" },
  { key: "4kHz", label: "4k" },
  { key: "16kHz", label: "16k" },
];
</script>

<template>
  <div class="space-y-4">
    <legend class="text-foreground text-sm font-medium tracking-tight">Equalizer</legend>
    <div class="flex h-48 justify-center gap-8">
      <div v-for="band in bands" :key="band.key" class="flex flex-col items-center gap-2">
        <Slider
          v-model="values[band.key]"
          :min="-5"
          :max="5"
          orientation="vertical"
          show-tooltip
          class="[&_[data-slot=slider-thumb]]:h-6 [&_[data-slot=slider-thumb]]:w-4 [&_[data-slot=slider-thumb]]:rounded-sm"
          :aria-label="`${band.label} band`"
        />
        <Label class="text-muted-foreground flex w-0 justify-center text-xs">{{ band.label }}</Label>
      </div>
    </div>
  </div>
</template>
