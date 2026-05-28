<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 gap-2 p-3">
      <div class="bg-sidebar border-border flex w-1/3 flex-col gap-2 rounded-md border p-2">
        <div class="bg-sidebar-accent h-1.5 w-full rounded-full" />
        <div class="bg-muted-foreground/30 h-1.5 w-3/4 rounded-full" />
        <div class="bg-muted-foreground/30 h-1.5 w-full rounded-full" />
        <div class="bg-muted-foreground/30 h-1.5 w-2/3 rounded-full" />
      </div>
      <div class="flex flex-1 flex-col gap-2 p-2">
        <div class="bg-muted-foreground/30 h-1.5 w-1/2 rounded-full" />
        <div class="bg-muted-foreground/15 h-3 rounded-sm" />
        <div class="bg-muted-foreground/15 h-3 rounded-sm" />
      </div>
    </div>
  </IllustrationFrame>
</template>
