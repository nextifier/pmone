<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 items-center gap-2 px-4 py-2.5">
      <Icon name="lucide:link" class="text-muted-foreground/60 size-4" />
      <div class="bg-muted-foreground/20 h-1.5 w-16 rounded-full" />
      <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
