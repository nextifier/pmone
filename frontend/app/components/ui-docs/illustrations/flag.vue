<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="24">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="flex aspect-3/2 h-8 overflow-hidden rounded-sm shadow">
        <div class="bg-muted-foreground/40 h-full flex-1" />
        <div class="bg-muted-foreground/15 h-full flex-1" />
        <div class="bg-muted-foreground/60 h-full flex-1" />
      </div>
    </div>
  </IllustrationFrame>
</template>
