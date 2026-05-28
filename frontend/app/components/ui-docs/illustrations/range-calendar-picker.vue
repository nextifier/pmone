<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 items-center gap-2 px-4 py-2.5">
      <Icon name="hugeicons:calendar-04" class="text-muted-foreground/88 size-4" />
      <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
      <Icon name="lucide:arrow-right" class="text-muted-foreground/60 size-3" />
      <div class="bg-muted-foreground/40 h-1.5 w-12 rounded-full" />
    </div>
  </IllustrationFrame>
</template>
