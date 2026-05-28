<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="72" variant="solid">
    <div class="flex flex-1 items-center px-2 py-2">
      <Icon name="lucide:search" class="text-muted-foreground/60 mx-2 size-4" />
      <div class="bg-muted-foreground/40 h-1.5 flex-1 rounded-full" />
      <div class="bg-primary h-6 w-12 rounded-r-md" />
    </div>
  </IllustrationFrame>
</template>
