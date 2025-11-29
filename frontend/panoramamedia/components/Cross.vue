<template>
  <span
    class="z-20 size-[var(--cross-size)] transition duration-500 hover:rotate-180"
    :class="{
      'absolute top-0 left-0 -translate-x-1/2 -translate-y-1/2':
        position === 'top-left',

      'absolute top-0 right-0 translate-x-1/2 -translate-y-1/2':
        position === 'top-right',

      'absolute bottom-0 left-0 -translate-x-1/2 translate-y-1/2':
        position === 'bottom-left',

      'absolute right-0 bottom-0 translate-x-1/2 translate-y-1/2':
        position === 'bottom-right',
    }"
    :style="{
      '--cross-size': '1.5rem',
      '--cross-color': 'var(--color-primary)',
    }"
  >
    <span
      class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 border-l border-[var(--cross-color)]"
    ></span>

    <span
      class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 border-t border-[var(--cross-color)]"
    ></span>
  </span>
</template>

<script setup>
const props = defineProps({
  position: {
    type: String,
    default: "top-left",
    validator: (value) =>
      ["top-left", "top-right", "bottom-left", "bottom-right"].includes(value),
  },
});
</script>
