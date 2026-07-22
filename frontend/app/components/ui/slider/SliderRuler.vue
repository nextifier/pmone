<script setup>
import { computed, nextTick, ref } from "vue";

/**
 * Value-field scrubber, a 1:1 port of the transitions.dev "ValueField" control
 * (.tl-field): a pill track whose raised fill block shows the value, dragged
 * horizontally (cursor ew-resize), with the numeric value editable in place
 * (click → input, Enter commits, Esc cancels) and an optional chevron stepper
 * that opens a preset menu. Same label/modelValue/min/max/step/defaultValue
 * API as the previous SliderRuler; `tokens` adds the preset dropdown.
 * Colors/shadows are the site's exact values (not theme tokens) in both modes.
 */
const props = defineProps({
  label: { type: String, default: "" },
  modelValue: { type: [Number, String], default: 0 },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 100 },
  step: { type: Number, default: 1 },
  defaultValue: { type: Number, default: undefined },
  /** Optional presets for the chevron menu: [{ label, value }] */
  tokens: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue"]);

const trackRef = ref(null);
const inputRef = ref(null);
const dragging = ref(false);
const editing = ref(false);
const menuOpen = ref(false);
const draft = ref("");

// 9 evenly-spaced tick marks (10%…90%), matching reactbits.dev/animations/strands
const tickPositions = Array.from({ length: 9 }, (_, i) => (i + 1) * 10);

const value = computed(() => Number(props.modelValue) || 0);
const pct = computed(() => {
  const range = props.max - props.min;
  if (range <= 0) return 0;
  return Math.min(Math.max((value.value - props.min) / range, 0), 1) * 100;
});

// decimals of `step`, so float steps (e.g. 0.01) snap and print cleanly
const stepDecimals = computed(() => {
  const s = String(props.step);
  return s.includes(".") ? s.split(".")[1].length : 0;
});
const display = computed(
  () => value.value.toFixed(Math.min(stepDecimals.value, 4)) * 1,
);

// fine grid while dragging (smooth); the snap `step` is applied on release.
const dragGrid = computed(() => props.step / 5);

function setFromX(clientX, grid) {
  if (!trackRef.value) return;
  const rect = trackRef.value.getBoundingClientRect();
  const ratio = Math.max(0, Math.min((clientX - rect.left) / rect.width, 1));
  const raw = props.min + ratio * (props.max - props.min);
  const g = grid || 1;
  const snapped = Math.round(raw / g) * g;
  const clamped = Math.max(props.min, Math.min(props.max, snapped));
  emit("update:modelValue", +clamped.toFixed(6));
}

function startDrag(e) {
  if (editing.value) return;
  if (e.button !== 0) return;
  e.preventDefault();
  dragging.value = true;
  let lastX = e.clientX;
  setFromX(e.clientX, dragGrid.value);
  const el = e.currentTarget;
  try {
    el.setPointerCapture(e.pointerId);
  } catch {
    /* pointer already inactive (e.g. synthetic event) — drag still works */
  }
  const onMove = (e2) => {
    lastX = e2.clientX;
    setFromX(e2.clientX, dragGrid.value);
  };
  const onUp = () => {
    dragging.value = false;
    setFromX(lastX, props.step);
    el.removeEventListener("pointermove", onMove);
    el.removeEventListener("pointerup", onUp);
    el.removeEventListener("pointercancel", onUp);
  };
  el.addEventListener("pointermove", onMove);
  el.addEventListener("pointerup", onUp);
  el.addEventListener("pointercancel", onUp);
}

function nudge(raw) {
  const snapped = Math.round(raw / props.step) * props.step;
  const clamped = Math.max(props.min, Math.min(props.max, snapped));
  emit("update:modelValue", +clamped.toFixed(6));
}

function onKeydown(e) {
  if (editing.value) return;
  // PageUp/PageDown jump by a tenth of the range, floored to one `step`
  const page = Math.max(props.step, (props.max - props.min) / 10);
  let next;
  switch (e.key) {
    case "ArrowRight":
    case "ArrowUp":
      next = value.value + props.step;
      break;
    case "ArrowLeft":
    case "ArrowDown":
      next = value.value - props.step;
      break;
    case "PageUp":
      next = value.value + page;
      break;
    case "PageDown":
      next = value.value - page;
      break;
    case "Home":
      next = props.min;
      break;
    case "End":
      next = props.max;
      break;
    default:
      return;
  }
  e.preventDefault();
  nudge(next);
}

function onDblClick() {
  if (props.defaultValue !== undefined)
    emit("update:modelValue", props.defaultValue);
}

function beginEdit(e) {
  e.stopPropagation();
  draft.value = String(display.value);
  editing.value = true;
  nextTick(() => {
    inputRef.value?.focus();
    requestAnimationFrame(() => inputRef.value?.select());
  });
}

function commit() {
  const n = parseFloat(draft.value);
  if (!Number.isNaN(n)) {
    const clamped = Math.max(props.min, Math.min(props.max, n));
    emit("update:modelValue", +clamped.toFixed(6));
  }
  editing.value = false;
}

function pickToken(tk) {
  emit("update:modelValue", tk.value);
  menuOpen.value = false;
}
</script>

<template>
  <div class="tl-field-wrap">
    <div
      class="tl-field"
      :class="{ 'is-dragging': dragging, 'is-editing': editing }"
      @dblclick="onDblClick"
    >
      <div
        class="tl-field-fill"
        :class="{ 'is-dragging': dragging }"
        :style="{ width: pct + '%' }"
      >
        <div class="tl-field-thumb" />
      </div>
      <div class="scrubber-ticks">
        <span
          v-for="left in tickPositions"
          :key="left"
          class="scrubber-tick"
          :style="{ left: left + '%' }"
        />
      </div>
      <div
        ref="trackRef"
        class="tl-field-track"
        role="slider"
        tabindex="0"
        :aria-label="label || 'Value'"
        :aria-valuemin="min"
        :aria-valuemax="max"
        :aria-valuenow="value"
        :aria-valuetext="String(display)"
        @pointerdown="startDrag"
        @keydown="onKeydown"
      />
      <span class="tl-field-label">{{ label }}</span>
      <input
        v-if="editing"
        ref="inputRef"
        v-model="draft"
        class="tl-field-input"
        @blur="commit"
        @keydown.enter="commit"
        @keydown.escape="editing = false"
        @pointerdown.stop
      />
      <span
        v-else
        class="tl-field-value"
        @pointerdown.stop
        @click="beginEdit"
        >{{ display }}</span
      >
    </div>

    <template v-if="tokens.length">
      <button
        type="button"
        class="tl-field-chevron"
        @click="menuOpen = !menuOpen"
      >
        <svg width="10" height="6" viewBox="0 0 10 6" fill="none">
          <path
            d="M1 1l4 4 4-4"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </button>
      <div v-if="menuOpen" class="tl-field-menu">
        <button
          v-for="tk in tokens"
          :key="tk.label"
          type="button"
          class="tl-field-menu-item"
          @click="pickToken(tk)"
        >
          {{ tk.label }} <span class="tl-field-menu-dim">{{ tk.value }}</span>
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* Exact values from transitions.dev (.tl-field, light) with html.dark overrides. */
.tl-field-wrap {
  position: relative;
  display: flex;
  align-items: center;
  gap: 6px;
}
.tl-field {
  position: relative;
  flex: 1;
  min-width: 0;
  height: 32px;
  border-radius: 8px;
  background: rgba(238, 238, 239, 0.51);
  transition:
    box-shadow 0.12s ease,
    background 0.12s ease;
}
.tl-field:hover:not(.is-editing) {
  background: rgba(170, 170, 170, 0.2);
}
.tl-field.is-editing {
  box-shadow:
    inset 0 0 0 1px rgba(0, 0, 0, 0.14),
    inset 0 0 0 0.5px rgba(255, 255, 255, 0.06);
}
.tl-field-fill {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  min-width: 32px;
  border-radius: 8px;
  background: rgba(219, 219, 219, 0.4);
  box-shadow:
    inset 0 0 0 1px rgba(0, 0, 0, 0.02),
    inset 0 -1px 0 0 rgba(0, 0, 0, 0.08),
    inset 0 0 0 1px rgba(191, 191, 191, 0.1),
    0 1px 3px 0 rgba(0, 0, 0, 0.06);
  pointer-events: none;
  z-index: 1;
  transition:
    background 0.12s ease,
    opacity 0.12s ease,
    width 220ms cubic-bezier(0.22, 1, 0.36, 1);
}
.tl-field-fill.is-dragging {
  background: rgba(219, 219, 219, 0.6);
  transition:
    background 0.12s ease,
    opacity 0.12s ease;
}
@media (prefers-reduced-motion: reduce) {
  .tl-field-fill {
    transition:
      background 0.12s ease,
      opacity 0.12s ease !important;
  }
}
.tl-field.is-dragging .tl-field-label {
  opacity: 0.7;
}
.tl-field.is-editing .tl-field-fill {
  opacity: 0;
}
/* tick marks — reactbits.dev/animations/strands (.scrubber-tick): 1×8px pills,
   evenly spaced, faint border color; sit above the fill, below label/value. */
.scrubber-ticks {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 1;
}
.scrubber-tick {
  position: absolute;
  top: 50%;
  width: 1px;
  height: 8px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.12);
  transform: translate(-50%) translateY(-50%);
}
/* pan-y: horizontal drags scrub, vertical swipes still scroll the page */
.tl-field-track {
  position: absolute;
  inset: 0;
  cursor: ew-resize;
  z-index: 2;
  touch-action: pan-y;
  border-radius: inherit;
  outline: none;
}
.tl-field-track:focus-visible {
  outline: 2px solid rgba(0, 0, 0, 0.35);
  outline-offset: 2px;
}
.tl-field-label {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 13px;
  font-weight: 500;
  color: #585858;
  pointer-events: none;
  z-index: 3;
}
.tl-field-value {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 13px;
  font-weight: 400;
  color: #17181c;
  cursor: text;
  z-index: 3;
  font-variant-numeric: tabular-nums;
}
.tl-field-input {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  width: 64px;
  text-align: right;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 13px;
  font-weight: 400;
  color: #17181c;
  outline: none;
  z-index: 4;
  font-variant-numeric: tabular-nums;
}
.tl-field-thumb {
  position: absolute;
  right: 8px;
  top: 7px;
  height: 18px;
  width: 2px;
  background: #767676;
  border-radius: 20px;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.12s ease;
  z-index: 3;
}
.tl-field:hover .tl-field-thumb {
  opacity: 0.6;
}
.tl-field.is-dragging .tl-field-thumb {
  opacity: 1;
}
.tl-field.is-editing .tl-field-thumb {
  opacity: 0;
}
.tl-field-chevron {
  flex: none;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: rgba(238, 238, 239, 0.51);
  color: #8b9099;
  cursor: pointer;
  transition:
    background 0.12s ease,
    scale 0.12s ease;
}
.tl-field-chevron:hover {
  background: rgba(170, 170, 170, 0.2);
}
.tl-field-chevron:active {
  scale: 0.96;
}

.tl-field-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 4px);
  z-index: 50;
  min-width: 180px;
  padding: 4px;
  border-radius: 10px;
  background: #fff;
  box-shadow:
    0 0 0 1px rgba(0, 0, 0, 0.06),
    0 8px 24px rgba(0, 0, 0, 0.12);
}
.tl-field-menu-item {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 6px;
  border: none;
  background: transparent;
  border-radius: 6px;
  padding: 6px 8px;
  font-size: 13px;
  color: #17181c;
  cursor: pointer;
  text-align: left;
}
.tl-field-menu-item:hover {
  background: rgba(170, 170, 170, 0.2);
}
.tl-field-menu-dim {
  color: #8b8b8b;
}
</style>

<style>
/* dark overrides — unscoped so the `.dark` ancestor class matches */
.dark .tl-field {
  background: rgba(255, 255, 255, 0.04);
}
.dark .tl-field:hover:not(.is-editing) {
  background: rgba(255, 255, 255, 0.06);
}
.dark .tl-field.is-editing {
  box-shadow:
    inset 0 0 0 1px rgba(255, 255, 255, 0.14),
    inset 0 0 0 0.5px rgba(255, 255, 255, 0.06);
}
.dark .tl-field-fill {
  background: rgba(255, 255, 255, 0.1);
  box-shadow:
    0 1px 1px 0 rgba(0, 0, 0, 0.24),
    inset 0 0 0 1px rgba(255, 255, 255, 0.04),
    inset 0 1px 0 0 rgba(255, 255, 255, 0.06);
}
.dark .tl-field-fill.is-dragging {
  background: rgba(255, 255, 255, 0.12);
}
.dark .tl-field-label {
  color: #979797;
}
.dark .tl-field-value,
.dark .tl-field-input {
  color: #fbfbfb;
}
.dark .scrubber-tick {
  background: rgba(255, 255, 255, 0.1);
}
.dark .tl-field-track:focus-visible {
  outline-color: rgba(255, 255, 255, 0.5);
}
.dark .tl-field-chevron {
  background: rgba(255, 255, 255, 0.04);
}
.dark .tl-field-chevron:hover {
  background: rgba(255, 255, 255, 0.06);
}
.dark .tl-field-menu {
  background: #232323;
  box-shadow:
    0 0 0 1px rgba(255, 255, 255, 0.08),
    0 8px 24px rgba(0, 0, 0, 0.4);
}
.dark .tl-field-menu-item {
  color: #fbfbfb;
}
.dark .tl-field-menu-item:hover {
  background: rgba(255, 255, 255, 0.06);
}
</style>
