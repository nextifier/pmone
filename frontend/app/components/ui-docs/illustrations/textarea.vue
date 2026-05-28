<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 flex-col gap-1.5 px-4 py-3">
      <div class="bg-muted-foreground/40 h-1.5 w-4/5 rounded-full" />
      <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      <div class="bg-muted-foreground/40 h-1.5 w-2/3 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
