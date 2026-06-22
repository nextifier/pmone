<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="64">
    <div class="text-muted-foreground/50 flex flex-1 items-center justify-center p-4">
      <svg viewBox="0 0 64 36" class="w-full" fill="none">
        <path
          d="M2 28 L14 18 L26 23 L38 9 L50 15 L62 6 L62 34 L2 34 Z"
          class="fill-muted-foreground/20"
        />
        <polyline
          points="2,28 14,18 26,23 38,9 50,15 62,6"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </div>
  </IllustrationFrame>
</template>
