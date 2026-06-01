<script setup>
import { useTemplateRef } from "vue";
import { Button } from "@/components/ui/button";
import { SliderWithInput } from "@/components/ui/slider";

const sliders = [
  { label: "X", minValue: -10, maxValue: 10, initialValue: [-2], defaultValue: [0] },
  { label: "Y", minValue: -10, maxValue: 10, initialValue: [4], defaultValue: [0] },
  { label: "Z", minValue: -10, maxValue: 10, initialValue: [2], defaultValue: [0] },
];

const slidersRefs = useTemplateRef("slidersRefs");

function resetAll() {
  slidersRefs.value?.forEach((slider) => slider?.resetToDefault());
}
</script>

<template>
  <div class="w-full max-w-sm space-y-4">
    <legend class="text-foreground text-sm font-medium tracking-tight">Object position</legend>
    <div class="space-y-2">
      <SliderWithInput
        v-for="slider in sliders"
        ref="slidersRefs"
        :key="slider.label"
        :label="slider.label"
        :min-value="slider.minValue"
        :max-value="slider.maxValue"
        :initial-value="slider.initialValue"
        :default-value="slider.defaultValue"
      />
    </div>
    <Button class="w-full" variant="outline" @click="resetAll">
      <Icon name="hugeicons:refresh" class="-ms-1 size-4 opacity-60" aria-hidden="true" />
      Reset
    </Button>
  </div>
</template>
