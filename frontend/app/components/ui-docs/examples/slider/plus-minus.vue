<script setup>
import { ref } from "vue";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Slider } from "@/components/ui/slider";

const minValue = 0;
const maxValue = 200;
const steps = 5;
const value = ref([100]);

function decreaseValue() {
  const current = value.value?.[0] ?? minValue;
  value.value = [Math.max(minValue, current - steps)];
}

function increaseValue() {
  const current = value.value?.[0] ?? minValue;
  value.value = [Math.min(maxValue, current + steps)];
}
</script>

<template>
  <div class="w-full max-w-sm space-y-3">
    <Label class="tabular-nums">{{ value[0] || 0 }} credits/mo</Label>
    <div class="flex items-center gap-4">
      <Button
        variant="outline"
        size="iconSm"
        aria-label="Decrease value"
        :disabled="(value[0] || 0) === minValue"
        @click="decreaseValue"
      >
        <Icon name="hugeicons:minus-sign" class="size-4" aria-hidden="true" />
      </Button>
      <Slider
        v-model="value"
        class="grow"
        :min="minValue"
        :max="maxValue"
        :step="steps"
        aria-label="Credits slider"
      />
      <Button
        variant="outline"
        size="iconSm"
        aria-label="Increase value"
        :disabled="(value[0] || 0) === maxValue"
        @click="increaseValue"
      >
        <Icon name="hugeicons:plus-sign" class="size-4" aria-hidden="true" />
      </Button>
    </div>
  </div>
</template>
