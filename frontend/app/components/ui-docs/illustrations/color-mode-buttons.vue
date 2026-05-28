<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center gap-2 p-4">
      <div class="border-border h-9 w-12 rounded-md border bg-card">
        <div class="border-border mt-1 ml-1 h-2 w-4 rounded-sm border bg-background" />
      </div>
      <div class="border-primary ring-primary/20 h-9 w-12 rounded-md border-2 ring bg-foreground/85">
        <div class="border-foreground/60 mt-1 ml-1 h-2 w-4 rounded-sm border bg-foreground/40" />
      </div>
      <div class="border-border h-9 w-12 overflow-hidden rounded-md border">
        <div class="from-card to-foreground/85 h-full w-full bg-linear-to-r" />
      </div>
    </div>
  </IllustrationFrame>
</template>
