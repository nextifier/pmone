<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 gap-2 p-4">
      <div class="flex flex-1 flex-col gap-1.5">
        <div class="bg-muted-foreground/20 h-1.5 w-4/5 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-3/5 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-4/5 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-2/3 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-4/5 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-1/2 rounded-full" />
      </div>
      <div class="bg-muted-foreground/40 h-12 w-1 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
