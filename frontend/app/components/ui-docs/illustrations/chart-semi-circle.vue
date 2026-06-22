<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="64">
    <div class="text-muted-foreground/30 flex flex-1 items-center justify-center p-4">
      <svg viewBox="0 0 64 38" class="w-full" fill="none">
        <path
          d="M6 34 A26 26 0 0 1 58 34"
          stroke="currentColor"
          stroke-width="5"
          stroke-linecap="round"
        />
        <path
          d="M6 34 A26 26 0 0 1 44 11"
          class="stroke-(--btn-to)"
          stroke-width="5"
          stroke-linecap="round"
        />
      </svg>
    </div>
  </IllustrationFrame>
</template>
