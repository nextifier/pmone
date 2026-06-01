<script setup lang="ts">
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";
import { useSliderWithInput } from "@/composables/useSliderWithInput";

const props = defineProps<{
  label: string;
  minValue: number;
  maxValue: number;
  initialValue: number[];
  defaultValue: number[];
}>();

const {
  sliderValues,
  inputValues,
  validateAndUpdateValue,
  handleInputChange,
  handleSliderChange,
  resetToDefault,
} = useSliderWithInput(props);

defineExpose({ resetToDefault });
</script>

<template>
  <div class="flex items-center gap-2">
    <Label class="text-muted-foreground text-xs sm:text-sm">{{ label }}</Label>
    <Slider
      class="grow [&_[data-slot=slider-thumb]]:rounded-sm"
      :model-value="sliderValues"
      :min="minValue"
      :max="maxValue"
      :aria-label="label"
      @update:model-value="handleSliderChange"
    />
    <Input
      class="h-8 w-12 px-2 py-1 text-center"
      type="text"
      inputmode="decimal"
      :model-value="inputValues[0]"
      :aria-label="`${label} value`"
      @update:model-value="(newValue) => handleInputChange(0, newValue)"
      @blur="() => validateAndUpdateValue(inputValues[0] ?? '', 0)"
      @keydown.enter="validateAndUpdateValue(inputValues[0] ?? '', 0)"
    />
  </div>
</template>
