<script setup>
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { useSliderWithInput } from "@/composables/useSliderWithInput";

const minValue = 0;
const maxValue = 2;
const initialValue = [1.25];
const defaultValue = [1];

const {
  sliderValues,
  inputValues,
  validateAndUpdateValue,
  handleInputChange,
  handleSliderChange,
  resetToDefault,
  showReset,
} = useSliderWithInput({ minValue, maxValue, initialValue, defaultValue });
</script>

<template>
  <div class="w-full max-w-sm space-y-3">
    <div class="flex items-center justify-between gap-2">
      <Label>Temperature</Label>
      <div class="flex items-center gap-1">
        <TooltipProvider :delay-duration="0">
          <Tooltip>
            <TooltipTrigger as-child>
              <Button
                variant="ghost"
                size="iconSm"
                :class="cn('size-7 transition-opacity', showReset ? 'opacity-100' : 'opacity-0')"
                aria-label="Reset"
                @click="resetToDefault"
              >
                <Icon name="hugeicons:refresh" class="size-4" aria-hidden="true" />
              </Button>
            </TooltipTrigger>
            <TooltipContent class="px-2 py-1 text-xs">Reset to default</TooltipContent>
          </Tooltip>
        </TooltipProvider>
        <Input
          class="h-7 w-12 px-2 py-0"
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
    <Slider
      :model-value="sliderValues"
      :min="minValue"
      :max="maxValue"
      :step="0.01"
      aria-label="Temperature"
      @update:model-value="handleSliderChange"
    />
  </div>
</template>
