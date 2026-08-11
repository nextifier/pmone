<template>
  <Input
    ref="inputRef"
    :model-value="display"
    :placeholder="placeholder"
    maxlength="50"
    autocapitalize="characters"
    autocomplete="off"
    spellcheck="false"
    @keydown="onKeydown"
    @input="onInput"
    @blur="onBlur"
  />
</template>

<script setup>
import { Input } from "@/components/ui/input";

const props = defineProps({
  modelValue: { type: String, default: "" },
  placeholder: { type: String, default: "Ex: B-101" },
});

const emit = defineEmits(["update:modelValue"]);

const inputRef = ref(null);

// `raw` is what the user actually typed, `display` is raw plus the dashes this
// component inserted, and `autoDashes` marks where those are. Rebuilding the
// display from raw on every keystroke is what lets an earlier auto dash be
// revoked once the token turns out to be longer: typing B-1-A-0-1 walks
// "B" -> "B-1" -> "B-1A" -> "B1A-0" -> "B1A-01". A dash the user typed is never
// in the set, so it is never taken away.
const raw = ref("");
const display = ref("");
const autoDashes = ref(new Set());

function render(next) {
  const { value, auto } = insertBoothDashes(next);
  // Store the raw side uppercased too, so the two never drift apart: the
  // formatter uppercases, and every later diff runs against what is on screen.
  raw.value = next.toUpperCase();
  display.value = value;
  autoDashes.value = auto;
  return value;
}

// External updates only. Dashes arriving from the API count as the user's own,
// so the formatter never yanks them. Deliberately does not emit: an untouched
// field must not dirty the form, and the API normalizes on save anyway.
watch(
  () => props.modelValue,
  (incoming) => {
    const value = String(incoming ?? "");
    if (value !== raw.value && value !== display.value) {
      render(value);
    }
  },
  { immediate: true },
);

function countRawBefore(str, pos, auto) {
  let count = 0;
  for (let i = 0; i < pos && i < str.length; i++) {
    if (!auto.has(i)) count++;
  }
  return count;
}

function positionByRawCount(str, target, auto) {
  if (target === 0) return 0;
  let seen = 0;
  for (let i = 0; i < str.length; i++) {
    if (!auto.has(i)) seen++;
    if (seen === target) return i + 1;
  }
  return str.length;
}

// Map the previous auto-dash positions through one contiguous edit (keystroke,
// paste, or selection replace), then drop the survivors to recover the raw
// string the user means.
function rebase(prevDisplay, prevAuto, next) {
  const max = Math.min(prevDisplay.length, next.length);

  let head = 0;
  while (head < max && prevDisplay[head] === next[head]) head++;

  let tail = 0;
  while (
    tail < max - head &&
    prevDisplay[prevDisplay.length - 1 - tail] === next[next.length - 1 - tail]
  ) {
    tail++;
  }

  const delta = next.length - prevDisplay.length;
  const autoOnNext = new Set();

  for (const index of prevAuto) {
    if (index < head) autoOnNext.add(index);
    else if (index >= prevDisplay.length - tail) autoOnNext.add(index + delta);
  }

  let recovered = "";
  for (let i = 0; i < next.length; i++) {
    if (!autoOnNext.has(i)) recovered += next[i];
  }

  return { recovered, autoOnNext };
}

function syncDom(value, caret) {
  const el = inputRef.value?.$el || inputRef.value;
  if (!el) return;
  // Input keeps its own passive v-model, so a value the formatter rewrote would
  // otherwise sit in the DOM with no prop change to patch it back.
  if (el.value !== value) el.value = value;
  if (caret != null) el.setSelectionRange(caret, caret);
}

function onKeydown(e) {
  const el = e.target;
  if (el.selectionStart !== el.selectionEnd) return;

  const pos = el.selectionStart;

  // Step over an auto dash instead of deleting it. The formatter would put it
  // straight back, so deleting it would just make the key look broken.
  if (e.key === "Backspace" && autoDashes.value.has(pos - 1)) {
    e.preventDefault();
    el.setSelectionRange(pos - 1, pos - 1);
  } else if (e.key === "Delete" && autoDashes.value.has(pos)) {
    e.preventDefault();
    el.setSelectionRange(pos + 1, pos + 1);
  }
}

function onInput(e) {
  const el = e.target;
  const next = el.value;
  const caret = el.selectionStart ?? next.length;

  const { recovered, autoOnNext } = rebase(display.value, autoDashes.value, next);
  const rawCaret = countRawBefore(next, caret, autoOnNext);

  const value = render(recovered);
  emit("update:modelValue", value);

  nextTick(() => syncDom(value, positionByRawCount(value, rawCaret, autoDashes.value)));
}

// Separator tidying waits for blur: doing it per keystroke would eat the space
// a user just typed after a comma.
function onBlur() {
  const value = render(tidyBoothSeparators(raw.value));
  emit("update:modelValue", value);
  nextTick(() => syncDom(value));
}
</script>
