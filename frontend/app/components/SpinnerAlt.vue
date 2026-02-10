<template>
  <div
    class="loader aspect-square animate-spin rounded-full"
    :style="{
      '--spinner-size': size,
      '--spinner-color': color,
      '--spinner-border': borderWidth,
      width: 'var(--spinner-size, 2.5rem)',
    }"
  ></div>
</template>

<script setup lang="ts">
defineProps<{
  size?: string;
  color?: string;
  borderWidth?: string;
}>();
</script>

<style scoped>
.loader {
  background:
    radial-gradient(farthest-side, var(--spinner-color, var(--color-primary)) 94%, #0000)
      top / var(--spinner-border, 4px) var(--spinner-border, 4px) no-repeat,
    conic-gradient(#0000 30%, var(--spinner-color, var(--color-primary)));
  -webkit-mask: radial-gradient(
    farthest-side,
    #0000 calc(100% - var(--spinner-border, 4px)),
    #000 0
  );
  mask: radial-gradient(
    farthest-side,
    #0000 calc(100% - var(--spinner-border, 4px)),
    #000 0
  );
}
</style>
