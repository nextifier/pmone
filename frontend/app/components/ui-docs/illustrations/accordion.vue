<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 flex-col gap-1.5 p-3">
      <div class="flex items-center justify-between rounded-md px-2 py-2">
        <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
        <div class="bg-muted-foreground/40 size-1.5 rounded-full" />
      </div>
      <div class="bg-muted-foreground/8 flex flex-col gap-1 rounded-md p-2">
        <div class="bg-muted-foreground/20 h-1.5 w-3/4 rounded-full" />
        <div class="bg-muted-foreground/20 h-1.5 w-1/2 rounded-full" />
      </div>
      <div class="flex items-center justify-between rounded-md px-2 py-2">
        <div class="bg-muted-foreground/40 h-1.5 w-16 rounded-full" />
        <div class="bg-muted-foreground/20 size-1.5 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
