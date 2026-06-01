<script setup>
import { ref } from "vue";
import { Input } from "@/components/ui/input";

// 2D position control for `ui.type: "position"` props ({ x, y } in 0..1, origin
// top-left). Drag the handle on the pad or type exact values.
const model = defineModel({ type: Object, default: () => ({ x: 0.5, y: 0.5 }) });

const pad = ref(null);
const dragging = ref(false);

const clamp01 = (n) => Math.min(1, Math.max(0, n));
const round = (n) => Math.round(n * 1000) / 1000;

function setFromEvent(event) {
  if (!pad.value) return;
  const rect = pad.value.getBoundingClientRect();
  model.value = {
    x: round(clamp01((event.clientX - rect.left) / rect.width)),
    y: round(clamp01((event.clientY - rect.top) / rect.height)),
  };
}

function onPointerDown(event) {
  dragging.value = true;
  pad.value?.setPointerCapture?.(event.pointerId);
  setFromEvent(event);
}
function onPointerMove(event) {
  if (dragging.value) setFromEvent(event);
}
function onPointerUp(event) {
  dragging.value = false;
  pad.value?.releasePointerCapture?.(event.pointerId);
}

function setAxis(axis, raw) {
  const n = Number.parseFloat(raw);
  if (Number.isNaN(n)) return;
  model.value = { ...model.value, [axis]: n };
}
</script>

<template>
  <div class="space-y-2">
    <div
      ref="pad"
      class="bg-muted/40 border-input relative aspect-[3/2] w-full cursor-crosshair touch-none overflow-hidden rounded-lg border [background-image:radial-gradient(var(--color-muted-foreground)_1px,transparent_1px)] [background-size:14px_14px] [background-position:center]"
      @pointerdown="onPointerDown"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
    >
      <div
        class="border-background bg-primary ring-primary/30 pointer-events-none absolute size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 shadow-md ring-2"
        :style="{ left: `${clamp01(model.x ?? 0.5) * 100}%`, top: `${clamp01(model.y ?? 0.5) * 100}%` }"
      />
    </div>
    <div class="grid grid-cols-2 gap-x-2">
      <div class="flex items-center gap-x-1.5">
        <span class="text-muted-foreground text-xs">X</span>
        <Input
          :model-value="model.x ?? 0.5"
          type="number"
          step="0.01"
          class="h-8 text-xs sm:text-sm"
          @update:model-value="setAxis('x', $event)"
        />
      </div>
      <div class="flex items-center gap-x-1.5">
        <span class="text-muted-foreground text-xs">Y</span>
        <Input
          :model-value="model.y ?? 0.5"
          type="number"
          step="0.01"
          class="h-8 text-xs sm:text-sm"
          @update:model-value="setAxis('y', $event)"
        />
      </div>
    </div>
  </div>
</template>
