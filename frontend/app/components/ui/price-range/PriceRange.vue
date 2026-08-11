<template>
  <div class="space-y-3">
    <div class="grid grid-cols-2 gap-x-2">
      <div class="space-y-1.5">
        <span class="text-muted-foreground block text-xs tracking-tight sm:text-sm">
          {{ startLabel }}
        </span>
        <InputGroup>
          <InputNumber
            :id="id"
            :model-value="local.start"
            :min="lowerBound"
            :max="upperBound"
            :disabled="disabled"
            :placeholder="startPlaceholder"
            data-slot="input-group-control"
            class="cn-input-group-input flex-1"
            @update:model-value="onStartInput"
            @blur="commit"
          />
          <InputGroupAddon>
            <InputGroupText>{{ currency }}</InputGroupText>
          </InputGroupAddon>
        </InputGroup>
      </div>

      <div class="space-y-1.5">
        <span class="text-muted-foreground block text-xs tracking-tight sm:text-sm">
          {{ endLabel }}
        </span>
        <InputGroup>
          <InputNumber
            :model-value="local.end"
            :min="lowerBound"
            :max="upperBound"
            :disabled="disabled"
            :placeholder="endPlaceholder"
            data-slot="input-group-control"
            class="cn-input-group-input flex-1"
            @update:model-value="onEndInput"
            @blur="commit"
          />
          <InputGroupAddon>
            <InputGroupText>{{ currency }}</InputGroupText>
          </InputGroupAddon>
        </InputGroup>
      </div>
    </div>

    <!-- The track is opt-in, and only appears once a ceiling is configured.
         A slider implies "the answer lives between these two numbers", which is
         a promise the field cannot keep when the real range is open-ended: an
         arbitrary Rp 500,000,000 ceiling would silently cap anyone whose figure
         runs higher. Without a max, the two amount fields stand alone. -->
    <template v-if="sliderVisible">
      <Slider
        :model-value="sliderValue"
        :min="lowerBound"
        :max="upperBound"
        :step="safeStep"
        :disabled="disabled"
        @update:model-value="onSlider"
      />

      <div class="text-muted-foreground flex justify-between text-xs tracking-tight sm:text-sm">
        <span>{{ currency }} {{ formatted(lowerBound) }}</span>
        <span>{{ currency }} {{ formatted(upperBound) }}</span>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, nextTick, reactive, watch } from "vue";
import { InputGroup, InputGroupAddon, InputGroupText } from "@/components/ui/input-group";
import { InputNumber } from "@/components/ui/input-number";
import { Slider } from "@/components/ui/slider";

const props = defineProps({
  /** `{ start, end }`, or null while neither end has been filled in. */
  modelValue: { type: Object, default: null },
  min: { type: Number, default: 0 },
  /** Null leaves the range open-ended: no ceiling, and no slider. */
  max: { type: Number, default: null },
  step: { type: Number, default: 1 },
  currency: { type: String, default: "Rp" },
  showSlider: { type: Boolean, default: true },
  disabled: { type: Boolean, default: false },
  id: { type: String, default: undefined },
  startLabel: { type: String, default: "Start from" },
  endLabel: { type: String, default: "Up to" },
  startPlaceholder: { type: String, default: "" },
  endPlaceholder: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const toNumber = (value) => {
  if (value === null || value === undefined || value === "") return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
};

const configuredMin = computed(() => toNumber(props.min) ?? 0);
const configuredMax = computed(() => toNumber(props.max));

/**
 * The configured bounds are read in sorted order rather than trusted as given.
 * The live preview in the field builder re-renders on every keystroke, so it
 * sees min=500,000,000 against max=100,000,000 for as long as it takes to
 * retype the ceiling - and clamping against an inverted pair pins every value
 * to the smaller number and silently destroys what the user had typed.
 */
const lowerBound = computed(() =>
  configuredMax.value === null
    ? configuredMin.value
    : Math.min(configuredMin.value, configuredMax.value),
);

const upperBound = computed(() =>
  configuredMax.value === null
    ? undefined
    : Math.max(configuredMin.value, configuredMax.value),
);

const safeStep = computed(() => (props.step > 0 ? props.step : 1));

// A zero-width track divides by zero, so the slider needs a real span as well
// as a ceiling and the author's opt-in.
const sliderVisible = computed(
  () => props.showSlider && upperBound.value !== undefined && upperBound.value > lowerBound.value,
);

const clamp = (value) => {
  const floored = Math.max(value, lowerBound.value);
  return upperBound.value === undefined ? floored : Math.min(floored, upperBound.value);
};

const local = reactive({ start: null, end: null });

// Guarded, so the component's own emit coming back round is a no-op rather than
// the first step of a loop.
watch(
  () => props.modelValue,
  (value) => {
    const start = toNumber(value?.start);
    const end = toNumber(value?.end);
    if (start !== local.start || end !== local.end) {
      local.start = start;
      local.end = end;
    }
  },
  { immediate: true, deep: true },
);

function emitValue() {
  emit(
    "update:modelValue",
    local.start === null && local.end === null ? null : { start: local.start, end: local.end },
  );
}

// Keystrokes pass through unclamped and unordered on purpose: clamping while
// the user is still typing would rewrite "50" to the minimum before they reach
// "50,000,000".
function onStartInput(value) {
  local.start = toNumber(value);
  emitValue();
}

function onEndInput(value) {
  local.end = toNumber(value);
  emitValue();
}

/**
 * Runs on blur, after InputNumber's own clamp - both listeners sit on the same
 * element and their order is not guaranteed, so this waits a tick.
 *
 * An out-of-order pair is swapped rather than flattened. Two numbers were
 * typed; a range of those two numbers is the obvious reading, and pushing one
 * onto the other would throw the older of them away.
 */
function commit() {
  nextTick(() => {
    let start = local.start === null ? null : clamp(local.start);
    let end = local.end === null ? null : clamp(local.end);

    if (start !== null && end !== null && start > end) {
      [start, end] = [end, start];
    }

    if (start === local.start && end === local.end) return;

    local.start = start;
    local.end = end;
    emitValue();
  });
}

// An untouched field shows the full track, matching how the slider_range branch
// of CustomFieldRenderer behaves.
const sliderValue = computed(() => {
  const start = clamp(local.start ?? lowerBound.value);
  const end = clamp(local.end ?? upperBound.value ?? lowerBound.value);
  return start <= end ? [start, end] : [end, start];
});

// reka clamps to min/max/step and stops the thumbs crossing, so slider output is
// always valid and can be applied live without a commit step. The equality guard
// keeps reka's own re-emit of an unchanged value from dirtying the form.
function onSlider(values) {
  if (!Array.isArray(values) || values.length < 2) return;

  const start = Math.min(values[0], values[1]);
  const end = Math.max(values[0], values[1]);

  if (start === local.start && end === local.end) return;

  local.start = start;
  local.end = end;
  emitValue();
}

const formatted = (value) => Number(value).toLocaleString("en-US");
</script>
