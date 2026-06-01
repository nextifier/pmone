<script setup>
import { computed } from "vue";
import { SliderRuler } from "@/components/ui/slider";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

/**
 * Config sub-form for a dynamic prop driver (PropDriver). A driven prop holds an
 * object `{ type, ... }` instead of a static number; this edits that object for
 * the three numeric driver types the package supports:
 *  - map: sample another layer's alpha/luminance and remap it
 *  - mouse: map a pointer axis to the value
 *  - auto-animate: oscillate / loop the value over time
 */
const props = defineProps({
  modelValue: { type: Object, required: true },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 100 },
  step: { type: Number, default: 1 },
  // [{ id, label }] of other layers, for the map source.
  layers: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue"]);

const DRIVER_TYPES = [
  { value: "map", label: "Layer map" },
  { value: "mouse", label: "Mouse" },
  { value: "auto-animate", label: "Auto-animate" },
];
const MAP_CHANNELS = ["luminance", "luminanceInverted", "alpha", "alphaInverted"];
const AXES = ["x", "y"];
const MODES = ["ping-pong", "loop"];
const EASINGS = ["sine", "linear", "quad", "expo", "bounce"];

const d = computed(() => props.modelValue ?? {});

const formatLabel = (s) =>
  String(s)
    .replace(/-/g, " ")
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/\b\w/g, (m) => m.toUpperCase());

function defaultDriver(type) {
  const lo = props.min;
  const hi = props.max;
  if (type === "map") {
    return {
      type: "map",
      source: props.layers[0]?.id ?? "",
      channel: "luminance",
      inputMin: 0,
      inputMax: 1,
      outputMin: lo,
      outputMax: hi,
    };
  }
  if (type === "mouse") {
    return { type: "mouse", axis: "x", outputMin: lo, outputMax: hi };
  }
  return { type: "auto-animate", mode: "ping-pong", outputMin: lo, outputMax: hi, speed: 1, easing: "sine" };
}

function setType(type) {
  emit("update:modelValue", defaultDriver(type));
}

function set(key, value) {
  emit("update:modelValue", { ...props.modelValue, [key]: value });
}
</script>

<template>
  <div class="border-border/60 bg-muted/30 space-y-3 rounded-lg border p-2.5">
    <div class="space-y-1.5">
      <label class="text-muted-foreground block text-sm tracking-tight">Driver</label>
      <Select :model-value="d.type" @update:model-value="setType">
        <SelectTrigger size="sm" class="w-full"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem v-for="t in DRIVER_TYPES" :key="t.value" :value="t.value">{{ t.label }}</SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- map -->
    <template v-if="d.type === 'map'">
      <div class="space-y-1.5">
        <label class="text-muted-foreground block text-sm tracking-tight">Source layer</label>
        <Select :model-value="d.source" @update:model-value="(v) => set('source', v)">
          <SelectTrigger size="sm" class="w-full"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="layer in layers" :key="layer.id" :value="layer.id">
              {{ layer.label ?? layer.type }}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div class="space-y-1.5">
        <label class="text-muted-foreground block text-sm tracking-tight">Channel</label>
        <Select :model-value="d.channel" @update:model-value="(v) => set('channel', v)">
          <SelectTrigger size="sm" class="w-full"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="c in MAP_CHANNELS" :key="c" :value="c">{{ formatLabel(c) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <SliderRuler label="Input min" :model-value="Number(d.inputMin ?? 0)" :min="0" :max="1" :step="0.01" @update:model-value="(v) => set('inputMin', v)" />
      <SliderRuler label="Input max" :model-value="Number(d.inputMax ?? 1)" :min="0" :max="1" :step="0.01" @update:model-value="(v) => set('inputMax', v)" />
      <SliderRuler label="Output min" :model-value="Number(d.outputMin ?? min)" :min="min" :max="max" :step="step" @update:model-value="(v) => set('outputMin', v)" />
      <SliderRuler label="Output max" :model-value="Number(d.outputMax ?? max)" :min="min" :max="max" :step="step" @update:model-value="(v) => set('outputMax', v)" />
    </template>

    <!-- mouse -->
    <template v-else-if="d.type === 'mouse'">
      <div class="space-y-1.5">
        <label class="text-muted-foreground block text-sm tracking-tight">Axis</label>
        <Select :model-value="d.axis" @update:model-value="(v) => set('axis', v)">
          <SelectTrigger size="sm" class="w-full"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="a in AXES" :key="a" :value="a">{{ formatLabel(a) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <SliderRuler label="Output min" :model-value="Number(d.outputMin ?? min)" :min="min" :max="max" :step="step" @update:model-value="(v) => set('outputMin', v)" />
      <SliderRuler label="Output max" :model-value="Number(d.outputMax ?? max)" :min="min" :max="max" :step="step" @update:model-value="(v) => set('outputMax', v)" />
    </template>

    <!-- auto-animate -->
    <template v-else-if="d.type === 'auto-animate'">
      <div class="space-y-1.5">
        <label class="text-muted-foreground block text-sm tracking-tight">Mode</label>
        <Select :model-value="d.mode" @update:model-value="(v) => set('mode', v)">
          <SelectTrigger size="sm" class="w-full"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="m in MODES" :key="m" :value="m">{{ formatLabel(m) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <SliderRuler label="Output min" :model-value="Number(d.outputMin ?? min)" :min="min" :max="max" :step="step" @update:model-value="(v) => set('outputMin', v)" />
      <SliderRuler label="Output max" :model-value="Number(d.outputMax ?? max)" :min="min" :max="max" :step="step" @update:model-value="(v) => set('outputMax', v)" />
      <SliderRuler label="Speed" :model-value="Number(d.speed ?? 1)" :min="0" :max="5" :step="0.1" @update:model-value="(v) => set('speed', v)" />
      <div class="space-y-1.5">
        <label class="text-muted-foreground block text-sm tracking-tight">Easing</label>
        <Select :model-value="d.easing" @update:model-value="(v) => set('easing', v)">
          <SelectTrigger size="sm" class="w-full"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem v-for="e in EASINGS" :key="e" :value="e">{{ formatLabel(e) }}</SelectItem>
          </SelectContent>
        </Select>
      </div>
    </template>
  </div>
</template>
