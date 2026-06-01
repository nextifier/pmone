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
  <div class="w-full max-w-sm space-y-3">
    <Label>Slider with input</Label>
    <div class="flex items-center gap-4">
      <Slider
        class="grow"
        :model-value="sliderValues"
        :min="minValue"
        :max="maxValue"
        aria-label="Slider with input"
        @update:model-value="handleSliderChange"
      />
      <Input
        class="h-8 w-12 px-2 py-1"
        type="text"
        inputmode="decimal"
        :model-value="inputValues[0]"
        @update:model-value="(newValue) => handleInputChange(0, newValue)"
        @blur="() => validateAndUpdateValue(inputValues[0] ?? '', 0)"
        @keydown.enter="validateAndUpdateValue(inputValues[0] ?? '', 0)"
      />
    </div>
  </div>
</template>
