<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50">
    <div class="flex flex-1 items-center justify-center gap-1 p-4">
      <Icon name="lucide:chevron-left" class="text-muted-foreground/60 size-3" />
      <div class="border-border bg-card flex size-6 items-center justify-center rounded text-xs">1</div>
      <div class="bg-primary text-primary-foreground flex size-6 items-center justify-center rounded text-xs">2</div>
      <div class="border-border bg-card flex size-6 items-center justify-center rounded text-xs">3</div>
      <Icon name="lucide:chevron-right" class="text-muted-foreground/60 size-3" />
    </div>
  </IllustrationFrame>
</template>
