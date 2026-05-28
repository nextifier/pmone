<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center gap-2 p-4">
      <div class="bg-muted-foreground/40 h-1.5 w-8 rounded-full" />
      <Icon name="lucide:chevron-right" class="text-muted-foreground/40 size-3" />
      <div class="bg-muted-foreground/40 h-1.5 w-10 rounded-full" />
      <Icon name="lucide:chevron-right" class="text-muted-foreground/40 size-3" />
      <div class="bg-muted-foreground/88 h-1.5 w-12 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
