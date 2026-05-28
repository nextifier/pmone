<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col items-start justify-center gap-2 p-4">
      <div class="flex items-center gap-2">
        <div class="bg-success size-2.5 rounded-full" />
        <div class="bg-muted-foreground/40 h-1.5 w-14 rounded-full" />
      </div>
      <div class="flex items-center gap-2">
        <div class="bg-warning size-2.5 rounded-full" />
        <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
      </div>
      <div class="flex items-center gap-2">
        <div class="bg-destructive size-2.5 rounded-full" />
        <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
