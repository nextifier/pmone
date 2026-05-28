<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="36" variant="solid">
    <div class="flex flex-1 items-center justify-between px-3 py-2.5">
      <Icon name="lucide:minus" class="text-muted-foreground/60 size-3" />
      <div class="bg-muted-foreground/40 h-1.5 w-6 rounded-full" />
      <Icon name="lucide:plus" class="text-muted-foreground/60 size-3" />
    </div>
  </IllustrationFrame>
</template>
