<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 items-center justify-between gap-2 py-2.5 ps-4 pe-2.5">
      <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
      <Icon name="lucide:chevron-down" class="text-muted-foreground/88 size-4" />
    </div>
  </IllustrationFrame>
</template>
