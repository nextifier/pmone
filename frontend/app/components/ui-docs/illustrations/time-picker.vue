<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36" variant="solid">
    <div class="flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5">
      <Icon name="lucide:clock" class="text-muted-foreground/88 size-4" />
      <div class="bg-muted-foreground/40 h-1.5 w-4 rounded-full" />
      <div class="text-muted-foreground/40 text-sm">:</div>
      <div class="bg-muted-foreground/40 h-1.5 w-4 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
