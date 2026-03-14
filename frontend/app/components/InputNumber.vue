<template>
  <Input
    ref="inputRef"
    :model-value="displayValue"
    @keydown="onKeydown"
    @input="onInput"
    @blur="onBlur"
    :placeholder="placeholder"
    :inputmode="decimal ? 'decimal' : 'numeric'"
    :class="props.class"
  />
</template>

<script setup>
const props = defineProps({
  modelValue: { type: [Number, String], default: null },
  placeholder: { type: String, default: "" },
  min: { type: Number, default: undefined },
  max: { type: Number, default: undefined },
  decimal: { type: Boolean, default: false },
  class: { type: [String, Object, Array], default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const inputRef = ref(null);

const allowNegative = computed(() => props.min === undefined || props.min < 0);

function formatNumber(value) {
  if (value === null || value === undefined || value === "") return "";
  const num = typeof value === "string" ? parseFloat(value) : value;
  if (isNaN(num)) return "";

  if (props.decimal) {
    const parts = num.toString().split(".");
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return parts.join(".");
  }
  return Math.round(num)
    .toString()
    .replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

function parseRaw(str) {
  if (!str) return null;
  const clean = str.replace(/,/g, "");
  const num = props.decimal ? parseFloat(clean) : parseInt(clean, 10);
  return isNaN(num) ? null : num;
}

const displayValue = ref(formatNumber(props.modelValue));

watch(
  () => props.modelValue,
  (val) => {
    const currentRaw = parseRaw(displayValue.value);
    if (val !== currentRaw) {
      displayValue.value = formatNumber(val);
    }
  }
);

function countDigitsBefore(str, pos) {
  let count = 0;
  for (let i = 0; i < pos && i < str.length; i++) {
    if (/[\d.\-]/.test(str[i])) count++;
  }
  return count;
}

function findPositionByDigitCount(str, digitCount) {
  if (digitCount === 0) return 0;
  let count = 0;
  for (let i = 0; i < str.length; i++) {
    if (/[\d.\-]/.test(str[i])) count++;
    if (count === digitCount) return i + 1;
  }
  return str.length;
}

function onKeydown(e) {
  // Allow navigation, selection, deletion keys
  if (
    [
      "Backspace",
      "Delete",
      "Tab",
      "Escape",
      "Enter",
      "ArrowLeft",
      "ArrowRight",
      "ArrowUp",
      "ArrowDown",
      "Home",
      "End",
    ].includes(e.key)
  )
    return;
  // Allow Ctrl/Cmd shortcuts (copy, paste, cut, select all)
  if (e.ctrlKey || e.metaKey) return;

  // Allow minus only if negative is allowed and at position 0
  if (e.key === "-") {
    if (!allowNegative.value || e.target.selectionStart !== 0 || e.target.value.includes("-")) {
      e.preventDefault();
    }
    return;
  }

  // Allow decimal point only in decimal mode, and only one
  if (e.key === ".") {
    if (!props.decimal || e.target.value.includes(".")) {
      e.preventDefault();
    }
    return;
  }

  // Block anything that's not a digit
  if (!/^\d$/.test(e.key)) {
    e.preventDefault();
  }
}

function onInput(e) {
  const input = e.target;
  const cursorPos = input.selectionStart;

  const digitPos = countDigitsBefore(input.value, cursorPos);

  let allowed = props.decimal ? /[^\d.\-]/g : /[^\d\-]/g;
  let clean = input.value.replace(allowed, "");

  if (props.decimal) {
    const parts = clean.split(".");
    if (parts.length > 2) {
      clean = parts[0] + "." + parts.slice(1).join("");
    }
  }

  // Strip minus if not allowed or not at start
  if (!allowNegative.value) {
    clean = clean.replace(/-/g, "");
  } else if (clean.indexOf("-") > 0) {
    clean = clean.replace(/-/g, "");
  }

  const num = parseRaw(clean);

  // Format for display - keep partial decimal input (e.g. "123.")
  let formatted;
  const isNegative = clean.startsWith("-");
  const digits = clean.replace(/-/g, "").split(".")[0];

  if (clean.endsWith(".") && props.decimal) {
    formatted = formatNumber(num) + ".";
  } else if (clean === "-") {
    formatted = "-";
  } else if (/^0\d/.test(digits)) {
    // Preserve leading zeros during editing (will collapse on blur)
    const prefix = isNegative ? "-" : "";
    if (props.decimal && clean.includes(".")) {
      const decPart = clean.split(".")[1] || "";
      formatted = prefix + digits.replace(/\B(?=(\d{3})+(?!\d))/g, ",") + "." + decPart;
    } else {
      formatted = prefix + digits.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
  } else {
    formatted = formatNumber(num);
  }

  displayValue.value = formatted;
  emit("update:modelValue", num);

  nextTick(() => {
    const el = inputRef.value?.$el || inputRef.value;
    if (el) {
      const newPos = findPositionByDigitCount(formatted, digitPos);
      el.setSelectionRange(newPos, newPos);
    }
  });
}

function onBlur() {
  // Apply min/max clamping only on blur
  let num = parseRaw(displayValue.value);
  if (num !== null) {
    if (props.min !== undefined && num < props.min) num = props.min;
    if (props.max !== undefined && num > props.max) num = props.max;
  }
  displayValue.value = formatNumber(num);
  if (num !== props.modelValue) {
    emit("update:modelValue", num);
  }
}
</script>
