<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 items-center gap-2 px-3 py-2.5">
      <div class="flex aspect-3/2 h-3 overflow-hidden rounded-xs shadow-xs">
        <div class="bg-muted-foreground/40 h-full flex-1" />
        <div class="bg-muted-foreground/15 h-full flex-1" />
      </div>
      <Icon name="lucide:chevrons-up-down" class="text-muted-foreground/60 size-3" />
      <div class="bg-muted-foreground/40 ml-2 h-1.5 flex-1 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
