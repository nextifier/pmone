<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="64">
    <div class="text-muted-foreground/50 flex flex-1 items-center justify-center p-4">
      <svg viewBox="0 0 64 36" class="w-full" fill="none">
        <polyline
          points="2,28 14,18 26,23 38,9 50,15 62,4"
          stroke="currentColor"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <circle cx="62" cy="4" r="3" class="fill-(--btn-to)" />
      </svg>
    </div>
  </IllustrationFrame>
</template>
