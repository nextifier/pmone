<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 flex-col justify-center gap-3 p-4">
      <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      <div class="bg-border h-px w-full" />
      <div class="bg-muted-foreground/40 h-1.5 w-2/3 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
