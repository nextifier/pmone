<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 flex-col gap-1.5 p-3">
      <div class="border-border bg-card flex items-center gap-2 rounded-md border px-2 py-1.5">
        <Icon name="lucide:search" class="text-muted-foreground/60 size-3" />
        <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
      </div>
      <div class="bg-muted-foreground/15 mx-2 mt-1 h-1.5 w-8 rounded-full" />
      <div class="flex items-center gap-2 rounded-md px-2 py-1">
        <div class="bg-muted-foreground/40 size-3 rounded-sm" />
        <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
      </div>
      <div class="bg-muted-foreground/10 flex items-center gap-2 rounded-md px-2 py-1">
        <div class="bg-muted-foreground/40 size-3 rounded-sm" />
        <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
      </div>
    </div>
  </IllustrationFrame>
</template>
