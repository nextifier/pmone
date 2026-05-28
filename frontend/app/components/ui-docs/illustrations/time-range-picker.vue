<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 items-center justify-center gap-2 px-3 py-2.5">
      <Icon name="lucide:clock" class="text-muted-foreground/88 size-4" />
      <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
      <Icon name="lucide:arrow-right" class="text-muted-foreground/60 size-3" />
      <Icon name="lucide:clock" class="text-muted-foreground/88 size-4" />
      <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
