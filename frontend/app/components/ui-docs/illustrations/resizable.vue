<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center p-4">
      <div class="border-border bg-card flex h-16 w-32 overflow-hidden rounded-md border">
        <div class="bg-muted/40 flex-1" />
        <div class="bg-muted-foreground/60 w-px" />
        <div class="bg-muted/60 flex-1" />
      </div>
    </div>
  </IllustrationFrame>
</template>
