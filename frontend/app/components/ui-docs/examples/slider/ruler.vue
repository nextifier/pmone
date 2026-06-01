<script setup>
import { SliderRuler } from "@/components/ui/slider";
import { reactive } from "vue";

const fields = reactive([
  { key: "offsetX", label: "Offset X", value: 0, min: -100, max: 100, step: 1, default: 0 },
  { key: "offsetY", label: "Offset Y", value: 0, min: -100, max: 100, step: 1, default: 0 },
  { key: "rotation", label: "Rotation", value: 0, min: -180, max: 180, step: 1, default: 0 },
  { key: "scale", label: "Scale", value: 1, min: 0, max: 5, step: 0.1, default: 1 },
]);
</script>

<template>
  <div class="w-full max-w-sm space-y-2">
    <SliderRuler
      v-for="field in fields"
      :key="field.key"
      v-model="field.value"
      :label="field.label"
      :min="field.min"
      :max="field.max"
      :step="field.step"
      :default-value="field.default"
    />
  </div>
</template>
