<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <svg viewBox="0 0 48 48" class="size-12 -rotate-90">
        <circle cx="24" cy="24" r="18" fill="none" class="stroke-muted-foreground/20" stroke-width="5" />
        <circle cx="24" cy="24" r="12" fill="none" class="stroke-muted-foreground/20" stroke-width="5" />
        <circle
          cx="24"
          cy="24"
          r="18"
          fill="none"
          class="stroke-(--btn-to)"
          stroke-width="5"
          stroke-linecap="round"
          stroke-dasharray="85 113"
        />
        <circle
          cx="24"
          cy="24"
          r="12"
          fill="none"
          class="stroke-(--btn-to)/60"
          stroke-width="5"
          stroke-linecap="round"
          stroke-dasharray="45 75"
        />
      </svg>
    </div>
  </IllustrationFrame>
</template>
