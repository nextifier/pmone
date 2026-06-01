<script setup>
import { Slider } from "@/components/ui/slider";
import { Input } from "@/components/ui/input";

/**
 * A ruler-style numeric control matching the shaders.com editor: a solid,
 * tick-marked track box with a bright vertical-bar thumb, paired with an
 * editable number box. Used for every range prop so all sliders look identical.
 */
const props = defineProps({
  label: { type: String, default: "" },
  modelValue: { type: [Number, String], default: 0 },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 100 },
  step: { type: Number, default: 1 },
});

const emit = defineEmits(["update:modelValue"]);

function fromSlider(value) {
  emit("update:modelValue", value?.[0] ?? props.min);
}

function fromInput(raw) {
  const n = Number.parseFloat(raw);
  if (!Number.isNaN(n)) emit("update:modelValue", n);
}
</script>

<template>
  <div class="space-y-1.5">
    <label v-if="label" class="text-muted-foreground block text-sm tracking-tight">{{ label }}</label>
    <div class="flex items-center gap-x-2.5">
      <div class="ruler border-input bg-muted/50 hover:border-ring/40 relative h-8 flex-1 overflow-hidden rounded-md border transition-colors">
        <Slider
          class="ruler-slider absolute inset-0 size-full px-2"
          :model-value="[Number(modelValue)]"
          :min="min"
          :max="max"
          :step="step"
          @update:model-value="fromSlider"
        />
      </div>
      <Input
        :model-value="modelValue"
        type="number"
        :min="min"
        :max="max"
        :step="step"
        class="h-8 w-16 text-right text-xs tabular-nums sm:text-sm"
        @update:model-value="fromInput"
      />
    </div>
  </div>
</template>

<style scoped>
/* Tick ruler: evenly spaced vertical lines filling the track height. */
.ruler::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    to right,
    color-mix(in oklch, var(--color-muted-foreground) 45%, transparent) 0,
    color-mix(in oklch, var(--color-muted-foreground) 45%, transparent) 1px,
    transparent 1px,
    transparent calc((100% - 1px) / 22)
  );
  pointer-events: none;
}

.ruler-slider :deep([data-slot="slider-track"]) {
  background: transparent;
}

.ruler-slider :deep([data-slot="slider-range"]) {
  background: transparent;
}

/* Bright vertical-bar thumb = the active position indicator. */
.ruler-slider :deep([data-slot="slider-thumb"]) {
  width: 0.375rem;
  height: 1.25rem;
  border-radius: 9999px;
  border: none;
  background: var(--color-foreground);
  box-shadow:
    0 0 0 1px color-mix(in oklch, var(--color-background) 70%, transparent),
    0 1px 3px rgb(0 0 0 / 0.35);
}
</style>
