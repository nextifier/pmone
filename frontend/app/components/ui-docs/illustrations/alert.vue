<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-start gap-3 p-4">
      <div class="bg-muted-foreground/40 mt-0.5 size-3 shrink-0 rounded-full" />
      <div class="flex flex-1 flex-col gap-2">
        <div class="bg-muted-foreground/40 h-1.5 w-2/3 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-full rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
