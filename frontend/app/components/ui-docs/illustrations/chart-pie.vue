<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <svg viewBox="0 0 36 36" class="size-12 -rotate-90">
        <circle
          cx="18"
          cy="18"
          r="14"
          fill="none"
          class="stroke-muted-foreground/30"
          stroke-width="7"
        />
        <circle
          cx="18"
          cy="18"
          r="14"
          fill="none"
          class="stroke-(--btn-to)"
          stroke-width="7"
          stroke-dasharray="33 88"
          stroke-linecap="round"
        />
      </svg>
    </div>
  </IllustrationFrame>
</template>
