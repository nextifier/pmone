<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 flex-col items-center justify-center gap-2 p-4">
      <div class="bg-muted flex size-10 items-center justify-center rounded-full">
        <Icon name="lucide:inbox" class="text-muted-foreground/60 size-5" />
      </div>
      <div class="bg-muted-foreground/40 h-1.5 w-16 rounded-full" />
      <div class="bg-muted-foreground/20 h-1.5 w-24 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
