<script setup>
import { computed } from "vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";
import { useSliderWithInput } from "@/composables/useSliderWithInput";

const prices = [
  80, 95, 110, 125, 130, 140, 145, 150, 155, 165, 175, 185, 195, 205, 215, 225, 235, 245, 255, 260,
  265, 270, 275, 280, 285, 290, 290, 295, 295, 295, 298, 299, 300, 305, 310, 315, 320, 325, 330,
  335, 340, 345, 350, 355, 360, 365, 365, 375, 380, 385, 390, 395, 400, 405, 410, 415, 420, 425,
  430, 435, 440, 445, 450, 455, 460, 465, 470, 475, 480, 485, 490, 495, 495, 498, 499, 500, 500,
  500, 515, 530, 545, 560, 575, 590, 605, 620, 635, 650, 655, 660, 665, 670, 675, 680, 685, 690,
  695, 700, 700, 700, 700, 700, 700, 725, 750, 775, 800, 815, 830, 845, 845, 845, 870, 875, 880,
  885, 890, 895, 898, 900,
];
const items = prices.map((price, index) => ({ id: index + 1, price }));

const tickCount = 40;
const minValue = Math.min(...prices);
const maxValue = Math.max(...prices);
const priceStep = computed(() => (maxValue - minValue) / tickCount);

const { sliderValues, inputValues, validateAndUpdateValue, handleSliderChange, handleInputChange } =
  useSliderWithInput({ minValue, maxValue, initialValue: [200, 780] });

const itemCounts = computed(() =>
  Array(tickCount)
    .fill(0)
    .map((_, tick) => {
      const rangeMin = minValue + tick * priceStep.value;
      const rangeMax = minValue + (tick + 1) * priceStep.value;
      return items.filter((item) => item.price >= rangeMin && item.price < rangeMax).length;
    }),
);

const maxCount = computed(() => (itemCounts.value.length ? Math.max(...itemCounts.value) : 0));

function countItemsInRange(min, max) {
  return items.filter((item) => item.price >= min && item.price <= max).length;
}

function isBarInSelectedRange(index) {
  const rangeMin = minValue + index * priceStep.value;
  const rangeMax = minValue + (index + 1) * priceStep.value;
  const [min, max] = sliderValues.value;
  if (!min || !max) {
    return false;
  }
  return countItemsInRange(min, max) > 0 && rangeMin <= max && rangeMax >= min;
}
</script>

<template>
  <div class="w-full max-w-md space-y-4">
    <Label>Price slider</Label>
    <div>
      <div class="flex h-12 w-full items-end px-3" aria-hidden="true">
        <div
          v-for="(count, i) in itemCounts"
          :key="i"
          class="flex flex-1 justify-center"
          :style="{ height: `${(count / maxCount) * 100}%` }"
        >
          <span
            :data-selected="isBarInSelectedRange(i)"
            class="bg-primary/20 data-[selected=true]:bg-primary/50 h-full w-full transition-colors"
          />
        </div>
      </div>
      <Slider
        :model-value="sliderValues"
        :min="minValue"
        :max="maxValue"
        aria-label="Price range"
        @update:model-value="handleSliderChange"
      />
    </div>

    <div class="flex items-center justify-between gap-4">
      <div class="flex-1 space-y-1">
        <Label for="min-price">Min price</Label>
        <div class="relative">
          <Input
            id="min-price"
            class="peer w-full ps-6"
            type="text"
            inputmode="decimal"
            :model-value="inputValues[0]"
            aria-label="Enter minimum price"
            @update:model-value="(value) => handleInputChange(0, value)"
            @blur="() => validateAndUpdateValue(inputValues[0] ?? '', 0)"
            @keydown.enter="validateAndUpdateValue(inputValues[0] ?? '', 0)"
          />
          <span
            class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center ps-3 text-sm peer-disabled:opacity-50"
          >
            $
          </span>
        </div>
      </div>
      <div class="flex-1 space-y-1">
        <Label for="max-price">Max price</Label>
        <div class="relative">
          <Input
            id="max-price"
            class="peer w-full ps-6"
            type="text"
            inputmode="decimal"
            :model-value="inputValues[1]"
            aria-label="Enter maximum price"
            @update:model-value="(value) => handleInputChange(1, value)"
            @blur="() => validateAndUpdateValue(inputValues[1] ?? '', 1)"
            @keydown.enter="validateAndUpdateValue(inputValues[1] ?? '', 1)"
          />
          <span
            class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center justify-center ps-3 text-sm peer-disabled:opacity-50"
          >
            $
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
