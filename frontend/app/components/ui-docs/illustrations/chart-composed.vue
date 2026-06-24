<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <svg viewBox="0 0 56 40" class="h-10 w-14">
        <path
          d="M4,30 L16,18 L28,26 L40,10 L52,20 L52,36 L4,36 Z"
          class="fill-muted-foreground/15"
        />
        <path
          d="M4,30 L16,18 L28,26 L40,10 L52,20"
          fill="none"
          class="stroke-(--btn-to)"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </div>
  </IllustrationFrame>
</template>
