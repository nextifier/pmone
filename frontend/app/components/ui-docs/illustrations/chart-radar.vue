<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <svg viewBox="0 0 48 48" class="size-12">
        <polygon
          points="24,6 39,15 39,33 24,42 9,33 9,15"
          fill="none"
          class="stroke-muted-foreground/30"
          stroke-width="1.5"
        />
        <polygon
          points="24,15 33,19 33,29 24,33 15,29 15,19"
          fill="none"
          class="stroke-muted-foreground/30"
          stroke-width="1.5"
        />
        <polygon
          points="24,9 36,24 28,36 16,32 12,18"
          class="fill-(--btn-to)/25 stroke-(--btn-to)"
          stroke-width="1.5"
          stroke-linejoin="round"
        />
      </svg>
    </div>
  </IllustrationFrame>
</template>
