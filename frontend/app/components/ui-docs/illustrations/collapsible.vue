<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36">
    <div class="flex flex-1 flex-col gap-2 p-4">
      <div class="flex items-center justify-between">
        <div class="bg-muted-foreground/40 h-1.5 w-14 rounded-full" />
        <Icon name="lucide:chevrons-up-down" class="text-muted-foreground/60 size-3" />
      </div>
      <div class="bg-muted-foreground/8 h-3.5 rounded-sm" />
      <div class="bg-muted-foreground/8 h-3.5 rounded-sm" />
    </div>
  </IllustrationFrame>
</template>
