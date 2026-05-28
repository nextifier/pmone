<template>
  <div :class="frameClass" :style="{ '--radius-2xl': radius }">
    <slot />
  </div>
</template>

<script setup>
const props = defineProps({
  maxWidth: {
    type: String,
    default: "36",
  },
  radius: {
    type: String,
    default: "14px",
  },
  variant: {
    type: String,
    default: "gradient",
    validator: (v) => ["gradient", "solid", "primary"].includes(v),
  },
  overflow: {
    type: Boolean,
    default: false,
  },
});

const base =
  "relative flex w-full flex-col rounded-2xl text-card-foreground shadow-md/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_-1px_--theme(--color-white/6%),0_1px_--theme(--color-black/6%)]";

const variantClass = computed(() => {
  switch (props.variant) {
    case "solid":
      return "border bg-card/99 dark:bg-card";
    case "primary":
      return "border-none bg-linear-to-b from-(--btn-from) to-(--btn-to)";
    case "gradient":
    default:
      return "border bg-linear-to-b from-[color-mix(in_srgb,var(--card)_96%,var(--color-white))] to-[color-mix(in_srgb,var(--card)_99%,var(--color-black))] dark:to-[color-mix(in_srgb,var(--card)_98%,var(--color-white))]";
  }
});

const maxWidthClass = computed(() => {
  const map = {
    24: "max-w-24",
    36: "max-w-36",
    50: "max-w-50",
    72: "max-w-72",
  };
  return map[props.maxWidth] || "max-w-36";
});

const frameClass = computed(() => [
  base,
  variantClass.value,
  maxWidthClass.value,
  props.overflow && "overflow-hidden",
]);
</script>
