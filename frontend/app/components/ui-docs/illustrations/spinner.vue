<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 items-center justify-center gap-2 p-4">
      <div class="border-muted-foreground/20 border-t-primary size-5 animate-spin rounded-full border-2" />
      <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
