<script setup>
import { Icon } from "#components";

const props = defineProps({
  // up | down — picks the icon.
  direction: {
    type: String,
    default: "up",
  },
  // success | destructive | warning — picks the color.
  tone: {
    type: String,
    default: "success",
  },
  // Numeric percent; rendered with a leading sign.
  value: {
    type: [Number, String],
    default: null,
  },
  // Override the rendered label entirely.
  label: {
    type: String,
    default: null,
  },
});

const toneClass = computed(() => {
  switch (props.tone) {
    case "destructive":
      return "bg-destructive/10 text-destructive";
    case "warning":
      return "bg-warning/10 text-warning";
    case "success":
    default:
      return "bg-success/10 text-success";
  }
});

const icon = computed(() =>
  props.direction === "down" ? "lucide:trending-down" : "lucide:trending-up"
);

const text = computed(() => {
  if (props.label !== null) {
    return props.label;
  }
  if (props.value === null) {
    return "";
  }
  const sign = props.direction === "down" ? "-" : "+";
  return `${sign}${props.value}%`;
});
</script>

<template>
  <span
    :class="[
      'inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium tracking-tight tabular-nums',
      toneClass,
    ]"
  >
    <Icon :name="icon" class="size-3.5" />
    {{ text }}
  </span>
</template>
