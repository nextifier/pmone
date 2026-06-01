<script setup>
import { Input } from "@/components/ui/input";
import { Slider } from "@/components/ui/slider";
import { computed, useId } from "vue";

/**
 * Ruler/scrubber control matching the shaders.com editor: a recessed,
 * tick-marked track with a thin glowing indicator line, paired with an
 * editable number box. Drop-in replacement for the old ShaderRangeControl
 * (same label/modelValue/min/max/step API). reka-ui Slider drives the
 * interaction and renders the primary-colored fill (slider-range); the
 * diamond ticks and thin indicator are layered on top.
 */
const props = defineProps({
  label: { type: String, default: "" },
  modelValue: { type: [Number, String], default: 0 },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 100 },
  step: { type: Number, default: 1 },
  defaultValue: { type: Number, default: undefined },
});

const emit = defineEmits(["update:modelValue"]);

const inputId = useId();

const ticks = Array.from({ length: 9 }, (_, i) => ({
  left: (i + 1) * 10,
  height: +(20 - Math.abs(i - 4) * 2.4).toFixed(1),
}));

const percent = computed(() => {
  const range = props.max - props.min;
  if (range <= 0) {
    return 0;
  }
  const ratio = (Number(props.modelValue) - props.min) / range;
  return Math.min(100, Math.max(0, ratio * 100));
});

const showReset = computed(
  () => props.defaultValue !== undefined && Number(props.modelValue) !== props.defaultValue
);

function fromSlider(value) {
  emit("update:modelValue", value?.[0] ?? props.min);
}

function fromInput(raw) {
  const n = Number.parseFloat(raw);
  if (!Number.isNaN(n)) {
    emit("update:modelValue", n);
  }
}

function reset() {
  if (props.defaultValue !== undefined) {
    emit("update:modelValue", props.defaultValue);
  }
}
</script>

<template>
  <div class="group/ruler space-y-1.5">
    <div v-if="label || defaultValue !== undefined" class="flex items-center gap-2">
      <label
        v-if="label"
        :for="inputId"
        class="text-muted-foreground group-hover/ruler:text-foreground text-xs tracking-tight transition-colors select-none"
      >
        {{ label }}
      </label>
      <button
        v-if="defaultValue !== undefined"
        type="button"
        class="text-muted-foreground hover:text-foreground transition-opacity"
        :class="showReset ? 'opacity-100' : 'pointer-events-none opacity-0'"
        aria-label="Reset to default"
        @click="reset"
      >
        <Icon name="hugeicons:refresh" class="size-3.5" aria-hidden="true" />
      </button>
    </div>

    <div class="flex items-center gap-2">
      <div
        class="ruler-track group/ruler bg-background relative isolate h-9 flex-1 cursor-pointer rounded-lg shadow-[0_0_0_1px_var(--border)]"
      >
        <Slider
          class="ruler-slider absolute inset-0 size-full"
          :model-value="[Number(modelValue)]"
          :min="min"
          :max="max"
          :step="step"
          :aria-label="label || 'Value'"
          @update:model-value="fromSlider"
        />

        <div class="pointer-events-none absolute inset-0 px-1">
          <span
            v-for="tick in ticks"
            :key="tick.left"
            class="absolute top-1/2 w-px -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/20 mix-blend-difference transition-colors group-hover/ruler:bg-white/40"
            :style="{ left: `${tick.left}%`, height: `${tick.height}px` }"
          />
        </div>
      </div>

      <Input
        :id="inputId"
        :model-value="modelValue"
        type="number"
        :min="min"
        :max="max"
        :step="step"
        class="h-9 w-16 shrink-0 rounded-lg text-center text-xs tabular-nums sm:text-sm"
        @update:model-value="fromInput"
      />
    </div>
  </div>
</template>

<style scoped>
/* Track + range are invisible: reka-ui only drives interaction here. */
.ruler-slider :deep([data-slot="slider-track"][data-orientation="horizontal"]) {
  height: 100%;
  border-radius: 0.5rem;
  background: transparent;
  box-shadow: none;
}

/* Thin, glowing vertical-bar indicator = the active position, above ticks. */
.ruler-slider :deep([data-slot="slider-thumb"]) {
  z-index: 10;
  width: 1.5px;
  height: 1.5rem;
  border: none;
  border-radius: 9999px;
  background: color-mix(in oklch, var(--foreground) 60%, transparent);
  box-shadow: 0 0 6px color-mix(in oklch, var(--foreground) 18%, transparent);
}

.group\/ruler:hover .ruler-slider :deep([data-slot="slider-thumb"]),
.ruler-slider :deep([data-slot="slider-thumb"]:focus-visible) {
  background: var(--foreground);
  box-shadow: 0 0 6px color-mix(in oklch, var(--foreground) 28%, transparent);
}
</style>
