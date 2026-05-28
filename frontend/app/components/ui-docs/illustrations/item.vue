<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center gap-3 p-4">
      <div class="bg-muted-foreground/15 flex size-9 items-center justify-center rounded-lg">
        <div class="bg-muted-foreground/40 size-4 rounded-sm" />
      </div>
      <div class="flex flex-1 flex-col gap-1.5">
        <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-4/5 rounded-full" />
      </div>
      <div class="bg-muted-foreground/20 h-4 w-8 rounded-sm" />
    </div>
  </IllustrationFrame>
</template>
