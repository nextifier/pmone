<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";
import { useSliderWithInput } from "@/composables/useSliderWithInput";

const minValue = 0;
const maxValue = 200;
const initialValue = [50, 150];

const { sliderValues, inputValues, validateAndUpdateValue, handleInputChange, handleSliderChange } =
  useSliderWithInput({ minValue, maxValue, initialValue });
</script>

<template>
  <div class="w-full max-w-sm space-y-3">
    <Label>Dual range slider with input</Label>
    <div class="flex items-center gap-4">
      <Input
        class="h-8 w-16 px-2 py-1 text-center"
        type="text"
        inputmode="decimal"
        :model-value="inputValues[0]"
        @update:model-value="(newValue) => handleInputChange(0, newValue)"
        @blur="() => validateAndUpdateValue(inputValues[0] ?? '', 0)"
        @keydown.enter="validateAndUpdateValue(inputValues[0] ?? '', 0)"
      />
      <Slider
        class="grow"
        :model-value="sliderValues"
        :min="minValue"
        :max="maxValue"
        aria-label="Dual range slider with input"
        @update:model-value="handleSliderChange"
      />
      <Input
        class="h-8 w-16 px-2 py-1 text-center"
        type="text"
        inputmode="decimal"
        :model-value="inputValues[1]"
        @update:model-value="(newValue) => handleInputChange(1, newValue)"
        @blur="() => validateAndUpdateValue(inputValues[1] ?? '', 1)"
        @keydown.enter="validateAndUpdateValue(inputValues[1] ?? '', 1)"
      />
    </div>
  </div>
</template>
