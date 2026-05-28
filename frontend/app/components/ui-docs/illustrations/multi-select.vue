<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 flex-wrap items-center gap-1.5 p-2">
      <div class="bg-muted text-muted-foreground flex items-center gap-1 rounded-md px-2 py-1 text-xs">
        Design
        <Icon name="lucide:x" class="size-3" />
      </div>
      <div class="bg-muted text-muted-foreground flex items-center gap-1 rounded-md px-2 py-1 text-xs">
        Eng
        <Icon name="lucide:x" class="size-3" />
      </div>
      <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
