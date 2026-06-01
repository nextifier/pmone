<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";
import { useSliderWithInput } from "@/composables/useSliderWithInput";

const minValue = 0;
const maxValue = 100;
const initialValue = [25];

const { sliderValues, inputValues, validateAndUpdateValue, handleInputChange, handleSliderChange } =
  useSliderWithInput({ minValue, maxValue, initialValue });
</script>

<template>
  <div class="space-y-4">
    <Label>Vertical slider with input</Label>
    <div class="flex h-40 flex-col items-center justify-center gap-4">
      <Slider
        class="data-[orientation=vertical]:min-h-0"
        :model-value="sliderValues"
        :min="minValue"
        :max="maxValue"
        orientation="vertical"
        aria-label="Vertical slider with input"
        @update:model-value="handleSliderChange"
      />
      <Input
        class="h-8 w-11 px-2 py-1 text-center"
        type="text"
        inputmode="decimal"
        :model-value="inputValues[0]"
        aria-label="Enter value"
        @update:model-value="(newValue) => handleInputChange(0, newValue)"
        @blur="() => validateAndUpdateValue(inputValues[0] ?? '', 0)"
        @keydown.enter="validateAndUpdateValue(inputValues[0] ?? '', 0)"
      />
    </div>
  </div>
</template>
